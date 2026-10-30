<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use craft\elements\Asset;
use craft\web\Controller;
use craft\web\UploadedFile;
use rondodevs\toolkit\streamingvideo\Poster;
use rondodevs\toolkit\Toolkit;
use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class StreamingVideoController extends Controller
{
    public function actionSave(): ?Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $service = Toolkit::getInstance()->streamingVideo;

        try {
            $service->saveSettings([
                'qualities' => $request->getBodyParam('qualities') ?: [],
                'aboveHd' => (bool)$request->getBodyParam('aboveHd', false),
            ] + $service->getSettings());
        } catch (Throwable $e) {
            throw new BadRequestHttpException('Unable to save Toolkit streaming video settings.', 0, $e);
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Streaming video settings saved. Queue the videos to apply them to existing ones.'));

        return $this->redirectToPostedUrl();
    }

    public function actionQueue(): ?Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        $service = Toolkit::getInstance()->streamingVideo;

        if (!$service->isEnabled()) {
            throw new BadRequestHttpException('Streaming video is switched off: set ' . $service::ENV_VAR . '=true.');
        }

        $queued = $service->queueAll();

        Craft::$app->getSession()->setNotice(Craft::t('app', $queued === 0
            ? 'Every video is already encoded with the current settings.'
            : "{$queued} videos queued for encoding."));

        return $this->redirectToPostedUrl();
    }

    /**
     * Adds the Streaming Video field to a volume's layout, from the Overview.
     */
    public function actionAddField(): ?Response
    {
        $this->requireCpRequest();
        $this->requireAdmin();
        $this->requirePostRequest();

        $volume = Craft::$app->getVolumes()->getVolumeById((int)$this->request->getRequiredBodyParam('volumeId'));

        if (!$volume) {
            throw new BadRequestHttpException('Volume not found.');
        }

        $error = Toolkit::getInstance()->streamingVideo->addFieldToVolume($volume);

        if ($error !== null) {
            Craft::$app->getSession()->setError($error);
        } else {
            Craft::$app->getSession()->setNotice("Streaming field added to {$volume->name}, on video files only.");
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Queues the video again, even if it's already encoded with the current settings.
     */
    public function actionReprocess(): Response
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $service = Toolkit::getInstance()->streamingVideo;
        $asset = $this->requireStreamingVideo((int)$this->request->getRequiredBodyParam('assetId'));

        if (!$service->shouldEncode($asset)) {
            return $this->asFailure('Switch on Convert to HLS and save first.');
        }

        $service->reprocess($asset);

        return $this->asJson(['message' => 'Queued. The status updates when the page reloads.']);
    }

    /**
     * Thumbnail of an image chosen as the poster, shown until the video is saved.
     */
    public function actionImage(): Response
    {
        $this->requireCpRequest();
        $this->requireAcceptsJson();

        $image = Asset::find()->id((int)$this->request->getRequiredQueryParam('imageId'))->kind(Asset::KIND_IMAGE)->status(null)->one();

        if (!$image) {
            return $this->asFailure('Choose an image.');
        }

        return $this->asJson($this->imageData($image));
    }

    /**
     * Saves an uploaded image next to the posters: it becomes the poster
     * when the video is saved with it.
     */
    public function actionUploadPoster(): Response
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $asset = $this->requireStreamingVideo((int)$this->request->getRequiredBodyParam('assetId'));
        $upload = UploadedFile::getInstanceByName('poster');
        $extension = strtolower((string)$upload?->getExtension());

        if (!$upload || $upload->getHasError() || !in_array($extension, Poster::EXTENSIONS, true)) {
            return $this->asFailure('Upload a ' . strtoupper(implode(', ', Poster::EXTENSIONS)) . ' image.');
        }

        $path = $upload->saveAsTempFile();

        if (!$path) {
            return $this->asFailure('Could not read the uploaded image.');
        }

        try {
            $image = Toolkit::getInstance()->streamingVideo->uploadPosterImage($asset, $path, $extension);
        } catch (Throwable $e) {
            Craft::error('Poster upload failed: ' . $e->getMessage(), __METHOD__);

            return $this->asFailure('Could not save the poster: ' . $e->getMessage());
        } finally {
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        return $this->asJson($this->imageData($image));
    }

    public function actionPoster(): Response
    {
        $this->requireCpRequest();
        $this->requireAcceptsJson();

        return $this->asJson($this->posterData($this->requireStreamingVideo((int)$this->request->getRequiredQueryParam('assetId'))));
    }

    /**
     * @return array{imageId: int, thumbUrl: ?string}
     */
    private function imageData(Asset $image): array
    {
        return [
            'imageId' => $image->id,
            'thumbUrl' => Craft::$app->getAssets()->getThumbUrl($image, 640, 360),
        ];
    }

    /**
     * @return array{version: ?string, thumbUrl: ?string, kind: string, time: ?float, pending: bool}
     */
    private function posterData(Asset $asset): array
    {
        $service = Toolkit::getInstance()->streamingVideo;
        $poster = $service->getPoster($asset);

        return [
            // Changes with every new poster file
            'version' => $poster ? $poster->id . ':' . $poster->getFilename() : null,
            'thumbUrl' => $poster ? Craft::$app->getAssets()->getThumbUrl($poster, 640, 360) : null,
            // A frame still to be extracted after the last save
            'pending' => $service->needsPoster($asset),
            // Seconds of the frame the poster shows, null for a custom image
            'time' => $poster && !$service->isCustomPoster($asset) ? $service->getPosterTime($asset) : null,
            'kind' => match (true) {
                $poster === null => '',
                $service->isCustomPoster($asset) => 'Custom image',
                default => 'Frame at ' . number_format($service->getPosterTime($asset), 1) . 's',
            },
        ];
    }

    private function requireStreamingVideo(int $assetId): Asset
    {
        $service = Toolkit::getInstance()->streamingVideo;
        $asset = Asset::find()->id($assetId)->status(null)->one();

        if (!$asset || !$service->isVideo($asset)) {
            throw new NotFoundHttpException('Video not found.');
        }

        if (!Craft::$app->getElements()->canSave($asset)) {
            throw new ForbiddenHttpException('You can\'t edit this video.');
        }

        if (!$service->isEnabled()) {
            throw new BadRequestHttpException('Streaming video is switched off: set ' . $service::ENV_VAR . '=true.');
        }

        return $asset;
    }
}
