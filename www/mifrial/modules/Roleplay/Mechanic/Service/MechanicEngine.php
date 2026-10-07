<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service;

use Mifrial\Roleplay\Mechanic\Dto\MechanicBinding;
use Mifrial\Roleplay\Mechanic\Dto\MechanicRecord;
use Mifrial\Roleplay\Mechanic\Dto\ResolveActiveOptions;
use Mifrial\Roleplay\Mechanic\Dto\ResolvedMechanic;
use Mifrial\Roleplay\Mechanic\Exception\MechanicInvalidException;
use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;
use Mifrial\Roleplay\Mechanic\Interface\IReliabilityCut;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine;

/**
 * Событийный движок: собирает активные механики по каталогу и поднимает событие.
 */
final class MechanicEngine implements IMechanicEngine
{
    /**
     * Принимает реестр хендлеров.
     *
     * @param MechanicHandlerRegistry $registry Реестр code@version.
     *
     * @return void
     */
    public function __construct(
        private readonly MechanicHandlerRegistry $registry,
    ) {
    }

    /**
     * Собирает механики binding через каталог id → code@version.
     *
     * @param array<int, MechanicBinding> $bindings Срезы правил.
     * @param array<int, MechanicRecord> $mechanics Строки каталога.
     * @param ResolveActiveOptions $options Фильтр семейства и доп. коды правил.
     *
     * @return array<int, ResolvedMechanic> Активные механики, включая повтор extraRuleCodes.
     *
     * @throws MechanicInvalidException Если у привязки с id нет каталога или хендлера.
     */
    public function resolveActive(array $bindings, array $mechanics, ResolveActiveOptions $options): array
    {
        $byId = $this->indexById($mechanics);
        $resolved = [];
        foreach ($bindings as $binding) {
            $this->pushBinding($resolved, $binding, $byId, $options->getIncludeCodes(), false);
        }

        $this->pushExtraRuleCodes($resolved, $bindings, $byId, $options);

        return $resolved;
    }

    /**
     * Есть ли среди резолва хендлер среза надёжности.
     *
     * @param array<int, MechanicBinding> $bindings Срезы правил.
     * @param array<int, MechanicRecord> $mechanics Строки каталога.
     * @param ResolveActiveOptions $options Фильтр семейства и доп. коды правил.
     *
     * @return bool true, если хендлер реализует маркер среза.
     *
     * @throws MechanicInvalidException Если у привязки с id нет каталога или хендлера.
     */
    public function hasReliabilityCut(array $bindings, array $mechanics, ResolveActiveOptions $options): bool
    {
        foreach ($this->resolveActive($bindings, $mechanics, $options) as $resolved) {
            if ($resolved->getHandler() instanceof IReliabilityCut) {
                return true;
            }
        }

        return false;
    }

    /**
     * Вызывает подписчиков события по возрастанию приоритета.
     *
     * @param string $event Имя события.
     * @param object $context Контекст шага; хендлеры мутируют его.
     * @param array<int, ResolvedMechanic> $active Активные механики.
     *
     * @return void
     */
    public function runEvent(string $event, object $context, array $active): void
    {
        foreach ($this->selectSubscribers($event, $active) as $resolved) {
            $resolved->getHandler()->run($resolved->getPayload(), $context, $event);
        }
    }

    /**
     * Индекс каталога по id. Повтор id оставляет последнюю строку.
     *
     * @param array<int, MechanicRecord> $mechanics Строки каталога.
     *
     * @return array<int, MechanicRecord> Id → строка.
     */
    private function indexById(array $mechanics): array
    {
        $byId = [];
        foreach ($mechanics as $mechanic) {
            $byId[$mechanic->getId()] = $mechanic;
        }

        return $byId;
    }

    /**
     * Добавляет binding с кодами правил из extraRuleCodes, мимо фильтра.
     *
     * @param array<int, ResolvedMechanic> $resolved Уже собранные механики.
     * @param array<int, MechanicBinding> $bindings Все срезы.
     * @param array<int, MechanicRecord> $byId Каталог по id.
     * @param ResolveActiveOptions $options Опции; берутся extraRuleCodes и includeCodes.
     *
     * @return void
     */
    private function pushExtraRuleCodes(
        array &$resolved,
        array $bindings,
        array $byId,
        ResolveActiveOptions $options,
    ): void {
        $pool = $this->poolByRuleCode($bindings);
        foreach ($options->getExtraRuleCodes() ?? [] as $ruleCode) {
            foreach ($pool[$ruleCode] ?? [] as $binding) {
                $this->pushBinding($resolved, $binding, $byId, $options->getIncludeCodes(), true);
            }
        }
    }

    /**
     * Группирует binding по коду правила, сохраняя порядок.
     *
     * @param array<int, MechanicBinding> $bindings Срезы.
     *
     * @return array<string, array<int, MechanicBinding>> Код правила → срезы.
     */
    private function poolByRuleCode(array $bindings): array
    {
        $pool = [];
        foreach ($bindings as $binding) {
            $pool[$binding->getRuleCode()][] = $binding;
        }

        return $pool;
    }

    /**
     * Кладёт binding в список, если каталог и хендлер найдены и фильтр пройден.
     *
     * @param array<int, ResolvedMechanic> $resolved Накопитель.
     * @param MechanicBinding $binding Срез.
     * @param array<int, MechanicRecord> $byId Каталог по id.
     * @param array<int, string>|null $includeCodes Фильтр семейства.
     * @param bool $force true — не применять includeCodes.
     *
     * @return void
     */
    private function pushBinding(
        array &$resolved,
        MechanicBinding $binding,
        array $byId,
        ?array $includeCodes,
        bool $force,
    ): void {
        $handler = $this->resolveHandler($binding, $byId, $includeCodes, $force);
        if ($handler === null) {
            return;
        }

        $resolved[] = new ResolvedMechanic($handler, $binding->getMechanicPayload());
    }

    /**
     * Хендлер binding или null, если строку надо пропустить.
     *
     * @param MechanicBinding $binding Срез.
     * @param array<int, MechanicRecord> $byId Каталог по id.
     * @param array<int, string>|null $includeCodes Фильтр семейства.
     * @param bool $force true — не применять includeCodes.
     *
     * @return IMechanicHandler|null Хендлер или null, если привязки нет или она отфильтрована.
     *
     * @throws MechanicInvalidException Если у привязки с id нет каталога или хендлера.
     */
    private function resolveHandler(
        MechanicBinding $binding,
        array $byId,
        ?array $includeCodes,
        bool $force,
    ): ?IMechanicHandler {
        if ($binding->getMechanicId() === null) {
            return null;
        }

        $mechanic = $this->listed($binding, $byId);
        if ($this->isFilteredOut($mechanic, $includeCodes, $force)) {
            return null;
        }

        return $this->handlerOf($mechanic);
    }

    /**
     * Строка каталога привязки с id.
     *
     * @param MechanicBinding $binding Срез.
     * @param array<int, MechanicRecord> $byId Каталог по id.
     *
     * @return MechanicRecord Строка.
     *
     * @throws MechanicInvalidException Если строки нет.
     */
    private function listed(MechanicBinding $binding, array $byId): MechanicRecord
    {
        $mechanic = $this->findListedMechanic($binding, $byId);
        if ($mechanic === null) {
            throw new MechanicInvalidException('Mechanic binding is not resolved');
        }

        return $mechanic;
    }

    /**
     * Хендлер найденной строки каталога.
     *
     * @param MechanicRecord $mechanic Строка.
     *
     * @return IMechanicHandler Хендлер.
     *
     * @throws MechanicInvalidException Если хендлера нет.
     */
    private function handlerOf(MechanicRecord $mechanic): IMechanicHandler
    {
        $handler = $this->registry->resolve($mechanic->getCode(), $mechanic->getHandlerVersion());
        if ($handler === null) {
            throw new MechanicInvalidException('Mechanic binding is not resolved');
        }

        return $handler;
    }

    /**
     * Строка каталога по mechanicId.
     *
     * @param MechanicBinding $binding Срез.
     * @param array<int, MechanicRecord> $byId Каталог по id.
     *
     * @return MechanicRecord|null Строка или null.
     */
    private function findListedMechanic(MechanicBinding $binding, array $byId): ?MechanicRecord
    {
        $mechanicId = $binding->getMechanicId();
        if ($mechanicId === null) {
            return null;
        }

        return $byId[$mechanicId] ?? null;
    }

    /**
     * Строка отсечена фильтром includeCodes.
     *
     * @param MechanicRecord $mechanic Строка каталога.
     * @param array<int, string>|null $includeCodes Фильтр семейства; null — не фильтровать.
     * @param bool $force true — фильтр не применять.
     *
     * @return bool true, если строку пропустить.
     */
    private function isFilteredOut(MechanicRecord $mechanic, ?array $includeCodes, bool $force): bool
    {
        if ($force || $includeCodes === null) {
            return false;
        }

        return !in_array($mechanic->getCode(), $includeCodes, true);
    }

    /**
     * Подписчики события, от меньшего приоритета к большему.
     *
     * @param string $event Имя события.
     * @param array<int, ResolvedMechanic> $active Активные механики.
     *
     * @return array<int, ResolvedMechanic> Подписчики.
     */
    private function selectSubscribers(string $event, array $active): array
    {
        $subscribed = [];
        foreach ($active as $resolved) {
            $this->keepSubscriber($subscribed, $resolved, $event);
        }

        usort(
            $subscribed,
            static fn (ResolvedMechanic $left, ResolvedMechanic $right): int => self::comparePriority(
                $left,
                $right,
                $event,
            ),
        );

        return $subscribed;
    }

    /**
     * Оставляет механику, если она подписана на событие.
     *
     * @param array<int, ResolvedMechanic> $subscribed Накопитель подписчиков.
     * @param ResolvedMechanic $resolved Механика.
     * @param string $event Имя события.
     *
     * @return void
     */
    private function keepSubscriber(array &$subscribed, ResolvedMechanic $resolved, string $event): void
    {
        if (!array_key_exists($event, $resolved->getHandler()->getSubscriptions())) {
            return;
        }

        $subscribed[] = $resolved;
    }

    /**
     * Сравнивает приоритеты подписки. Меньше — раньше.
     *
     * @param ResolvedMechanic $left Левая механика.
     * @param ResolvedMechanic $right Правая механика.
     * @param string $event Имя события.
     *
     * @return int Результат для usort.
     */
    private static function comparePriority(ResolvedMechanic $left, ResolvedMechanic $right, string $event): int
    {
        return $left->getHandler()->getSubscriptions()[$event]
            <=> $right->getHandler()->getSubscriptions()[$event];
    }
}
