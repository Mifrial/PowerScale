<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\DropGameSpellInput;
use Mifrial\Roleplay\Game\Service\GameActiveSpellHttp;

/**
 * Прекращение поддержания заклинания.
 */
final class DropGameSpellAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameActiveSpellHttp $activeSpell Сценарий.
     *
     * @return void
     */
    public function __construct(private readonly GameActiveSpellHttp $activeSpell)
    {
    }

    /**
     * Выполняет действие.
     *
     * @param DropGameSpellInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(DropGameSpellInput $input): array
    {
        return $this->activeSpell->drop($input);
    }
}
