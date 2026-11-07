<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Mechanic\Dto\MechanicBinding;
use Mifrial\Roleplay\Mechanic\Dto\ResolveActiveOptions;
use Mifrial\Roleplay\Mechanic\Exception\MechanicInvalidException;
use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;

/**
 * Разрешение обработчика привязанной механики.
 */
final class GameMechanicResolver
{
    /**
     * Создаёт резолв.
     *
     * @param IMechanics $mechanics Каталог.
     * @param IMechanicEngine $engine Резолв.
     *
     * @return void
     */
    public function __construct(
        private readonly IMechanics $mechanics,
        private readonly IMechanicEngine $engine,
    ) {
    }

    /**
     * Обработчик механики по коду правила.
     *
     * @param string $ruleCode Код правила.
     * @param int $mechanicId Механика.
     * @param string $message Текст отказа.
     *
     * @return IMechanicHandler Обработчик.
     *
     * @throws GameInvalidException Если механика не разрешается.
     */
    public function handler(string $ruleCode, int $mechanicId, string $message): IMechanicHandler
    {
        try {
            $resolved = $this->engine->resolveActive(
                [new MechanicBinding($ruleCode, $mechanicId, null)],
                $this->mechanics->getList(),
                new ResolveActiveOptions(),
            );
        } catch (MechanicInvalidException $exception) {
            throw new GameInvalidException($message, $exception);
        }

        return $resolved[0]->getHandler();
    }
}
