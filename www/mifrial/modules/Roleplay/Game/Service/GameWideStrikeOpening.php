<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameWideStrikeTargetRepository;

/**
 * Состав и строки открытого широкого удара.
 */
final class GameWideStrikeOpening
{
    /**
     * Создаёт проверку состава.
     *
     * @param GameBattleRepository $battles Состав.
     * @param GameWideStrikeTargetRepository $targets Цели.
     *
     * @return void
     */
    public function __construct(
        private readonly GameBattleRepository $battles,
        private readonly GameWideStrikeTargetRepository $targets,
    ) {
    }

    /**
     * Атакующий и цели — разные участники состава.
     *
     * @param int $battleId Бой.
     * @param array $choice Выбор.
     *
     * @return void
     *
     * @throws GameInvalidException Если пара совпала или вид чужой.
     * @throws GameNotFoundException Если пары нет в составе.
     */
    public function assertRoster(int $battleId, array $choice): void
    {
        $this->assertKind($choice['attacker']['type']);
        foreach ($choice['targets'] as $target) {
            $this->assertKind($target['type']);
            if ($choice['attacker'] === $target) {
                throw new GameInvalidException('Game wide strike target is invalid');
            }
        }

        $this->assertPresent($battleId, $choice['attacker']);
        foreach ($choice['targets'] as $target) {
            $this->assertPresent($battleId, $target);
        }
    }

    /**
     * Колонки открытого удара.
     *
     * @param GameBattleRecord $battle Бой.
     * @param int $sessionId Сессия.
     * @param array $choice Выбор.
     *
     * @return array<string, mixed> Строка.
     */
    public function row(GameBattleRecord $battle, int $sessionId, array $choice): array
    {
        return [
            'battle_id' => $battle->getId(),
            'session_id' => $sessionId,
            'attacker_kind' => $choice['attacker']['type'],
            'attacker_id' => $choice['attacker']['id'],
            'action_rule_code' => $choice['actionRuleCode'],
            'item_rule_code' => $choice['itemRuleCode'],
            'profile_type' => $choice['profileType'],
            'profile_index' => $choice['profileIndex'],
            'open' => true,
        ];
    }

    /**
     * Строки целей.
     *
     * @param int $strikeId Удар.
     * @param list<array{type: string, id: int}> $targets Цели.
     *
     * @return void
     *
     * @throws GameInvalidException Если поле.
     */
    public function addTargets(int $strikeId, array $targets): void
    {
        foreach ($targets as $target) {
            $this->targets->add([
                'strike_id' => $strikeId,
                'defender_kind' => $target['type'],
                'defender_id' => $target['id'],
                'reaction' => null,
                'block_item_rule_code' => null,
            ]);
        }
    }

    /**
     * Вид участника.
     *
     * @param string $kind Вид.
     *
     * @return void
     *
     * @throws GameInvalidException Если вид чужой.
     */
    private function assertKind(string $kind): void
    {
        if ($kind !== 'character' && $kind !== 'npc') {
            throw new GameInvalidException('Game wide strike participant is invalid');
        }
    }

    /**
     * Пара есть в составе.
     *
     * @param int $battleId Бой.
     * @param array{type: string, id: int} $side Участник.
     *
     * @return void
     *
     * @throws GameNotFoundException Если пары нет.
     */
    private function assertPresent(int $battleId, array $side): void
    {
        if (!in_array($side, $this->battles->findParticipants($battleId), true)) {
            throw new GameNotFoundException('Game battle participant was not found');
        }
    }
}
