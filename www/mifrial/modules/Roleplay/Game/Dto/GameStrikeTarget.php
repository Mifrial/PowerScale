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

    /**
     * Собирает запрос из идентификаторов.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $itemRuleCode Предмет.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     * @param int $itemInventoryId Строка инвентаря.
     * @param string $actorKind Character или npc.
     * @param int $actorId Участник.
     * @param array<string, mixed>|null $npcSheet Снимок sheet NPC.
     * @param array<string, mixed>|null $npcChoices Снимок choices NPC.
     * @param int|null $sheetVersion Версия листа NPC.
     * @param int|null $actionCharacteristicModifier Сдвиг характеристики.
     * @param int $strengthDrop Штраф силы броска.
     * @param int $gripBonus Бонус удержания.
     *
     * @return self Запрос.
     */
    public static function of(
        int $spaceId,
        int $rulesRevision,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        int $itemInventoryId,
        string $actorKind,
        int $actorId,
        ?array $npcSheet,
        ?array $npcChoices,
        ?int $sheetVersion = null,
        ?int $actionCharacteristicModifier = null,
        int $strengthDrop = 0,
        int $gripBonus = 0,
    ): self {
        return new self(
            self::profile($spaceId, $rulesRevision, $itemRuleCode, $profileType, $profileIndex, $itemInventoryId),
            new GameStrikeActorRef($actorKind, $actorId, $npcSheet, $npcChoices, $sheetVersion),
            $actionCharacteristicModifier,
            $strengthDrop,
            $gripBonus,
        );
    }

    /**
     * Ссылка на профиль.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $itemRuleCode Предмет.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     * @param int $itemInventoryId Строка инвентаря.
     *
     * @return GameStrikeProfileRef Профиль.
     */
    private static function profile(
        int $spaceId,
        int $rulesRevision,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        int $itemInventoryId,
    ): GameStrikeProfileRef {
        return new GameStrikeProfileRef(
            $spaceId,
            $rulesRevision,
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $itemInventoryId,
        );
    }
}
