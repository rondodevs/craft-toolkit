<?php

namespace rondodevs\toolkit\fields;

use craft\fields\linktypes\Email;

/**
 * Craft's Email link type, registered in its place (same `email` id): on Link fields without "URL Suffix",
 * subject and body go in the value itself (`mailto:a@b.it?subject=…`), which Craft's pattern already allows.
 * The label, and the chip in the CP, show only the address.
 */
class EmailLinkType extends Email
{
    public function linkLabel(string $value): string
    {
        return parent::linkLabel(explode('?', $value, 2)[0]);
    }
}
