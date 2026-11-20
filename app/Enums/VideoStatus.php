<?php

namespace App\Enums;

/** HLS transcoding state of a video material (ADR-0004). */
enum VideoStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Feldolgozásra vár',
            self::Processing => 'Feldolgozás alatt',
            self::Ready => 'Kész',
            self::Failed => 'Sikertelen',
        };
    }
}
