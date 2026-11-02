<?php

namespace rondodevs\toolkit\streamingvideo;

/**
 * Where a video is in the encoding pipeline (MP4, plus HLS when switched
 * on): queued on save, processing in the worker, then completed or failed.
 */
enum ConversionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    // Not encoded yet, e.g. uploaded before streaming was on: the row only keeps the poster
    case Off = 'off';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Queued',
            self::Processing => 'Processing',
            self::Completed => 'Ready',
            self::Failed => 'Failed',
            self::Off => 'Off',
        };
    }

    /**
     * Craft's CP status dot color class.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'grey',
            self::Processing => 'orange',
            self::Completed => 'green',
            self::Failed => 'red',
            self::Off => 'light',
        };
    }

    public function badgeHtml(): string
    {
        return '<span class="status ' . $this->color() . '"></span> ' . $this->label();
    }
}
