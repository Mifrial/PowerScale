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
     * @param GameStrikeWideTrail $trail Модификаторы действия.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeWideChat $chat,
        private readonly GameActionRepository $actions,
        private readonly GameBattleRepository $battles,
        private readonly GameStrikeTargetClose $targets,
        private readonly GameStrikeWideTrail $trail,
    ) {
        $this->marks = new GameBattleMarks();
    }

    /**
     * Пишет основной чат удара до хвоста действия.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $open Открытый удар.
     * @param array{choices: list<array<string, mixed>>, refusals: list<string|null>, prepared: array<int, array<string, mixed>>, resolved: array<int, array<string, mixed>>, covers: array<int, array<string, mixed>|null>, rows: list<array<string, mixed>>, early: array<string, mixed>|null} $prepared Подготовка.
     * @param array{slice: \Mifrial\Roleplay\Character\Dto\CharacterRuleSlice, hitCode: string, rows: list<array<string, mixed>>, attackerRoll: array<string, mixed>} $opening Начало.
     * @param list<array<string, mixed>> $results Итоги целей.
     *
     * @return void
     */
    public function post(
        GameStrikeWideRun $run,
        array $open,
        array $prepared,
        array $opening,
        array $results,
    ): void {
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
    }

    /**
     * Исполняет модификаторы действия после основного чата.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $open Открытый удар.
     * @param list<array<string, mixed>> $rows Строки целей.
     *
     * @return array<string, mixed>|null Итог или null.
     */
    public function trail(GameStrikeWideRun $run, array $open, array $rows): ?array
    {
        return $this->trail->apply($run, $open, $rows);
    }

    /**
     * Сохраняет реакции, закрывает действие и пишет итог.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $open Открытый удар.
     * @param array{choices: list<array<string, mixed>>, refusals: list<string|null>, prepared: array<int, array<string, mixed>>, resolved: array<int, array<string, mixed>>, covers: array<int, array<string, mixed>|null>, rows: list<array<string, mixed>>, early: array<string, mixed>|null} $prepared Подготовка.
     * @param array{slice: \Mifrial\Roleplay\Character\Dto\CharacterRuleSlice, hitCode: string, rows: list<array<string, mixed>>, attackerRoll: array<string, mixed>} $opening Начало.
     * @param list<array<string, mixed>> $results Итоги целей.
     * @param array<string, mixed> $pending Новая карта ожидания.
     * @param array<string, mixed>|null $modifiers Итог модификаторов.
     *
     * @return array<string, mixed> Итог закрытия.
     *
     * @throws GameInvalidException Если строка действия битая.
     */
    public function finish(
        GameStrikeWideRun $run,
        array $open,
        array $prepared,
        array $opening,
        array $results,
        array $pending,
        ?array $modifiers = null,
    ): array {
        $this->storeReactions($prepared['rows'], $prepared['choices'], $results);
        $strikeId = $this->strikeId($open);
        $this->actions->close($strikeId);
        $this->saveBattle($run, $pending);

        $result = $this->result($run, $strikeId, $opening['attackerRoll'], $results);
        if ($modifiers !== null) {
            $result['modifiers'] = $modifiers;
        }

        return $result;
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
