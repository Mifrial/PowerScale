<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

/**
 * Запрос удара: профиль, атакующий и сдвиги характеристики.
 */
final class GameStrikeTarget
{
    /**
     * Создаёт запрос удара.
     *
     * @param GameStrikeProfileRef $profile Профиль удара.
     * @param GameStrikeActorRef $actor Атакующий.
     * @param int|null $actionCharacteristicModifier Сдвиг характеристики действия.
     * @param int $strengthDrop Штраф силы броска в размерах.
     * @param int $gripBonus Бонус удержания к Силе удара.
     *
     * @return void
     */
    public function __construct(
        public readonly GameStrikeProfileRef $profile,
        public readonly GameStrikeActorRef $actor,
        public readonly ?int $actionCharacteristicModifier,
        public readonly int $strengthDrop,
        public readonly int $gripBonus,
    ) {
    }
}
