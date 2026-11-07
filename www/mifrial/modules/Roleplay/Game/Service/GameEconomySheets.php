<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Game\Dto\GameShopPositionRecord;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Запись листов и магазина со сверкой ожидаемых версий.
 */
final class GameEconomySheets
{
    /**
     * @var array<int, int>
     */
    private array $characterVersions = [];

    /**
     * @var array<int, int>
     */
    private array $npcVersions = [];

    /**
     * @var array<string, int>
     */
    private array $shopVersions = [];

    /**
     * Создаёт запись.
     *
     * @param GameEconomyPorts $ports Порты.
     * @param GameEconomyConflict $conflicts Конфликты.
     *
     * @return void
     */
    public function __construct(
        private readonly GameEconomyPorts $ports,
        private readonly GameEconomyConflict $conflicts,
    ) {
    }

    /**
     * Читает три списка версий.
     *
     * @param array $expectedVersions Тело.
     *
     * @return void
     *
     * @throws GameInvalidException Если форма.
     */
    public function readExpected(array $expectedVersions): void
    {
        $this->characterVersions = $this->versionMap($expectedVersions['characters'] ?? null, 'characterId');
        $this->npcVersions = $this->versionMap($expectedVersions['npcs'] ?? null, 'npcId');
        $this->shopVersions = $this->versionMap($expectedVersions['positions'] ?? null, 'ruleCode');
    }

    /**
     * Пишет листы и магазин. Версии сверяются здесь.
     *
     * @param GameEconomyLedger $ledger Копилка прохода.
     *
     * @return array<string, mixed> Итог версий.
     *
     * @throws GameEconomyConflictException Если версия другая.
     * @throws GameInvalidException Если версия не передана.
     */
    public function write(GameEconomyLedger $ledger): array
    {
        $characters = [];
        foreach ($ledger->characters() as $characterId => $holder) {
            $characters[] = $this->writeCharacter($characterId, $holder);
        }

        $npcs = [];
        foreach ($ledger->npcSheets() as $npcId => $holder) {
            $npcs[] = $this->writeNpc($npcId, $holder);
        }

        $positions = [];
        foreach ($ledger->touchedShop() as $ruleCode => $slot) {
            $positions[] = $this->writeShop($ruleCode, $slot);
        }

        $this->assertVersionsUsed();

        return [
            'characters' => $characters,
            'npcs' => $npcs,
            'positions' => $positions,
        ];
    }

    /**
     * Пара id/код → версия.
     *
     * @param mixed $rows Список.
     * @param string $idKey Поле идентификатора.
     *
     * @return array<int|string, int> Карта.
     *
     * @throws GameInvalidException Если пара битая.
     */
    private function versionMap(mixed $rows, string $idKey): array
    {
        if (!is_array($rows)) {
            throw new GameInvalidException('Economy versions are invalid');
        }

        $map = [];
        foreach ($rows as $row) {
            [$id, $version] = $this->versionEntry($row, $idKey);
            $map[$id] = $version;
        }

        return $map;
    }

    /**
     * Одна пара id/код и версия.
     *
     * @param mixed $row Строка.
     * @param string $idKey Поле идентификатора.
     *
     * @return array{0: int|string, 1: int} Пара и версия.
     *
     * @throws GameInvalidException Если пара битая.
     */
    private function versionEntry(mixed $row, string $idKey): array
    {
        if (!is_array($row)) {
            throw new GameInvalidException('Economy version row is invalid');
        }

        $version = $row['actualVersion'] ?? $row['version'] ?? null;
        $id = $row[$idKey] ?? null;
        if ((!is_int($id) && !is_string($id)) || !is_int($version)) {
            throw new GameInvalidException('Economy version row is invalid');
        }

        return [$id, $version];
    }

    /**
     * Персонаж через apply.
     *
     * @param int $characterId Персонаж.
     * @param array{money: int, lines: array<string, int>, baseMoney: int, baseLines: array<string, int>, version: int} $holder Итог.
     *
     * @return array{characterId: int, actualVersion: int} Версия.
     *
     * @throws GameEconomyConflictException Если версия не названа.
     * @throws GameInvalidException Если версия не передана.
     */
    private function writeCharacter(int $characterId, array $holder): array
    {
        $expected = $this->pullVersion($this->characterVersions, $characterId);
        $operations = $this->operationsOf($holder);
        if ($operations === []) {
            $this->assertCharacterNoOp($characterId, $expected, $holder['version']);

            return ['characterId' => $characterId, 'actualVersion' => $expected];
        }

        try {
            $record = $this->ports->mutations->apply($characterId, $expected, $operations);
        } catch (CharacterConflictException $exception) {
            throw $this->conflicts->character($characterId, $exception);
        }

        return ['characterId' => $characterId, 'actualVersion' => $record->getActualVersion()];
    }

    /**
     * Версия персонажа без изменений.
     *
     * @param int $characterId Персонаж.
     * @param int $expected Ожидаемая версия.
     * @param int $actual Переданная версия.
     *
     * @return void
     *
     * @throws GameEconomyConflictException Если версия устарела.
     * @throws GameNotFoundException Если строки нет.
     */
    private function assertCharacterNoOp(int $characterId, int $expected, int $actual): void
    {
        if ($expected === $actual) {
            return;
        }

        $record = $this->ports->characters->get($characterId);
        throw $this->conflicts->character(
            $characterId,
            new CharacterConflictException(
                $record->getActualVersion(),
                currentSheet: [
                    'choices' => $record->getChoices(),
                    'sheet' => $record->getSheet(),
                ],
            ),
        );
    }

    /**
     * NPC через applyToDocument.
     *
     * @param int $npcId NPC.
     * @param array{money: int, lines: array<string, int>, baseMoney: int, baseLines: array<string, int>, version: int, document: array<string, mixed>} $holder Итог.
     *
     * @return array{npcId: int, actualVersion: int} Версия.
     *
     * @throws GameEconomyConflictException Если версия не названа или другая.
     * @throws GameInvalidException Если лист без ревизии.
     */
    private function writeNpc(int $npcId, array $holder): array
    {
        $expected = $this->pullVersion($this->npcVersions, $npcId);
        $operations = $this->operationsOf($holder);
        if ($operations === []) {
            $this->assertNpcNoOpVersion($npcId, $expected, $holder['version']);

            return ['npcId' => $npcId, 'actualVersion' => $expected];
        }

        try {
            $saved = $this->ports->npcs->replaceVersion($npcId, $this->patchedDocument($holder, $operations), $expected);
        } catch (GameEconomyConflictException $exception) {
            $this->conflicts->npc($npcId, $exception);
        }

        return ['npcId' => $npcId, 'actualVersion' => $saved->getActualVersion()];
    }

    /**
     * Документ NPC после мутации.
     *
     * @param array<mixed> $holder Держатель.
     * @param list<array<string, mixed>> $operations Операции.
     *
     * @return array<string, mixed> Документ.
     *
     * @throws GameInvalidException Если лист без ревизии.
     */
    private function patchedDocument(array $holder, array $operations): array
    {
        $parts = $this->npcDocumentParts($holder['document']);
        $patched = $this->ports->mutations->applyToDocument(
            $parts['spaceId'],
            $parts['revision'],
            $parts['choices'],
            $parts['sheet'],
            $operations,
        );
        $document = $holder['document'];
        $document['choices'] = $patched['choices'];
        $document['sheet'] = $patched['sheet'];

        return $document;
    }

    /**
     * Проверяет версию NPC для операции без изменений.
     *
     * @param int $npcId NPC.
     * @param int $expected Ожидаемая версия.
     * @param int $actual Переданная версия.
     *
     * @return void
     *
     * @throws GameEconomyConflictException Если версия устарела.
     * @throws GameNotFoundException Если NPC исчез.
     */
    private function assertNpcNoOpVersion(int $npcId, int $expected, int $actual): void
    {
        if ($expected === $actual) {
            return;
        }

        $npc = $this->ports->npcs->getById($npcId);
        $this->conflicts->npc(
            $npcId,
            new GameEconomyConflictException($npc->getActualVersion()),
        );
    }

    /**
     * Проверяет и разбирает document NPC для mutation.
     *
     * @param array<string, mixed> $document Документ NPC.
     *
     * @return array{choices: array<mixed>, sheet: array<mixed>, spaceId: int, revision: int} Части документа.
     *
     * @throws GameInvalidException Если документ без ревизии.
     */
    private function npcDocumentParts(array $document): array
    {
        $choices = $document['choices'] ?? null;
        $sheet = $document['sheet'] ?? null;
        $spaceId = $document['spaceId'] ?? null;
        $revision = $document['rulesRevision'] ?? null;
        if (!is_array($choices) || !is_array($sheet) || !is_int($spaceId) || !is_int($revision)) {
            throw new GameInvalidException('NPC sheet is invalid');
        }

        return [
            'choices' => $choices,
            'sheet' => $sheet,
            'spaceId' => $spaceId,
            'revision' => $revision,
        ];
    }

    /**
     * Позиция магазина.
     *
     * @param string $ruleCode Код.
     * @param array{row: GameShopPositionRecord, quantity: int} $slot Итог.
     *
     * @return array{ruleCode: string, version: int} Версия.
     *
     * @throws GameEconomyConflictException Если версия другая.
     * @throws GameInvalidException Если версия не передана.
     */
    private function writeShop(string $ruleCode, array $slot): array
    {
        $expected = $this->pullVersion($this->shopVersions, $ruleCode);
        if ($expected !== $slot['row']->getVersion()) {
            throw new GameEconomyConflictException($slot['row']->getVersion());
        }

        if ($slot['quantity'] === $slot['row']->getQuantity()) {
            return ['ruleCode' => $ruleCode, 'version' => $expected];
        }

        $this->ports->shopPositions->save(
            $slot['row'],
            $slot['row']->getBuyPrice(),
            $slot['row']->getSellPrice(),
            $slot['quantity'],
        );

        return ['ruleCode' => $ruleCode, 'version' => $expected + 1];
    }

    /**
     * Забирает ожидаемую версию. Повторное отсутствие — лишняя не проверяется здесь.
     *
     * @param array<int|string, int> $map Карта.
     * @param int|string $id Ключ.
     *
     * @return int Версия.
     *
     * @throws GameInvalidException Если ключа нет.
     */
    private function pullVersion(array &$map, int|string $id): int
    {
        if (!array_key_exists($id, $map)) {
            throw new GameInvalidException('Economy expected version is missing');
        }

        $version = $map[$id];
        unset($map[$id]);

        return $version;
    }

    /**
     * Лишние ожидаемые версии.
     *
     * @return void
     *
     * @throws GameInvalidException Если остались.
     */
    private function assertVersionsUsed(): void
    {
        if ($this->characterVersions !== [] || $this->npcVersions !== [] || $this->shopVersions !== []) {
            throw new GameInvalidException('Economy expected version is unused');
        }
    }

    /**
     * Абсолютные операции порта.
     *
     * @param array{money: int, lines: array<string, int>, baseMoney: int, baseLines: array<string, int>} $holder Итог.
     *
     * @return list<array<string, mixed>> Операции.
     */
    private function operationsOf(array $holder): array
    {
        $operations = [];
        if ($holder['money'] !== $holder['baseMoney']) {
            $operations[] = ['kind' => 'setMoney', 'amount' => $holder['money']];
        }

        foreach ($holder['lines'] as $ruleCode => $quantity) {
            $before = $holder['baseLines'][$ruleCode] ?? null;
            if ($before === $quantity) {
                continue;
            }

            $operations[] = [
                'kind' => 'putInventoryQuantity',
                'ruleCode' => $ruleCode,
                'quantity' => $quantity,
            ];
        }

        return $operations;
    }
}
