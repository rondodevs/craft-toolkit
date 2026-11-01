<?php

namespace rondodevs\toolkit\cp\screens;

/**
 * A Toolkit section in the CP: the props its Vue screen (app/components/templates/screens)
 * starts from, sent again after every save.
 */
interface Screen
{
    public static function props(): array;
}
