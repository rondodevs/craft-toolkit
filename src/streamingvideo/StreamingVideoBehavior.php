<?php

namespace rondodevs\toolkit\streamingvideo;

use yii\base\Behavior;

/**
 * Gives assets the `toolkitStreaming` attribute, so Craft sets it from the
 * Streaming block of the edit form like any native attribute. Read after the
 * save by StreamingVideoService::handleSave(), never stored on the asset.
 */
class StreamingVideoBehavior extends Behavior
{
    /**
     * @var array|null ['enabled' => '1'|'', 'posterTime' => string, 'posterImageId' => string, 'resetPoster' => '1'|''],
     * or null when the asset wasn't saved from its edit form
     */
    public ?array $toolkitStreaming = null;
}
