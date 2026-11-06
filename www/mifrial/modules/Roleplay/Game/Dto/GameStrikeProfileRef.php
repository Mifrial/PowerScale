<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

/**
 * Профиль удара: мир, ревизия, предмет и выбранная строка профиля.
 */
final class GameStrikeProfileRef
{
    /**
     * Создаёт ссылку на профиль.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $itemRuleCode Предмет.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение профиля.
     * @param int $itemInventoryId Строка инвентаря.
     *
     * @return void
     */
    public function __construct(
        public readonly int $spaceId,
        public readonly int $rulesRevision,
        public readonly string $itemRuleCode,
        public readonly string $profileType,
        public readonly int $profileIndex,
        public readonly int $itemInventoryId,
    ) {
    }
}
