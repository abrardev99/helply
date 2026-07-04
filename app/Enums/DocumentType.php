<?php

namespace App\Enums;

enum DocumentType: string
{
    case Web = 'web';
    case Pdf = 'pdf';

    /**
     * Get the display label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Web => __('Website'),
            self::Pdf => __('PDF'),
        };
    }
}
