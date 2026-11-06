<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponProfile;

/**
 * Подготовленный удар: срез, документы атакующего, предмет, профиль и контекст.
 *
 * Снимок неизменяем: калькуляторы дистанции, урона и защиты читают его
 * и не ходят в порты Character/Game.
 */
final class GameStrikeAttack
{
    /**
     * Создаёт снимок атаки.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param array<string, mixed> $documents Документы атакующего: sheet, choices и key.
     * @param ItemSpec $item Выбранный предмет.
     * @param WeaponProfile $profile Профиль атаки.
     * @param FormulaContext $context Контекст формулы профиля.
     *
     * @return void
     */
    public function __construct(
        public readonly CharacterRuleSlice $slice,
        public readonly array $documents,
        public readonly ItemSpec $item,
        public readonly WeaponProfile $profile,
        public readonly FormulaContext $context,
    ) {
    }
}
