<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Разбор выбора удара. Готовый урон не принимается.
 */
final class GameStrikeBody
{
    private readonly GameStrikeAttack $attackBody;

    private readonly GameStrikeBodyDefense $defenseBody;

    private readonly GameStrikeWideAttack $wideAttackBody;

    private readonly GameStrikeWideDefense $wideDefenseBody;

    /**
     * Создаёт разбор тела удара.
     *
     * @return void
     */
    public function __construct()
    {
        $fields = new GameStrikeBodyFields();
        $conditions = new GameStrikeConditions();
        $reaction = new GameStrikeReaction($fields);
        $this->attackBody = new GameStrikeAttack($fields, $conditions);
        $this->defenseBody = new GameStrikeBodyDefense($reaction);
        $this->wideAttackBody = new GameStrikeWideAttack($fields, new GameStrikeTargets($fields, $conditions));
        $this->wideDefenseBody = new GameStrikeWideDefense($fields, $reaction);
    }

    /**
     * Выбор атаки.
     *
     * @param array<string, mixed> $attack Тело.
     *
     * @return array<string, mixed> Выбор.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function attack(array $attack): array
    {
        return $this->attackBody->parse($attack);
    }

    /**
     * Выбор защиты.
     *
     * @param array<string, mixed> $defense Тело.
     *
     * @return array<string, mixed> Выбор.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function defense(array $defense): array
    {
        return $this->defenseBody->parse($defense);
    }

    /**
     * Выбор атаки по нескольким целям.
     *
     * @param array<string, mixed> $attack Тело.
     *
     * @return array<string, mixed> Выбор.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function wideAttack(array $attack): array
    {
        return $this->wideAttackBody->parse($attack);
    }

    /**
     * Выбор защиты по нескольким целям.
     *
     * @param array<string, mixed> $defense Тело.
     *
     * @return list<array<string, mixed>> Защиты.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function wideDefense(array $defense): array
    {
        return $this->wideDefenseBody->parse($defense);
    }
}
