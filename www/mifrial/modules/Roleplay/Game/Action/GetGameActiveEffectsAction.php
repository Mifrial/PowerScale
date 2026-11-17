<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GetGameActiveEffectsInput;
use Mifrial\Roleplay\Game\Service\GameActiveEffectQueryHttp;

/**
 * Чтение наложенных длительных эффектов игры.
 */
final class GetGameActiveEffectsAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameActiveEffectQueryHttp $query Сценарий.
     *
     * @return void
     */
    public function __construct(private readonly GameActiveEffectQueryHttp $query)
    {
    }

    /**
     * Читает строки.
     *
     * @param GetGameActiveEffectsInput $input JSON.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    public function handle(GetGameActiveEffectsInput $input): array
    {
        return $this->query->get($input);
    }
}
