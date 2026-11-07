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
    private readonly GameNpcDocument $documents;

    /**
     * Собирает сценарий.
     *
     * @param GameNpcRepository $npcRepository Строки.
     * @param IGames $games Игра.
     * @param ICharacterSheetEngines $sheetEngines Сборка листа.
     * @param ISmartTableGateway $smartTableGateway Транзакции.
     *
     * @return void
     */
    public function __construct(
        private readonly GameNpcRepository $npcRepository,
        private readonly IGames $games,
        private readonly ICharacterSheetEngines $sheetEngines,
        private readonly ISmartTableGateway $smartTableGateway,
    ) {
        $this->documents = new GameNpcDocument();
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

        return $this->npcRepository->add($gameId, $report['name'], $this->documents->version($game, $report), $visibility);
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
        return $this->smartTableGateway->transaction(
            fn (): GameNpcResourceBackfillResult => $this->backfillFresh($record, $game, $expectedActualVersion),
        );
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
        $report = $this->changedReport($record, $game, $name, $choices, $sheet);
        if ($report['kind'] !== 'ok') {
            return ['record' => null, 'report' => $report];
        }

        $saved = $this->npcRepository->save(
            $record,
            $report['name'],
            $this->documents->version($game, $report, $this->documents->storedRevision($record)),
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
     * @param array<string, mixed>|null $choices Документ перевода.
     * @param string|null $name Имя перевода.
     * @param array<string, mixed>|null $sheet Сверка листа.
     *
     * @return array{record: GameNpcRecord|null, report: array<string, mixed>} Строка или conflicts.
     *
     * @throws GameConflictException Если CAS.
     * @throws GameInvalidException Если уже на ревизии, нет choices или нет ревизии.
     */
    public function translate(
        GameNpcRecord $record,
        GameRecord $game,
        int $expectedActualVersion,
        ?array $choices = null,
        ?string $name = null,
        ?array $sheet = null,
    ): array {
        $this->assertVersion($record, $expectedActualVersion);
        $sourceRevision = $this->documents->storedRevision($record);
        if ($sourceRevision === $game->getRulesRevision()) {
            throw new GameInvalidException('NPC sheet is already on the game revision');
        }

        $report = $choices === null || $choices === []
            ? $this->remapReport($record, $game, $sourceRevision)
            : $this->translatedReport($choices, $game, $name, $sheet, $record);
        if ($report['kind'] !== 'ok') {
            return ['record' => null, 'report' => $report];
        }

        $saved = $this->npcRepository->save(
            $record,
            $report['name'],
            $this->documents->version($game, $report),
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
     * Пересобирает ресурсы уже внутри транзакции.
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
    private function backfillFresh(
        GameNpcRecord $record,
        GameRecord $game,
        int $expectedActualVersion,
    ): GameNpcResourceBackfillResult {
        $fresh = $this->npcRepository->getById($record->getId());
        $this->assertVersion($fresh, $expectedActualVersion);
        $document = $fresh->getVersion();
        $previousSheet = $this->documents->sheetOf($fresh);
        $report = $this->rebuildFresh($fresh, $game, $previousSheet);
        $version = $this->documents->version($game, $report, $this->documents->storedRevision($fresh));
        if ($this->documents->sameDocument($document, $version)) {
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
    }

    /**
     * Пересобирает лист на ревизии самой строки.
     *
     * @param GameNpcRecord $fresh Свежая строка.
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $previousSheet Server-owned предыдущий лист.
     *
     * @return array<string, mixed> Отчёт.
     *
     * @throws GameInvalidException Если лист не собирается.
     */
    private function rebuildFresh(GameNpcRecord $fresh, GameRecord $game, array $previousSheet): array
    {
        $report = $this->runBuild(
            $this->documents->choicesOf($fresh),
            $game,
            null,
            $this->documents->storedRevision($fresh),
            $previousSheet,
        );
        if ($report['kind'] !== 'ok') {
            throw new GameInvalidException('NPC resource backfill was not built');
        }

        return $report;
    }

    /**
     * Собирает присланные изменения на ревизии строки.
     *
     * @param GameNpcRecord $record Строка.
     * @param GameRecord $game Игра.
     * @param string $name Имя.
     * @param array<string, mixed> $choices Документ.
     * @param array<string, mixed>|null $sheet Сверка.
     *
     * @return array<string, mixed> Отчёт.
     *
     * @throws GameInvalidException Если ревизии нет.
     */
    private function changedReport(
        GameNpcRecord $record,
        GameRecord $game,
        string $name,
        array $choices,
        ?array $sheet,
    ): array {
        $choices['name'] = $name;
        $report = $this->runBuild(
            $choices,
            $game,
            null,
            $this->documents->storedRevision($record),
            $this->documents->sheetOf($record),
        );

        return $this->documents->withSheetCheck($report, $sheet);
    }

    /**
     * Серверный remap листа на ревизию игры.
     *
     * @param GameNpcRecord $record Строка.
     * @param GameRecord $game Игра.
     * @param int $sourceRevision Ревизия листа.
     *
     * @return array<string, mixed> Отчёт.
     *
     * @throws GameInvalidException Если ревизии нет или лист битый.
     */
    private function remapReport(GameNpcRecord $record, GameRecord $game, int $sourceRevision): array
    {
        try {
            return $this->sheetEngines->remap(
                $this->documents->choicesOf($record),
                $game->getSpaceId(),
                $sourceRevision,
                $game->getRulesRevision(),
                $this->documents->sheetOf($record),
            );
        } catch (CharacterNotFoundException $exception) {
            throw new GameInvalidException('NPC revision was not found', $exception);
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('NPC sheet is invalid', $exception);
        }
    }

    /**
     * Сборка присланного перевода на ревизии игры.
     *
     * @param array<string, mixed> $choices Документ.
     * @param GameRecord $game Игра.
     * @param string|null $name Имя.
     * @param array<string, mixed>|null $sheet Сверка.
     * @param GameNpcRecord $record Строка.
     *
     * @return array<string, mixed> Отчёт.
     *
     * @throws GameInvalidException Если ревизии нет.
     */
    private function translatedReport(
        array $choices,
        GameRecord $game,
        ?string $name,
        ?array $sheet,
        GameNpcRecord $record,
    ): array {
        if (is_string($name) && $name !== '') {
            $choices['name'] = $name;
        }

        $report = $this->runBuild(
            $choices,
            $game,
            null,
            $game->getRulesRevision(),
            $this->documents->sheetOf($record),
        );

        return $this->documents->withSheetCheck($report, $sheet);
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














}
