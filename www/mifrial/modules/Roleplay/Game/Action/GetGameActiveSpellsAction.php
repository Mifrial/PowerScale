<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GetGameActiveSpellsInput;
use Mifrial\Roleplay\Game\Service\GameActiveSpellQueryHttp;

/**
 * Чтение активных заклинаний игры.
 */
final class GetGameActiveSpellsAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameActiveSpellQueryHttp $query Сценарий.
     *
     * @return void
     */
    public function __construct(private readonly GameActiveSpellQueryHttp $query)
    {
    }

    /**
     * Читает строки.
     *
     * @param GetGameActiveSpellsInput $input JSON.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    public function handle(GetGameActiveSpellsInput $input): array
    {
        return $this->query->get($input);
    }
}
