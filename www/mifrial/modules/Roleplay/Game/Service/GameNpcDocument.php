<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameNpcRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Документ NPC из версии строки и сверка присланного снимка.
 */
final class GameNpcDocument
{
    /**
     * npc.version из отчёта сборки.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $report Отчёт.
     * @param int|null $revision Ревизия листа или ревизия игры.
     *
     * @return array<string, mixed> Лист.
     */
    public function version(GameRecord $game, array $report, ?int $revision = null): array
    {
        return [
            'choices' => $report['choices'],
            'sheet' => $report['sheet'],
            'spaceId' => $game->getSpaceId(),
            'spaceCode' => $game->getSpaceCode(),
            'rulesRevision' => $revision ?? $game->getRulesRevision(),
        ];
    }

    /**
     * Ревизия, уже лежащая в листе.
     *
     * @param GameNpcRecord $record Строка.
     *
     * @return int Номер.
     *
     * @throws GameInvalidException Если нет объекта choices или номера.
     */
    public function storedRevision(GameNpcRecord $record): int
    {
        $this->choicesOf($record);
        $revision = $record->getVersion()['rulesRevision'] ?? null;
        if (!is_int($revision)) {
            throw new GameInvalidException('NPC sheet has no revision');
        }

        return $revision;
    }

    /**
     * Сверяет присланный снимок с собранным. Порядок ключей JSON не важен.
     *
     * @param array<string, mixed> $report Отчёт сборки.
     * @param array<string, mixed>|null $sheet Присланный снимок.
     *
     * @return array<string, mixed> Отчёт, при расхождении conflicts.
     */
    public function withSheetCheck(array $report, ?array $sheet): array
    {
        $built = $report['sheet'] ?? null;
        if (!is_array($sheet) || !is_array($built) || $this->canonical($sheet) === $this->canonical($built)) {
            return $report;
        }

        $problems = is_array($report['problems'] ?? null) ? $report['problems'] : [];
        $problems[] = [
            'code' => 'CHARACTER_SHEET',
            'message' => 'Expected sheet does not match the server sheet',
            'stage' => 'derived',
            'path' => 'expectedSheet',
        ];
        $report['problems'] = $problems;
        $report['kind'] = 'conflicts';

        return $report;
    }

    /**
     * Документ choices строки.
     *
     * @param GameNpcRecord $record Строка.
     *
     * @return array<string, mixed> Choices.
     *
     * @throws GameInvalidException Если ключа нет.
     */
    public function choicesOf(GameNpcRecord $record): array
    {
        $choices = $record->getVersion()['choices'] ?? null;
        if (!is_array($choices) || array_is_list($choices)) {
            throw new GameInvalidException('NPC sheet has no choices');
        }

        return $choices;
    }

    /**
     * Возвращает принадлежащий серверу sheet NPC.
     *
     * @param GameNpcRecord $record NPC row.
     *
     * @return array<string, mixed> Stored sheet.
     *
     * @throws GameInvalidException If the version has no sheet.
     */
    public function sheetOf(GameNpcRecord $record): array
    {
        $sheet = $record->getVersion()['sheet'] ?? null;
        if (!is_array($sheet) || array_is_list($sheet)) {
            throw new GameInvalidException('NPC sheet is invalid');
        }

        return $sheet;
    }

    /**
     * Документы совпадают без учёта порядка ключей.
     *
     * @param array<mixed> $left Документ.
     * @param array<mixed> $right Документ.
     *
     * @return bool true, если совпадают.
     */
    public function sameDocument(array $left, array $right): bool
    {
        return $this->canonical($left) === $this->canonical($right);
    }

    /**
     * Стабильный JSON объекта.
     *
     * @param array<mixed> $value Документ.
     *
     * @return string Текст.
     */
    private function canonical(array $value): string
    {
        $this->sortKeys($value);
        $encoded = json_encode($value);

        return is_string($encoded) ? $encoded : '';
    }

    /**
     * Сортирует ключи объектов рекурсивно.
     *
     * @param array<mixed> $value Документ.
     *
     * @return void
     */
    private function sortKeys(array &$value): void
    {
        if (!array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortKeys($item);
            }
        }
    }
}
