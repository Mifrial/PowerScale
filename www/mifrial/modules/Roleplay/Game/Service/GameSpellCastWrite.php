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
     *
     * @return array{casterVersion: int, targetVersion: int|null} Версии.
     *
     * @throws GameInvalidException Если версии цели нет.
     */
    public function write(GameRecord $game, GameSpellCastPrepared $prepared, array $body, array $damage): array
    {
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
                    $prepared->casterVersion,
                    $damage['operations'],
                    null,
                    null,
                    $prepared->spends,
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

        $casterVersion = $this->writes->writeResources(
            $game,
            $prepared->casterKind,
            $prepared->casterId,
            $prepared->casterVersion,
            $prepared->spends,
        );

        return ['casterVersion' => $casterVersion, 'targetVersion' => $targetVersion];
    }
}
