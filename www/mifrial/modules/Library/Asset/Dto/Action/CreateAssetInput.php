<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalArray;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;

/**
 * Вход `asset.create`.
 */
final class CreateAssetInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param string $collectionModule Владелец назначения.
     * @param string $collectionCode Код коллекции.
     * @param string $filePublicId Внешний id файла.
     * @param string $scope Общая или личная часть.
     * @param OptionalString $name Подпись; пусто — из имени файла.
     * @param OptionalArray $meta Данные потребителя.
     *
     * @return void
     */
    public function __construct(
        public readonly string $collectionModule,
        public readonly string $collectionCode,
        public readonly string $filePublicId,
        public readonly string $scope,
        public readonly OptionalString $name,
        public readonly OptionalArray $meta,
    ) {
    }
}
