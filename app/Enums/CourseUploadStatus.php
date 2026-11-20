<?php

namespace App\Enums;

enum CourseUploadStatus: string
{
    case Uploading = 'uploading';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Uploading => 'Feltöltés alatt',
            self::Processing => 'Feldolgozás alatt',
            self::Completed => 'Kész',
            self::Failed => 'Sikertelen',
        };
    }
}
