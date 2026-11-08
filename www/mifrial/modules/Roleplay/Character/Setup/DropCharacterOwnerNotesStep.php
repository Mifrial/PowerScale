<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Setup;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Schema\DdlFailedException;
use Mifrial\Core\SmartTable\Exception\Schema\SchemaMismatchException;
use Mifrial\Core\SmartTable\Exception\Schema\TableMissingException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Table\CharacterTable;

/**
 * Снимает с character колонку заметок, уехавшую в Messages/Notes.
 */
final class DropCharacterOwnerNotesStep implements ISetupStep
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
        return 'Roleplay/Character:drop-owner-notes';
    }

    /**
     * Приводит character к карте без owner_notes.
     *
     * @return void
     *
     * @throws KernelException Если DDL отклонён.
     */
    public function run(): void
    {
        $schema = $this->smartTableGateway->open(CharacterTable::class)->schema();
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
                'Character owner notes drop failed',
                $exception,
            );
        }
    }
}
