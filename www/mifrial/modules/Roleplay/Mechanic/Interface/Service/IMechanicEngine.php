<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Interface\Service;

use Mifrial\Roleplay\Mechanic\Dto\MechanicBinding;
use Mifrial\Roleplay\Mechanic\Dto\MechanicRecord;
use Mifrial\Roleplay\Mechanic\Dto\ResolveActiveOptions;
use Mifrial\Roleplay\Mechanic\Dto\ResolvedMechanic;

/**
 * Публичный событийный движок: сосед не регистрирует хендлеры и не видит реестр.
 */
interface IMechanicEngine
{
    /**
     * Собирает механики binding через каталог id → code@version.
     *
     * @param array<int, MechanicBinding> $bindings Срезы правил.
     * @param array<int, MechanicRecord> $mechanics Строки каталога.
     * @param ResolveActiveOptions $options Фильтр семейства и доп. коды правил.
     *
     * @return array<int, ResolvedMechanic> Активные механики, включая повтор extraRuleCodes.
     */
    public function resolveActive(array $bindings, array $mechanics, ResolveActiveOptions $options): array;

    /**
     * Вызывает подписчиков события по возрастанию приоритета.
     *
     * @param string $event Имя события.
     * @param object $context Контекст шага; хендлеры мутируют его.
     * @param array<int, ResolvedMechanic> $active Активные механики.
     *
     * @return void
     */
    public function runEvent(string $event, object $context, array $active): void;
}
