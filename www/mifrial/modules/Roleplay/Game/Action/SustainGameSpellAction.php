<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\SustainGameSpellInput;
use Mifrial\Roleplay\Game\Service\GameActiveSpellHttp;

/**
 * Поддержание активного заклинания.
 */
final class SustainGameSpellAction implements IActionHandler
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
     * @param SustainGameSpellInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(SustainGameSpellInput $input): array
    {
        return $this->activeSpell->sustain($input);
    }
}
