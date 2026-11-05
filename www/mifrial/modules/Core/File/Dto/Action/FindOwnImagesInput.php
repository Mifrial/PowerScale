<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Плоский JSON `file.findOwnImages`: страница картинок актора.
 */
final class FindOwnImagesInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     *
     * @return void
     */
    public function __construct(
        public readonly int $limit,
        public readonly int $offset,
    ) {
    }
}
