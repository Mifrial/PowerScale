<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

/**
 * Входные данные одной записи открытого удара.
 */
final class GameStrikeOpenRun
{
    public GameRecord $game;

    public GameBattleRecord $battle;

    public int $sessionId = 0;

    public int $expectedBattleVersion = 0;

    public string $kind = '';

    public string $lane = '';

    public string $subject = '';

    public array $choice = [];

    public array $snapshot = [];

    public array $action = [];

    public array $targets = [];

    public ?array $context = null;

    public ?int $closingProcessId = null;

    public string $attackerKind = '';

    public int $attackerId = 0;
}
