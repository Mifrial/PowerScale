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
            $changed = false;
            foreach (['supporters', 'coverers'] as $key) {
                $entries = $this->jsonRows($row, $key);
                $kept = [];
                foreach ($entries as $entry) {
                    if (($entry['spent'] ?? false) === true) {
                        $kept[] = $entry;
                        continue;
                    }

                    $expected = $entry['expectedSheetVersion'] ?? null;
                    $actor = $entry['actor'] ?? null;
                    if (
                        !is_int($expected)
                        || !is_array($actor)
                        || !is_string($actor['type'] ?? null)
                        || !is_int($actor['id'] ?? null)
                    ) {
                        $kept[] = $entry;
                        continue;
                    }

                    $changed = true;
                    $entry['spent'] = true;
                    $ruleCode = is_string($entry['ruleCode'] ?? null) ? $entry['ruleCode'] : '';
                    $amount = $key === 'supporters'
                        ? $this->spend->getActionAp($slice, $ruleCode)
                        : (is_int($entry['cost'] ?? null) ? $entry['cost'] : 0);
                    if ($amount <= 0) {
                        $kept[] = $entry;
                        continue;
                    }

                    $spends = [new ResourceSpend($turn, new ScalarResourceValue($amount))];
                    $sheet = $this->sheets->getTargetSheet($game, $actor);
                    if (
                        !$this->rules->spends->hasSufficientResources(
                            $game->getSpaceId(),
                            $game->getRulesRevision(),
                            $sheet,
                            $spends,
                        )
                    ) {
                        continue;
                    }

                    $entry['expectedSheetVersion'] = $this->sheetWrites->writeResources(
                        $game,
                        $actor['type'],
                        $actor['id'],
                        $expected,
                        $spends,
                    );
                    $kept[] = $entry;
                }

                if ($changed) {
                    $row[$key] = $kept;
                }
            }

            $rows[$index] = $row;
            if (!$changed) {
                continue;
            }

            $strikeId = $row['id'] ?? null;
            if (is_int($strikeId)) {
                $this->actions->answerStrike($strikeId, [
                    'supporters' => $row['supporters'],
                    'coverers' => $row['coverers'],
                ]);
            }
        }

        return $rows;
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
