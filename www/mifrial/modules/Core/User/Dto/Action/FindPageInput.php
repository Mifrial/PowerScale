<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalBool;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;

/**
 * Страница списка: limit/offset, опционально q и active.
 */
final class FindPageInput implements IActionInput
{
    /**
     * Собирает вход findPage.
     *
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     * @param OptionalString $q Подстрока.
     * @param OptionalBool $active Фильтр active.
     *
     * @return void
     */
    public function __construct(
        public readonly int $limit,
        public readonly int $offset,
        public readonly OptionalString $q,
        public readonly OptionalBool $active,
    ) {
    }
}
