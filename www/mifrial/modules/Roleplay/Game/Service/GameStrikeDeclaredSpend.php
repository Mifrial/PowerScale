<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;

/**
 * Списание объявленных реакций в начале разрешения удара.
 */
final class GameStrikeDeclaredSpend
{
    /**
     * Создаёт списание.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameActionRepository $actions Действия и цели.
     * @param GameStrikeSheets $sheets Листы.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     * @param GameStrikeSpend $spend Стоимость.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameActionRepository $actions,
        private readonly GameStrikeSheets $sheets,
        private readonly GameStrikeSheetWrites $sheetWrites,
        private readonly GameStrikeSpend $spend,
    ) {
    }

    /**
     * Списывает поддержки и прикрытия всех строк.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param list<array<string, mixed>> $rows Строки удара.
     *
     * @return list<array<string, mixed>> Строки после списания.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка битая.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    public function spend(GameRecord $game, CharacterRuleSlice $slice, array $rows): array
    {
        $turn = $this->spend->getTurnResourceCode($slice);
        if ($turn === null) {
            return $rows;
        }

        foreach ($rows as $index => $row) {
            $spent = $this->row($game, $slice, $row, $turn);
            $rows[$index] = $spent['row'];
            if ($spent['changed']) {
                $this->answer($spent['row']);
            }
        }

        return $rows;
    }

    /**
     * Одна строка удара: обе колонки поддержек.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $row Строка удара.
     * @param string $turn Код ресурса хода.
     *
     * @return array{row: array<string, mixed>, changed: bool} Строка и признак.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка битая.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    private function row(GameRecord $game, CharacterRuleSlice $slice, array $row, string $turn): array
    {
        $changed = false;
        foreach (['supporters', 'coverers'] as $key) {
            $column = $this->column($game, $slice, $row, $key, $turn);
            $row = $column['row'];
            $changed = $changed || $column['changed'];
        }

        return ['row' => $row, 'changed' => $changed];
    }

    /**
     * Одна колонка поддержек или прикрытий.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $row Строка удара.
     * @param string $key Колонка.
     * @param string $turn Код ресурса хода.
     *
     * @return array{row: array<string, mixed>, changed: bool} Строка и признак.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка битая.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    private function column(
        GameRecord $game,
        CharacterRuleSlice $slice,
        array $row,
        string $key,
        string $turn,
    ): array {
        $kept = [];
        $changed = false;
        foreach ($this->jsonRows($row, $key) as $entry) {
            $result = $this->entry($game, $slice, $entry, $key, $turn);
            $changed = $changed || $result['changed'];
            if ($result['keep']) {
                $kept[] = $result['entry'];
            }
        }

        if (!$changed) {
            return ['row' => $row, 'changed' => false];
        }

        $row[$key] = $kept;

        return ['row' => $row, 'changed' => true];
    }

    /**
     * Одна запись поддержки: списание или сохранение как есть.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $entry Запись.
     * @param string $key Колонка.
     * @param string $turn Код ресурса хода.
     *
     * @return array{entry: array<string, mixed>, keep: bool, changed: bool} Итог.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка битая.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    private function entry(
        GameRecord $game,
        CharacterRuleSlice $slice,
        array $entry,
        string $key,
        string $turn,
    ): array {
        if (($entry['spent'] ?? false) === true) {
            return ['entry' => $entry, 'keep' => true, 'changed' => false];
        }

        $actor = $this->actor($entry);
        if ($actor === null) {
            return ['entry' => $entry, 'keep' => true, 'changed' => false];
        }

        return $this->spendEntry($game, $slice, $entry, $key, $turn, $actor);
    }

    /**
     * Списывает ресурс одной записи поддержки.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $entry Запись.
     * @param string $key Колонка.
     * @param string $turn Код ресурса хода.
     * @param array{type: string, id: int, expected: int} $actor Актор записи.
     *
     * @return array{entry: array<string, mixed>, keep: bool, changed: bool} Итог.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка битая.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    private function spendEntry(
        GameRecord $game,
        CharacterRuleSlice $slice,
        array $entry,
        string $key,
        string $turn,
        array $actor,
    ): array {
        $entry['spent'] = true;
        $amount = $this->amount($slice, $entry, $key);
        if ($amount <= 0) {
            return ['entry' => $entry, 'keep' => true, 'changed' => true];
        }

        $spends = [new ResourceSpend($turn, new ScalarResourceValue($amount))];
        $sheet = $this->sheets->getTargetSheet($game, ['type' => $actor['type'], 'id' => $actor['id']]);
        if (!$this->sufficient($game, $sheet, $spends)) {
            return ['entry' => $entry, 'keep' => false, 'changed' => true];
        }

        $entry['expectedSheetVersion'] = $this->sheetWrites->writeResources(
            $game,
            $actor['type'],
            $actor['id'],
            $actor['expected'],
            $spends,
        );

        return ['entry' => $entry, 'keep' => true, 'changed' => true];
    }

    /**
     * Проверенный актор записи.
     *
     * @param array<string, mixed> $entry Запись.
     *
     * @return array{type: string, id: int, expected: int}|null Пара или null.
     */
    private function actor(array $entry): ?array
    {
        $expected = $entry['expectedSheetVersion'] ?? null;
        $actor = $entry['actor'] ?? null;
        if (!is_int($expected) || !is_array($actor)) {
            return null;
        }

        $type = $actor['type'] ?? null;
        $id = $actor['id'] ?? null;
        if (!is_string($type) || !is_int($id)) {
            return null;
        }

        return ['type' => $type, 'id' => $id, 'expected' => $expected];
    }

    /**
     * Стоимость одной записи.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $entry Запись.
     * @param string $key Колонка.
     *
     * @return int Стоимость.
     */
    private function amount(CharacterRuleSlice $slice, array $entry, string $key): int
    {
        if ($key === 'supporters') {
            $ruleCode = is_string($entry['ruleCode'] ?? null) ? $entry['ruleCode'] : '';

            return $this->spend->getActionAp($slice, $ruleCode);
        }

        return is_int($entry['cost'] ?? null) ? $entry['cost'] : 0;
    }

    /**
     * Хватает ли ресурсов листа на списание.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $sheet Лист.
     * @param list<ResourceSpend> $spends Списания.
     *
     * @return bool true, если хватает.
     */
    private function sufficient(GameRecord $game, array $sheet, array $spends): bool
    {
        return $this->rules->spends->hasSufficientResources(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $sheet,
            $spends,
        );
    }

    /**
     * Пишет списанные колонки в строку удара.
     *
     * @param array<string, mixed> $row Строка удара.
     *
     * @return void
     */
    private function answer(array $row): void
    {
        $strikeId = $row['id'] ?? null;
        if (!is_int($strikeId)) {
            return;
        }

        $this->actions->answerStrike($strikeId, [
            'supporters' => $row['supporters'],
            'coverers' => $row['coverers'],
        ]);
    }

    /**
     * Список JSON-записей строки удара.
     *
     * @param array<string, mixed> $row Строка удара.
     * @param string $key Колонка.
     *
     * @return list<array<string, mixed>> Записи.
     */
    private function jsonRows(array $row, string $key): array
    {
        $raw = $row[$key] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter($raw, static fn (mixed $entry): bool => is_array($entry)));
    }
}
