<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\CastGameSpellInput;
use Mifrial\Roleplay\Game\Service\GameSpellCastHttp;

/**
 * Серверное сотворение заклинания.
 */
final class CastGameSpellAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameSpellCastHttp $cast Сценарий.
     *
     * @return void
     */
    public function __construct(private readonly GameSpellCastHttp $cast)
    {
    }

    /**
     * Выполняет действие.
     *
     * @param CastGameSpellInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(CastGameSpellInput $input): array
    {
        return $this->cast->run($input);
    }
}
