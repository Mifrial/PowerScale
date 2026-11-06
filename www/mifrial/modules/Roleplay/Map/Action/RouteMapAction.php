<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Map\Dto\Action\RouteMapInput;
use Mifrial\Roleplay\Map\Service\MapRouteHttp;

/**
 * HTTP-обработчик карт.
 */
final class RouteMapAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param MapRouteHttp $service Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly MapRouteHttp $service,
    ) {
    }

    /**
     * Выполняет сценарий.
     *
     * @param RouteMapInput $input JSON.
     *
     * @return array<string, mixed> Результат.
     */
    public function handle(RouteMapInput $input): array
    {
        return $this->service->route($input);
    }
}
