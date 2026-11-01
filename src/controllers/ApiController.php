<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use craft\web\Controller;
use Throwable;
use yii\web\Response;

/**
 * JSON actions of the CP app: CP requests from admins, unless an action is listed in
 * `$editorActions`, and results as `{message, ...}` with a 400 on failure.
 */
abstract class ApiController extends Controller
{
    /** @var string[] Actions any CP user can call: each checks its own permissions */
    protected array $editorActions = [];

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requireCpRequest();

        if (!in_array($action->id, $this->editorActions, true)) {
            $this->requireAdmin(false);
        }

        return true;
    }

    protected function body(string $name, mixed $default = null): mixed
    {
        return $this->request->getBodyParam($name, $default);
    }

    /**
     * A service result: `success` and `message`, plus extra data on success.
     */
    protected function asResult(array $result, array $data = []): Response
    {
        return $result['success']
            ? $this->asSuccess($result['message'], $data)
            : $this->asFailure($result['message'], array_diff_key($result, ['success' => true, 'message' => true]));
    }

    /**
     * Runs the action: invalid input answers with its own message, anything else is logged and
     * answers with `$failure`.
     */
    protected function attempt(callable $action, string $failure): Response
    {
        try {
            return $action();
        } catch (\InvalidArgumentException $e) {
            return $this->asFailure($e->getMessage());
        } catch (Throwable $e) {
            Craft::error("$failure " . $e->getMessage(), static::class);

            return $this->asFailure($failure);
        }
    }

    /**
     * After a save: the message, and the screen's props again so it shows what was stored.
     */
    protected function asSaved(string $message, array $props): Response
    {
        return $this->asSuccess($message, ['props' => $props]);
    }
}
