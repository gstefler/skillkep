<?php

namespace App\Enums;

enum MaterialType: string
{
    case Pdf = 'pdf';
    case Video = 'video';

    public static function fromExtension(string $extension): ?self
    {
        return match (strtolower($extension)) {
            'pdf' => self::Pdf,
            'mp4', 'm4v', 'mov' => self::Video,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF',
            self::Video => 'Videó',
        };
    }
}
