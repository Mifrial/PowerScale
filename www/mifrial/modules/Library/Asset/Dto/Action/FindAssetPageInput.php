<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;

/**
 * Вход `asset.findPage`.
 */
final class FindAssetPageInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param string $collectionModule Владелец назначения.
     * @param string $collectionCode Код коллекции.
     * @param string $scope Общая или личная часть.
     * @param OptionalString $q Подстрока имени.
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     *
     * @return void
     */
    public function __construct(
        public readonly string $collectionModule,
        public readonly string $collectionCode,
        public readonly string $scope,
        public readonly OptionalString $q,
        public readonly int $limit,
        public readonly int $offset,
    ) {
    }
}
