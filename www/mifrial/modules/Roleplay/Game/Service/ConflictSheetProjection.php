<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Service\Read\CharacterSectionMask;

/**
 * Проецирует конфликтный снимок теми же правилами, что обычные чтения.
 */
final class ConflictSheetProjection
{
    /**
     * Создаёт проекцию конфликтов.
     *
     * @param CharacterSectionMask $characterSectionMask Маска Character detail.
     * @param GameCharacterProjectionMask $gameCharacterMask Маска Character в Game.
     * @param GameNpcVisibility $npcVisibility Маска NPC.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterSectionMask $characterSectionMask,
        private readonly GameCharacterProjectionMask $gameCharacterMask,
        private readonly GameNpcVisibility $npcVisibility,
    ) {
    }

    /**
     * Проецирует Character для game conflict.
     *
     * @param array<string, mixed> $choices Choices.
     * @param array<string, mixed> $sheet Sheet.
     * @param array<int, string> $sections Видимые секции.
     * @param bool $fullSheet Полный доступ.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>} Безопасный снимок.
     */
    public function projectGameCharacter(
        array $choices,
        array $sheet,
        array $sections,
        bool $fullSheet,
    ): array {
        return $this->gameCharacterMask->apply($choices, $sheet, $sections, $fullSheet);
    }

    /**
     * Проецирует Character для owner conflict.
     *
     * @param array<string, mixed> $choices Choices.
     * @param array<string, mixed> $sheet Sheet.
     * @param array<int, string> $sections Видимые секции.
     * @param bool $fullSheet Полный доступ.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>} Безопасный снимок.
     */
    public function projectCharacter(
        array $choices,
        array $sheet,
        array $sections,
        bool $fullSheet,
    ): array {
        if ($fullSheet) {
            return ['choices' => $choices, 'sheet' => $sheet];
        }

        return $this->characterSectionMask->apply($choices, $sheet, $sections);
    }

    /**
     * Проецирует NPC conflict.
     *
     * @param array<string, mixed> $version Version document.
     * @param array<string, mixed> $visibility Visibility policy.
     * @param bool $fullSheet Полный доступ.
     *
     * @return array<string, mixed> Безопасный version.
     */
    public function projectNpc(array $version, array $visibility, bool $fullSheet): array
    {
        return $this->npcVisibility->mask($version, $visibility, $fullSheet);
    }
}
