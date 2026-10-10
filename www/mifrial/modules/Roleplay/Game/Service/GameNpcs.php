<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheetEngines;
use Mifrial\Roleplay\Game\Dto\GameNpcRecord;
use Mifrial\Roleplay\Game\Dto\GameNpcResourceBackfillResult;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;

/**
 * Строка NPC: создание, сборка и перевод. Сессию не трогает.
 */
final class GameNpcs
{
    /**
     * Собирает сценарий.
     *
     * @param GameNpcRepository $npcRepository Строки.
     * @param IGames $games Игра.
     * @param ICharacterSheetEngines $sheetEngines Сборка листа.
     *
     * @return void
     */
    public function __construct(
        private readonly GameNpcRepository $npcRepository,
        private readonly IGames $games,
        private readonly ICharacterSheetEngines $sheetEngines,
        private readonly ISmartTableGateway $smartTableGateway,
    ) {
    }

    /**
     * Создаёт NPC на ревизии игры.
     *
     * @param int $gameId Игра.
     * @param string $name Имя.
     * @param array{scope: string, userIds: array<int, int>, sections: array<int, string>} $visibility Видимость.
     *
     * @return GameNpcRecord Строка.
     *
     * @throws GameInvalidException Если игра completed или лист не собрался.
     * @throws GameNotFoundException Если игры нет.
     */
    public function add(int $gameId, string $name, array $visibility): GameNpcRecord
    {
        $game = $this->openGame($gameId);
        $report = $this->runBuild($this->seedChoices($name), $game, null);
        if ($report['kind'] !== 'ok') {
            throw new GameInvalidException('NPC sheet was not built');
        }

        return $this->npcRepository->add($gameId, $report['name'], $this->version($game, $report), $visibility);
    }

    /**
     * Инициализирует или выправляет resource rows NPC через CAS.
     *
     * @param GameNpcRecord $record NPC.
     * @param GameRecord $game Игра.
     * @param int $expectedActualVersion Ожидаемая версия.
     *
     * @return GameNpcResourceBackfillResult Результат backfill.
     *
     * @throws GameConflictException Если версия устарела.
     * @throws GameInvalidException Если лист не собирается.
     * @throws GameNotFoundException Если строки или ревизии нет.
     */
    public function backfill(
        GameNpcRecord $record,
        GameRecord $game,
        int $expectedActualVersion,
    ): GameNpcResourceBackfillResult {
        return $this->smartTableGateway->transaction(function () use (
            $record,
            $game,
            $expectedActualVersion,
        ): GameNpcResourceBackfillResult {
            $fresh = $this->npcRepository->getById($record->getId());
            $this->assertVersion($fresh, $expectedActualVersion);
            $document = $fresh->getVersion();
            $choices = $this->choicesOf($fresh);
            $previousSheet = $this->sheetOf($fresh);
            $report = $this->runBuild(
                $choices,
                $game,
                null,
                $this->storedRevision($fresh),
                $previousSheet,
            );
            if ($report['kind'] !== 'ok') {
                throw new GameInvalidException('NPC resource backfill was not built');
            }

            $version = $this->version($game, $report, $this->storedRevision($fresh));
            if ($this->canonical($document) === $this->canonical($version)) {
                return new GameNpcResourceBackfillResult($fresh, 'noop');
            }

            $saved = $this->npcRepository->save(
                $fresh,
                $report['name'],
                $version,
                $fresh->getVisibility(),
            );
            $status = array_key_exists('resources', $previousSheet) ? 'changed' : 'initialized';

            return new GameNpcResourceBackfillResult($saved, $status);
        });
    }

    /**
     * Строка NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return GameNpcRecord Строка.
     *
     * @throws GameNotFoundException Если нет или чужая игра.
     * @throws GameInvalidException Если строка битая.
     */
    public function get(int $gameId, int $npcId): GameNpcRecord
    {
        $record = $this->npcRepository->getById($npcId);
        if ($record->getGameId() !== $gameId) {
            throw new GameNotFoundException();
        }

        return $record;
    }

    /**
     * Пишет лист сборки на той же ревизии.
     *
     * @param GameNpcRecord $record Строка.
     * @param GameRecord $game Игра.
     * @param string $name Имя.
     * @param array<string, mixed> $choices Документ.
     * @param array<string, mixed>|null $sheet Сверка.
     * @param array{scope: string, userIds: array<int, int>, sections: array<int, string>} $visibility Видимость.
     * @param int $expectedActualVersion CAS.
     *
     * @return array{record: GameNpcRecord|null, report: array<string, mixed>} Строка или conflicts.
     *
     * @throws GameConflictException Если CAS.
     * @throws GameInvalidException Если ревизии нет.
     */
    public function change(
        GameNpcRecord $record,
        GameRecord $game,
        string $name,
        array $choices,
        ?array $sheet,
        array $visibility,
        int $expectedActualVersion,
    ): array {
        $this->assertVersion($record, $expectedActualVersion);
        $choices['name'] = $name;
        $report = $this->runBuild(
            $choices,
            $game,
            null,
            $this->storedRevision($record),
            $this->sheetOf($record),
        );
        $report = $this->withSheetCheck($report, $sheet);
        if ($report['kind'] !== 'ok') {
            return ['record' => null, 'report' => $report];
        }

        $saved = $this->npcRepository->save(
            $record,
            $report['name'],
            $this->version($game, $report, $this->storedRevision($record)),
            $visibility,
        );

        return ['record' => $saved, 'report' => $report];
    }

    /**
     * Переводит лист на ревизию игры.
     *
     * @param GameNpcRecord $record Строка.
     * @param GameRecord $game Игра.
     * @param int $expectedActualVersion CAS.
     *
     * @return array{record: GameNpcRecord|null, report: array<string, mixed>} Строка или conflicts.
     *
     * @throws GameConflictException Если CAS.
     * @throws GameInvalidException Если уже на ревизии, нет choices или нет ревизии.
     */
    public function translate(GameNpcRecord $record, GameRecord $game, int $expectedActualVersion): array
    {
        $this->assertVersion($record, $expectedActualVersion);
        $sourceRevision = $this->storedRevision($record);
        if ($sourceRevision === $game->getRulesRevision()) {
            throw new GameInvalidException('NPC sheet is already on the game revision');
        }

        try {
            $report = $this->sheetEngines->remap(
                $this->choicesOf($record),
                $game->getSpaceId(),
                $sourceRevision,
                $game->getRulesRevision(),
                $this->sheetOf($record),
            );
        } catch (CharacterNotFoundException $exception) {
            throw new GameInvalidException('NPC revision was not found', $exception);
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('NPC sheet is invalid', $exception);
        }

        if ($report['kind'] !== 'ok') {
            return ['record' => null, 'report' => $report];
        }

        $saved = $this->npcRepository->save(
            $record,
            $report['name'],
            $this->version($game, $report),
            $record->getVisibility(),
        );

        return ['record' => $saved, 'report' => $report];
    }

    /**
     * Игра не completed.
     *
     * @param int $gameId Игра.
     *
     * @return GameRecord Строка.
     *
     * @throws GameInvalidException Если completed.
     * @throws GameNotFoundException Если нет.
     */
    public function openGame(int $gameId): GameRecord
    {
        $game = $this->games->get($gameId);
        if ($game->isCompleted()) {
            throw new GameInvalidException('Completed game is read-only');
        }

        return $game;
    }

    /**
     * Сборка. Ревизия по умолчанию — ревизия игры.
     *
     * @param array<string, mixed> $choices Документ.
     * @param GameRecord $game Игра.
     * @param array<string, mixed>|null $sheet Сверка.
     * @param int|null $revision Ревизия листа.
     * @param array<string, mixed>|null $previousSheet Server-owned previous sheet.
     *
     * @return array<string, mixed> Отчёт.
     *
     * @throws GameInvalidException Если ревизии нет.
     */
    private function runBuild(
        array $choices,
        GameRecord $game,
        ?array $sheet,
        ?int $revision = null,
        ?array $previousSheet = null,
    ): array {
        try {
            return $this->sheetEngines->build(
                $choices,
                $game->getSpaceId(),
                $revision ?? $game->getRulesRevision(),
                $sheet,
                $previousSheet,
            );
        } catch (CharacterNotFoundException $exception) {
            throw new GameInvalidException('NPC revision was not found', $exception);
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('NPC sheet is invalid', $exception);
        }
    }

    /**
     * CAS до сборки.
     *
     * @param GameNpcRecord $record Строка.
     * @param int $expectedActualVersion Ожидание.
     *
     * @return void
     *
     * @throws GameConflictException Если счётчик другой.
     */
    private function assertVersion(GameNpcRecord $record, int $expectedActualVersion): void
    {
        if ($record->getActualVersion() !== $expectedActualVersion) {
            throw new GameConflictException($record->getActualVersion(), 0);
        }
    }

    /**
     * Минимальный документ создания.
     *
     * @param string $name Имя.
     *
     * @return array<string, mixed> Choices.
     */
    private function seedChoices(string $name): array
    {
        return [
            'name' => $name,
            'raceCode' => '',
            'abilities' => [],
            'inventory' => [],
            'characteristicPurchases' => [],
            'customRules' => [],
            'limits' => [],
            'money' => 0,
            'active' => true,
        ];
    }

    /**
     * npc.version из отчёта сборки.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $report Отчёт.
     * @param int|null $revision Ревизия листа или ревизия игры.
     *
     * @return array<string, mixed> Лист.
     */
    private function version(GameRecord $game, array $report, ?int $revision = null): array
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
    private function storedRevision(GameNpcRecord $record): int
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
    private function withSheetCheck(array $report, ?array $sheet): array
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

    /**
     * Документ choices строки.
     *
     * @param GameNpcRecord $record Строка.
     *
     * @return array<string, mixed> Choices.
     *
     * @throws GameInvalidException Если ключа нет.
     */
    private function choicesOf(GameNpcRecord $record): array
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
    private function sheetOf(GameNpcRecord $record): array
    {
        $sheet = $record->getVersion()['sheet'] ?? null;
        if (!is_array($sheet) || array_is_list($sheet)) {
            throw new GameInvalidException('NPC sheet is invalid');
        }

        return $sheet;
    }
}
