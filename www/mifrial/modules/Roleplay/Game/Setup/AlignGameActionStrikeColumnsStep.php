<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Setup;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Schema\DdlFailedException;
use Mifrial\Core\SmartTable\Exception\Schema\SchemaMismatchException;
use Mifrial\Core\SmartTable\Exception\Schema\TableMissingException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Table\GameActionStrikeTable;

/**
 * Добавляет в game_action_strike колонки, появившиеся после первой установки.
 */
final class AlignGameActionStrikeColumnsStep implements ISetupStep
{
    /**
     * Создаёт шаг.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз таблиц.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
    ) {
    }

    /**
     * Возвращает id шага.
     *
     * @return string Ключ шага.
     */
    public function getId(): string
    {
        return 'Roleplay/Game:align-game-action-strike-columns';
    }

    /**
     * Приводит game_action_strike к карте заголовка.
     *
     * @return void
     *
     * @throws KernelException Если DDL отклонён.
     */
    public function run(): void
    {
        $schema = $this->smartTableGateway->open(GameActionStrikeTable::class)->schema();
        if (!$schema->exists()) {
            return;
        }

        try {
            $schema->forceUpdateTable();
        } catch (
            TableMissingException
            | SchemaMismatchException
            | MapInvalidException
            | DdlFailedException $exception
        ) {
            throw new KernelException(
                'SETUP_FAILED',
                'Game action strike columns align failed',
                $exception,
            );
        }
    }
}
