<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * CAS-запись цели и кастера в одной команде.
 */
final class GameSpellCastWrite
{
    /**
     * Создаёт запись каста.
     *
     * @param GameStrikeSheetWrites $writes Запись листа.
     *
     * @return void
     */
    public function __construct(private readonly GameStrikeSheetWrites $writes)
    {
    }

    /**
     * Пишет только операции листа без трат.
     *
     * @param GameRecord $game Игра.
     * @param string $kind character или npc.
     * @param int $id Лист.
     * @param int $expected Версия листа.
     * @param list<array<string, mixed>> $operations Операции.
     *
     * @return int Версия после записи.
     *
     * @throws GameInvalidException Если операция отвергнута.
     * @throws \Mifrial\Roleplay\Game\Exception\GameBattleConflictException Если версия другая.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    public function sheetOps(GameRecord $game, string $kind, int $id, int $expected, array $operations): int
    {
        if ($operations === []) {
            return $expected;
        }

        return $this->writes->write($game, $kind, $id, $expected, $operations);
    }

    /**
     * Пишет операции цели и траты кастера.
     *
     * @param GameRecord $game Игра.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param array<string, mixed> $body Тело команды.
     * @param array{
     *     spellSr: int|null,
     *     spellDamage: mixed,
     *     injury: mixed,
     *     operations: list<array<string, mixed>>,
     *     consequence: array<string, mixed>
     * } $damage Урон и операции.
     * @param int|null $expectedCasterVersion Версия листа кастера или null.
     * @param list<array<string, mixed>> $casterOperations Операции кастера.
     * @param list<\Mifrial\Roleplay\Character\Dto\ResourceSpend>|null $spends Траты или null для трат подготовки.
     *
     * @return array{casterVersion: int, targetVersion: int|null} Версии.
     *
     * @throws GameInvalidException Если версии цели нет.
     */
    public function write(
        GameRecord $game,
        GameSpellCastPrepared $prepared,
        array $body,
        array $damage,
        ?int $expectedCasterVersion = null,
        array $casterOperations = [],
        ?array $spends = null,
    ): array {
        $spends ??= $prepared->spends;
        $casterExpected = $expectedCasterVersion ?? $prepared->casterVersion;
        $target = $prepared->target;
        $targetVersion = null;
        if ($target !== null) {
            $expectedTarget = $body['expectedTargetVersion'] ?? null;
            if (!is_int($expectedTarget)) {
                throw new GameInvalidException('Game spell target version is missing');
            }

            if ($target['type'] === $prepared->casterKind && $target['id'] === $prepared->casterId) {
                $version = $this->writes->write(
                    $game,
                    $prepared->casterKind,
                    $prepared->casterId,
                    $casterExpected,
                    [...$damage['operations'], ...$casterOperations],
                    null,
                    null,
                    $spends,
                );

                return ['casterVersion' => $version, 'targetVersion' => $version];
            }

            $targetVersion = $damage['operations'] === []
                ? $target['version']
                : $this->writes->write(
                    $game,
                    $target['type'],
                    $target['id'],
                    $expectedTarget,
                    $damage['operations'],
                );
        }

        $casterVersion = $casterOperations === []
            ? $this->writes->writeResources(
                $game,
                $prepared->casterKind,
                $prepared->casterId,
                $casterExpected,
                $spends,
            )
            : $this->writes->write(
                $game,
                $prepared->casterKind,
                $prepared->casterId,
                $casterExpected,
                $casterOperations,
                null,
                null,
                $spends,
            );

        return ['casterVersion' => $casterVersion, 'targetVersion' => $targetVersion];
    }
}
