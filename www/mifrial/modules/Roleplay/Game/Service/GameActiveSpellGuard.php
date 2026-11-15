<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameActiveSpellRepository;

/**
 * Занятость источника поддержания у кастера в бою.
 */
final class GameActiveSpellGuard
{
    /**
     * Создаёт проверку.
     *
     * @param GameActiveSpellRepository $spells Строки поддержания.
     *
     * @return void
     */
    public function __construct(private readonly GameActiveSpellRepository $spells)
    {
    }

    /**
     * Источник свободен или отказ.
     *
     * @param int $battleId Бой.
     * @param string $casterKind character или npc.
     * @param int $casterId Id кастера.
     * @param string $sourceKey Ключ источника.
     *
     * @return void
     *
     * @throws GameInvalidException Если источник уже держит заклинание.
     */
    public function assertSourceFree(int $battleId, string $casterKind, int $casterId, string $sourceKey): void
    {
        if (!$this->isSourceFree($battleId, $casterKind, $casterId, $sourceKey)) {
            throw new GameInvalidException('Game spell power source is busy');
        }
    }

    /**
     * Кастер не превысил предел активных волшебств пути.
     *
     * @param int $battleId Бой.
     * @param string $casterKind character или npc.
     * @param int $casterId Id кастера.
     * @param int|null $limit Предел пути; null — предела нет.
     *
     * @return void
     *
     * @throws GameInvalidException Если предел уже выбран.
     */
    public function assertCasterLimit(int $battleId, string $casterKind, int $casterId, ?int $limit): void
    {
        if (!$this->isCasterLimitReached($battleId, $casterKind, $casterId, $limit)) {
            return;
        }

        throw new GameInvalidException('Game spell active limit is reached');
    }

    /**
     * Предел активных волшебств выбран.
     *
     * @param int $battleId Бой.
     * @param string $casterKind character или npc.
     * @param int $casterId Id кастера.
     * @param int|null $limit Предел пути; null — предела нет.
     *
     * @return bool true, если новое волшебство сверх предела.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function isCasterLimitReached(int $battleId, string $casterKind, int $casterId, ?int $limit): bool
    {
        if ($limit === null || $limit <= 0) {
            return false;
        }

        return $this->spells->countForCaster($battleId, $casterKind, $casterId) >= $limit;
    }

    /**
     * Источник свободен.
     *
     * @param int $battleId Бой.
     * @param string $casterKind character или npc.
     * @param int $casterId Id кастера.
     * @param string $sourceKey Ключ источника.
     *
     * @return bool true, если заклинания на источнике нет.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function isSourceFree(int $battleId, string $casterKind, int $casterId, string $sourceKey): bool
    {
        return $this->spells->findForSource($battleId, $casterKind, $casterId, $sourceKey) === null;
    }
}
