<?php

declare(strict_types=1);

namespace Mifrial\Core\Image\Enum;

/**
 * Формат производной картинки.
 */
enum OutputFormat: string
{
    case Webp = 'webp';
    case Jpeg = 'jpeg';
    case Png = 'png';

    /**
     * Mime-тип результата.
     *
     * @return string type/subtype.
     */
    public function mime(): string
    {
        return match ($this) {
            self::Webp => 'image/webp',
            self::Jpeg => 'image/jpeg',
            self::Png => 'image/png',
        };
    }
}
