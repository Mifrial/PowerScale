<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Character\Dto\Action\MigrateCharacterInput;
use Mifrial\Roleplay\Character\Service\CharacterMigration;

/**
 * Перевод персонажа на другую ревизию того же мира.
 */
final class MigrateCharacterAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param CharacterMigration $characterMigration Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterMigration $characterMigration,
    ) {
    }

    /**
     * Ремапит или принимает продолжение и пишет лист без problems.
     *
     * @param MigrateCharacterInput $input JSON.
     *
     * @return array<string, mixed> Лист или conflicts.
     */
    public function handle(MigrateCharacterInput $input): array
    {
        return $this->characterMigration->migrate($input);
    }
}
