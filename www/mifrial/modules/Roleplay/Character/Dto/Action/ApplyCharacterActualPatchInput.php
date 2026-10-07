<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход character.applyActualPatch. Полный лист и урон биндер не принимает.
 */
final class ApplyCharacterActualPatchInput implements IActionInput
{
    /**
     * Собирает вход патча.
     *
     * @param int $characterId Персонаж.
     * @param int $expectedActualVersion Текущий actual_version.
     * @param array $operations Список операций.
     *
     * @return void
     */
    public function __construct(
        public readonly int $characterId,
        public readonly int $expectedActualVersion,
        public readonly array $operations,
    ) {
    }
}
