<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Снимок прошлого удара: запись, чтение и гейты добивания.
 */
final class GameLastStrikeSnapshot
{
    private readonly GameLastStrikeStore $store;

    private readonly GameLastStrikeCard $card;

    /**
     * Создаёт снимок.
     *
     * @return void
     */
    public function __construct()
    {
        $this->store = new GameLastStrikeStore();
        $this->card = new GameLastStrikeCard($this->store);
    }

    /**
     * Заменяет снимок прошлого удара в карте ожидания.
     *
     * @param array<string, mixed> $pending Карта боя.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     * @param string $sourceRuleCode Карточка удара.
     * @param string $kind Вид lethal или other.
     * @param array<int, array<string, mixed>> $hits Удары.
     *
     * @return array<string, mixed> Новая карта.
     */
    public function store(
        array $pending,
        string $actorKind,
        int $actorId,
        string $sourceRuleCode,
        string $kind,
        array $hits,
    ): array {
        return $this->store->store($pending, $actorKind, $actorId, $sourceRuleCode, $kind, $hits);
    }

    /**
     * Снимок прошлого удара актора.
     *
     * @param array<string, mixed> $pending Карта боя.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     *
     * @return array<string, mixed>|null Снимок или null.
     */
    public function snapshotOf(array $pending, string $actorKind, int $actorId): ?array
    {
        return $this->store->snapshotOf($pending, $actorKind, $actorId);
    }

    /**
     * Успех прошлого удара по цели.
     *
     * @param array<string, mixed> $pending Карта боя.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     * @param string $targetKey Ключ цели.
     *
     * @return int Успех или 0.
     */
    public function previousSr(array $pending, string $actorKind, int $actorId, string $targetKey): int
    {
        return $this->store->previousSr($pending, $actorKind, $actorId, $targetKey);
    }

    /**
     * Реакция цели в снимке прошлого удара.
     *
     * @param array<string, mixed> $pending Карта боя.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     * @param string $targetKey Ключ цели.
     *
     * @return string|null Реакция или null.
     */
    public function reactionOf(
        array $pending,
        string $actorKind,
        int $actorId,
        string $targetKey,
    ): ?string {
        return $this->store->reactionOf($pending, $actorKind, $actorId, $targetKey);
    }

    /**
     * Вид удара карточки: lethal, если она добивает от прошлого успеха.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     *
     * @return string lethal или other.
     */
    public function kindOf(CharacterRuleSlice $slice, string $actionRuleCode): string
    {
        return $this->card->kindOf($slice, $actionRuleCode);
    }

    /**
     * Успех удара с надбавкой от прошлого успеха.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param int $success Текущий успех.
     * @param int $previousSr Успех прошлого удара.
     *
     * @return int Итоговый успех.
     */
    public function boostSuccess(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        int $success,
        int $previousSr,
    ): int {
        return $this->card->boostSuccess($slice, $actionRuleCode, $success, $previousSr);
    }

    /**
     * Проверяет гейт добивания карточки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param array<string, mixed> $pending Карта боя.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     * @param array<int, string> $targetKeys Цели объявляемой атаки.
     *
     * @return void
     *
     * @throws GameInvalidException Если прошлого удара нет или цель чужая.
     */
    public function assertFollowUp(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        array $pending,
        string $actorKind,
        int $actorId,
        array $targetKeys,
    ): void {
        $this->card->assertFollowUp($slice, $actionRuleCode, $pending, $actorKind, $actorId, $targetKeys);
    }
}
