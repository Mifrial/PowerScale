<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceNotFoundException;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

/**
 * Мир и ревизия для строки игры. Выключенный мир не читает состав.
 */
final class GameWorldGate
{
    /**
     * Создаёт сверку мира.
     *
     * @param IRuleSpaces $ruleSpaces Оператор миров.
     *
     * @return void
     */
    public function __construct(
        private readonly IRuleSpaces $ruleSpaces,
    ) {
    }

    /**
     * Код включённого мира с существующей ревизией.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Номер.
     *
     * @return string Код мира.
     *
     * @throws GameNotFoundException Если мира или ревизии нет.
     * @throws GameInvalidException Если мир выключен или номер битый.
     */
    public function requireCode(int $spaceId, int $rulesRevision): string
    {
        $world = $this->world($spaceId);
        if (!$world->isActive()) {
            throw new GameInvalidException('Rule space is inactive');
        }

        $this->requireRevision($spaceId, $rulesRevision);

        return $world->getCode();
    }

    /**
     * Сверяет клиентский код с миром. null не сверяет.
     *
     * @param int $spaceId Мир.
     * @param string|null $spaceCode Код клиента.
     *
     * @return void
     *
     * @throws GameNotFoundException Если мира нет.
     * @throws GameInvalidException Если код не совпал.
     */
    public function assertClientCode(int $spaceId, ?string $spaceCode): void
    {
        if ($spaceCode === null) {
            return;
        }

        $world = $this->world($spaceId);
        if (trim($spaceCode) !== $world->getCode()) {
            throw new GameInvalidException('Game space code does not match');
        }
    }

    /**
     * Мир по id.
     *
     * @param int $spaceId Мир.
     *
     * @return RuleSpaceRecord Мир.
     *
     * @throws GameNotFoundException Если sidecar нет.
     * @throws GameInvalidException Если запись битая.
     */
    private function world(int $spaceId): RuleSpaceRecord
    {
        try {
            return $this->ruleSpaces->get($spaceId);
        } catch (RuleSpaceNotFoundException $exception) {
            throw new GameNotFoundException('Rule space was not found', $exception);
        } catch (RuleSpaceInvalidException $exception) {
            throw new GameInvalidException('Rule space is invalid', $exception);
        }
    }

    /**
     * Ревизия существует.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Номер.
     *
     * @return void
     *
     * @throws GameNotFoundException Если ревизии нет.
     * @throws GameInvalidException Если номер битый.
     */
    private function requireRevision(int $spaceId, int $rulesRevision): void
    {
        try {
            $this->ruleSpaces->getRevision($spaceId, $rulesRevision);
        } catch (RuleSpaceNotFoundException $exception) {
            throw new GameNotFoundException('Rule revision was not found', $exception);
        } catch (RuleSpaceInvalidException $exception) {
            throw new GameInvalidException('Rule revision is invalid', $exception);
        }
    }
}
