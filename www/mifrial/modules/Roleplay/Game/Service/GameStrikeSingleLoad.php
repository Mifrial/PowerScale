<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeSingleRun;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Загрузка открытого удара и его строки.
 */
final class GameStrikeSingleLoad
{
    /**
     * Создаёт загрузку удара.
     *
     * @param GameStrikeCloseRows $rows Строки удара.
     * @param GameStrikeRules $rules Правила.
     * @param GamePushRules $push Толчок.
     * @param GameStrikeCover $cover Прикрытие.
     * @param GameProcessFlow $processes Сессия процесса.
     * @param GameStrikeSheets $sheets Листы.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeCloseRows $rows,
        private readonly GameStrikeRules $rules,
        private readonly GamePushRules $push,
        private readonly GameStrikeCover $cover,
        private readonly GameProcessFlow $processes,
        private readonly GameStrikeSheets $sheets,
    ) {
    }

    /**
     * Открывает удар и заполняет состояние строки.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param array<string, mixed>|null $resume Решение окна цели.
     *
     * @return void
     *
     * @throws GameInvalidException Если строка или актор битые.
     */
    public function load(GameStrikeSingleRun $run, ?array $resume): void
    {
        $this->open($run, $resume);
        $this->row($run, $resume);
    }

    /**
     * Открывает удар и читает его шапку.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param array<string, mixed>|null $resume Решение окна цели.
     *
     * @return void
     *
     * @throws GameInvalidException Если удар не открыт.
     */
    private function open(GameStrikeSingleRun $run, ?array $resume): void
    {
        $game = $run->input->game;
        $open = $resume !== null
            ? $this->rows->requireOpen($run->input->battleId, 'strike')
            : $this->rows->openSingle($run->input->battleId, $game, $run->scope->actorUserId, $run->editAll);
        $run->battle = $this->rows->battleOf($run->input->sessionId, $run->input->battleId, $run->input->expectedBattleVersion);
        $strikeId = $open['id'] ?? null;
        if (!is_int($strikeId)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        $strikes = is_array($open['strikes'] ?? null) ? $open['strikes'] : [];
        $actionRuleCode = is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '';
        $pushSpec = $this->push->pushOf($game, $actionRuleCode);
        $run->open = $open;
        $run->strikeId = $strikeId;
        $run->strikes = $strikes;
        $run->actionRuleCode = $actionRuleCode;
        $run->pushSpec = $pushSpec;
        $run->isPush = $pushSpec !== null;
        $run->sequential = $this->rules->cards->isSequentialStrike(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $actionRuleCode,
        );
        $run->activeIndex = $this->activeIndex($strikes, $run->sequential);
    }

    /**
     * Выбирает строку, применяет решение окна и снимок атаки.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param array<string, mixed>|null $resume Решение окна цели.
     *
     * @return void
     *
     * @throws GameInvalidException Если строка или актор битые.
     */
    private function row(GameStrikeSingleRun $run, ?array $resume): void
    {
        $row = is_array($run->strikes[$run->activeIndex] ?? null) ? $run->strikes[$run->activeIndex] : null;
        if ($row === null) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        $row = $this->resume($run, $row, $resume);
        $run->row = $row;
        $run->open = array_merge($run->open, [
            'item_inventory_id' => $row['item_inventory_id'] ?? null,
            'item_rule_code' => $row['item_rule_code'] ?? null,
            'profile_type' => $row['profile_type'] ?? null,
            'profile_index' => $row['profile_index'] ?? null,
            'conditions' => GameStrikeTargetRow::conditions($row),
        ]);
        $this->attacker($run);
        $run->packets = $run->sequential ? 1 : max(1, count($run->strikes));
        $run->target = $this->rows->rowTarget($row);
        $run->targetKey = $this->rows->targetKey($run->target);
        $run->process = $this->processes->strike($run->input->game, $run->open, $this->abilityLevels($run));
        $run->targetOpen = $this->rows->targetOpen($run->open, $run->target);
        $run->hitCode = $this->hitCode($run);
    }

    /**
     * Уровни способностей атакующего.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return array<string, int> Уровни.
     */
    private function abilityLevels(GameStrikeSingleRun $run): array
    {
        return $this->sheets->abilityLevels($run->input->game, $run->attackerKind, $run->attackerId);
    }

    /**
     * Код проверки попадания.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return string Код.
     */
    private function hitCode(GameStrikeSingleRun $run): string
    {
        if ($run->pushSpec === null) {
            return $this->rules->hitCheck->findHitCode(
                $run->input->game->getSpaceId(),
                $run->input->game->getRulesRevision(),
            );
        }

        return $this->push->checkCode($run->pushSpec);
    }

    /**
     * Применяет решение окна фактической цели.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param array<string, mixed> $row Строка удара.
     * @param array<string, mixed>|null $resume Решение.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameInvalidException Если окно открыто или выбор чужой.
     */
    private function resume(GameStrikeSingleRun $run, array $row, ?array $resume): array
    {
        $maneuverResume = $resume !== null && is_array($resume['maneuver'] ?? null) ? $resume['maneuver'] : null;
        if ($resume === null && $this->maneuverWindowOpen($row)) {
            throw new GameInvalidException('Game maneuver window is open');
        }

        $run->maneuverResume = $maneuverResume;
        if ($resume !== null && $maneuverResume === null) {
            $state = $this->resumeState($row, $resume);
            $run->resumeState = $state;
            $run->input->expectedSheetVersion = is_int($state['expectedSheetVersion'] ?? null)
                ? $state['expectedSheetVersion']
                : 0;

            return is_array($state['row'] ?? null) ? $state['row'] : $row;
        }

        if ($maneuverResume !== null) {
            $run->input->expectedSheetVersion = is_int($row['expected_sheet_version'] ?? null)
                ? $row['expected_sheet_version']
                : 0;
        }

        return $row;
    }

    /**
     * Атакующий открытого удара.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     *
     * @throws GameInvalidException Если актор битый.
     */
    private function attacker(GameStrikeSingleRun $run): void
    {
        $kind = $run->open['attacker_kind'] ?? null;
        $id = $run->open['attacker_id'] ?? null;
        if (!is_string($kind) || !is_int($id)) {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        $run->attackerKind = $kind;
        $run->attackerId = $id;
    }

    /**
     * Индекс первого неотвеченного удара.
     *
     * @param list<array<string, mixed>> $strikes Строки.
     * @param bool $sequential Последовательный удар.
     *
     * @return int Индекс.
     */
    private function activeIndex(array $strikes, bool $sequential): int
    {
        if (!$sequential) {
            return 0;
        }

        foreach ($strikes as $index => $strikeRow) {
            if (($strikeRow['reaction'] ?? null) === null) {
                return $index;
            }
        }

        return 0;
    }

    /**
     * Открыто ли окно раскрытия манёвра по строке.
     *
     * @param array<string, mixed> $row Строка удара.
     *
     * @return bool Признак.
     */
    private function maneuverWindowOpen(array $row): bool
    {
        $resolution = $this->jsonRow($row, 'resolution');
        if (!in_array($resolution['phase'] ?? null, ['check', 'decision'], true)) {
            return false;
        }

        foreach (is_array($resolution['awaiting'] ?? null) ? $resolution['awaiting'] : [] as $entry) {
            if (is_array($entry) && ($entry['kind'] ?? null) === 'maneuver_reveal') {
                return true;
            }
        }

        return false;
    }

    /**
     * Восстанавливает состояние окна выбора фактической цели.
     *
     * @param array<string, mixed> $row Строка удара.
     * @param array<string, mixed> $resume Решение.
     *
     * @return array<string, mixed> Состояние.
     *
     * @throws GameInvalidException Если окно закрыто или выбор чужой.
     */
    private function resumeState(array $row, array $resume): array
    {
        $resolution = $this->jsonRow($row, 'resolution');
        $key = $resume['actualTarget'] ?? null;
        $candidate = $this->candidate($resolution, $key);
        [$actor, $choice, $attackerRoll] = $this->values($candidate);
        $row = $this->restore($row, $actor, $choice, $candidate);
        $candidates = $resolution['candidates'] ?? [];
        $primaryKey = is_string($resolution['primaryKey'] ?? null) ? $resolution['primaryKey'] : null;
        $cover = $primaryKey === null ? null : $this->cover->info(is_array($candidates) ? $candidates : [], $primaryKey, is_string($key) ? $key : null);

        return [
            'row' => $row,
            'choice' => [
                'reaction' => $choice['reaction'],
                'blockItemInventoryId' => $choice['blockItemInventoryId'] ?? null,
                'blockItemProfileIndex' => $choice['blockItemProfileIndex'] ?? null,
                'blockItemRuleCode' => $choice['blockItemRuleCode'] ?? null,
                'turn' => false,
            ],
            'attackerRoll' => $attackerRoll,
            'defenderRoll' => is_array($candidate['defenderRoll'] ?? null) ? $candidate['defenderRoll'] : null,
            'expectedSheetVersion' => is_int($candidate['expectedSheetVersion'] ?? null)
                ? $candidate['expectedSheetVersion']
                : 0,
            'prepaid' => true,
            'cover' => $cover,
            'markKeys' => is_array($candidate['markKeys'] ?? null) ? $candidate['markKeys'] : [],
        ];
    }

    /**
     * Кандидат окна по ключу цели.
     *
     * @param array<string, mixed> $resolution Решение строки.
     * @param mixed $key Ключ цели.
     *
     * @return array<string, mixed> Кандидат.
     *
     * @throws GameInvalidException Если окно закрыто.
     */
    private function candidate(array $resolution, mixed $key): array
    {
        $candidates = $resolution['candidates'] ?? null;
        if (!is_array($candidates) || !is_string($key) || !is_array($candidates[$key] ?? null)) {
            throw new GameInvalidException('Game strike window is not open');
        }

        return $candidates[$key];
    }

    /**
     * Проверенные значения кандидата.
     *
     * @param array<string, mixed> $candidate Кандидат.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>} Значения.
     *
     * @throws GameInvalidException Если выбор чужой.
     */
    private function values(array $candidate): array
    {
        $actor = $candidate['actor'] ?? null;
        $choice = $candidate['choice'] ?? null;
        $attackerRoll = $candidate['attackerRoll'] ?? null;
        if (!is_array($actor) || !is_string($actor['type'] ?? null) || !is_int($actor['id'] ?? null)) {
            throw new GameInvalidException('Game strike window choice is invalid');
        }

        if (!is_array($choice) || !is_array($attackerRoll)) {
            throw new GameInvalidException('Game strike window choice is invalid');
        }

        return [$actor, $choice, $attackerRoll];
    }

    /**
     * Переносит выбранную цель в строку удара.
     *
     * @param array<string, mixed> $row Строка удара.
     * @param array<string, mixed> $actor Цель.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $candidate Кандидат.
     *
     * @return array<string, mixed> Строка.
     */
    private function restore(array $row, array $actor, array $choice, array $candidate): array
    {
        $row['target_type'] = $actor['type'];
        $row['target_id'] = $actor['id'];
        $row['reaction'] = $choice['reaction'] ?? null;
        $row['block_item_inventory_id'] = $choice['blockItemInventoryId'] ?? null;
        $row['block_item_profile_index'] = $choice['blockItemProfileIndex'] ?? null;
        $row['block_item_rule_code'] = $choice['blockItemRuleCode'] ?? null;
        $row['expected_sheet_version'] = $candidate['expectedSheetVersion'] ?? 0;

        return $row;
    }

    /**
     * JSON-объект строки удара.
     *
     * @param array<string, mixed> $row Строка удара.
     * @param string $key Колонка.
     *
     * @return array<string, mixed> Объект.
     */
    private function jsonRow(array $row, string $key): array
    {
        $raw = $row[$key] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return is_array($raw) ? $raw : [];
    }
}
