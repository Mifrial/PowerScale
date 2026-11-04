<?php

declare(strict_types=1);

namespace Mifrial\Core\Image\Enum;

/**
 * Как вписывать картинку в рамку результата.
 */
enum FitMode: string
{
    case Cover = 'cover';
    case Contain = 'contain';
}
