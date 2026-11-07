<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeWideRun;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;

/**
 * Чат, реакции и сохранение результата широкого удара.
 */
final class GameStrikeWideFinalize
{
    private readonly GameBattleMarks $marks;

    /**
     * Создаёт финализацию.
     *
     * @param GameStrikeWideChat $chat Чат.
     * @param GameActionRepository $actions Действия и цели.
     * @param GameBattleRepository $battles Бои.
     * @param GameStrikeTargetClose $targets Расчёт цели.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeWideChat $chat,
        private readonly GameActionRepository $actions,
        private readonly GameBattleRepository $battles,
        private readonly GameStrikeTargetClose $targets,
    ) {
        $this->marks = new GameBattleMarks();
    }

    /**
     * Пишет чат, сохраняет реакции и закрывает действие.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $open Открытый удар.
     * @param array{choices: list<array<string, mixed>>, refusals: list<string|null>, prepared: array<int, array<string, mixed>>, resolved: array<int, array<string, mixed>>, covers: array<int, array<string, mixed>|null>, rows: list<array<string, mixed>>, early: array<string, mixed>|null} $prepared Подготовка.
     * @param array{slice: \Mifrial\Roleplay\Character\Dto\CharacterRuleSlice, hitCode: string, rows: list<array<string, mixed>>, attackerRoll: array<string, mixed>} $opening Начало.
     * @param list<array<string, mixed>> $results Итоги целей.
     * @param array<string, mixed> $pending Новая карта ожидания.
     *
     * @return array<string, mixed> Итог закрытия.
     *
     * @throws GameInvalidException Если строка действия битая.
     */
    public function finish(
        GameStrikeWideRun $run, array $open, array $prepared, array $opening, array $results, array $pending,
    ): array {
        $this->chat->post(
            $run->game,
            $prepared['rows'],
            $prepared['choices'],
            $results,
            $opening['attackerRoll'],
            $open,
            $run->battle,
            $opening['slice'],
            $run->scope->actorUserId,
            $prepared['covers'],
        );
        $this->storeReactions($prepared['rows'], $prepared['choices'], $results);
        $strikeId = $this->strikeId($open);
        $this->actions->close($strikeId);
        $this->saveBattle($run, $pending);

        return $this->result($run, $strikeId, $opening['attackerRoll'], $results);
    }

    /**
     * Итог закрытия удара.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param int $strikeId Действие.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param list<array<string, mixed>> $results Итоги целей.
     *
     * @return array<string, mixed> Итог.
     */
    private function result(GameStrikeWideRun $run, int $strikeId, array $attackerRoll, array $results): array
    {
        return [
            'battleId' => $run->battle->getId(),
            'strikeId' => $strikeId,
            'version' => $run->expectedBattleVersion + 1,
            'attackerRoll' => $attackerRoll,
            'targetResults' => $results,
            'closed' => true,
            'pendingTargets' => [],
        ];
    }

    /**
     * Пишет реакции принятых целей.
     *
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array<string, mixed>> $choices Защиты.
     * @param list<array<string, mixed>> $results Итоги.
     *
     * @return void
     *
     * @throws GameInvalidException Если строка цели битая.
     */
    private function storeReactions(array $rows, array $choices, array $results): void
    {
        foreach ($results as $index => $result) {
            if (($result['code'] ?? null) !== null) {
                continue;
            }

            $targetId = $rows[$index]['id'] ?? null;
            if (!is_int($targetId)) {
                throw new GameInvalidException('Game wide strike target row is invalid');
            }

            $this->actions->answerStrike($targetId, [
                'reaction' => $choices[$index]['reaction'],
                'block_item_inventory_id' => $choices[$index]['blockItemInventoryId'],
                'block_item_profile_index' => $choices[$index]['blockItemProfileIndex'],
                'block_item_rule_code' => $choices[$index]['blockItemRuleCode'],
            ]);
        }
    }

    /**
     * Сохраняет ожидание, цикл жетонов и метки боя.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $pending Новая карта ожидания.
     *
     * @return void
     */
    private function saveBattle(GameStrikeWideRun $run, array $pending): void
    {
        $markKeys = $this->targets->markKeys();
        $marks = $markKeys === [] ? null : $this->marks->consume($run->battle->getMarks(), $markKeys);
        if ($this->targets->tokenUsed()) {
            $this->battles->replaceTracked(
                $run->battle->getId(),
                $run->expectedBattleVersion,
                $pending,
                null,
                null,
                null,
                $this->targets->cycleAfter(),
                $marks,
            );

            return;
        }

        $this->battles->replacePending($run->battle->getId(), $run->expectedBattleVersion, $pending, $marks);
    }

    /**
     * Идентификатор действия удара.
     *
     * @param array<string, mixed> $open Открытый удар.
     *
     * @return int Действие.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function strikeId(array $open): int
    {
        $strikeId = $open['id'] ?? null;
        if (!is_int($strikeId)) {
            throw new GameInvalidException('Game wide strike is not open');
        }

        return $strikeId;
    }
}
