<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service\Handler;

use Mifrial\Roleplay\Mechanic\Constant\RollEvent;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Mechanic\Dto\RollMechanicContext;
use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;
use Mifrial\Roleplay\Mechanic\Interface\MechanicPayload;

/**
 * Помехи и преимущества: до броска растит пул, после броска снимает крайние грани.
 */
final class AdvantageDisadvantageHandler implements IMechanicHandler
{
    /**
     * Код семейства.
     *
     * @return string Код advantage_disadvantage.
     */
    public function getCode(): string
    {
        return 'advantage_disadvantage';
    }

    /**
     * Поставка контракта.
     *
     * @return string Версия 2.1.0.
     */
    public function getVersion(): string
    {
        return '2.1.0';
    }

    /**
     * Подписки на пул и сброс с приоритетом 10.
     *
     * @return array<string, int> Карта.
     */
    public function getSubscriptions(): array
    {
        return [RollEvent::POOL => 10, RollEvent::DROP => 10];
    }

    /**
     * Меняет пул или сбрасывает грани. Payload не читает. Нулевое нетто выходит.
     *
     * @param MechanicPayload|null $payload Payload binding. Не используется.
     * @param object $context Контекст броска.
     * @param string $event Имя события.
     *
     * @return void
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInImplementedInterfaceBeforeLastUsed
    public function run(?MechanicPayload $payload, object $context, string $event): void
    {
        if (!$context instanceof RollMechanicContext) {
            return;
        }

        $this->apply($context, $event, $this->net($context->getAdvantages()));
    }

    /**
     * Применяет нетто к событию.
     *
     * @param RollMechanicContext $context Контекст.
     * @param string $event Имя события.
     * @param int $net Нетто кубов.
     *
     * @return void
     */
    private function apply(RollMechanicContext $context, string $event, int $net): void
    {
        if ($net === 0 || !$this->isRollEvent($event)) {
            return;
        }

        if ($event === RollEvent::POOL) {
            $context->growPool(abs($net));

            return;
        }

        $this->drop($context, $net);
    }

    /**
     * Событие пула или сброса.
     *
     * @param string $event Имя события.
     *
     * @return bool true для pool и drop.
     */
    private function isRollEvent(string $event): bool
    {
        return $event === RollEvent::POOL || $event === RollEvent::DROP;
    }

    /**
     * Снимает крайние грани: преимущество — большие, помеха — маленькие.
     *
     * @param RollMechanicContext $context Контекст после броска.
     * @param int $net Нетто. Плюс — преимущество.
     *
     * @return void
     */
    private function drop(RollMechanicContext $context, int $net): void
    {
        $sorted = $context->getRolls();
        usort(
            $sorted,
            static fn (int $left, int $right): int => $net > 0 ? $right <=> $left : $left <=> $right,
        );
        $count = min(abs($net), count($sorted));
        $context->applyDrop(array_slice($sorted, 0, $count), array_slice($sorted, $count));
        $context->markApplied($this->getCode());
    }

    /**
     * Нетто: нули не входят, один источник держит крайний плюс и крайний минус.
     *
     * @param array<int, RollAdvantage> $entries Записи.
     *
     * @return int Сумма оставшихся дельт.
     */
    private function net(array $entries): int
    {
        $sum = 0;
        foreach ($this->group($entries) as $group) {
            $sum += $this->extreme($group, true);
            $sum += $this->extreme($group, false);
        }

        return $sum;
    }

    /**
     * Группы по источнику. null — отдельная группа на каждую запись.
     *
     * @param array<int, RollAdvantage> $entries Записи.
     *
     * @return array<string, array<int, RollAdvantage>> Группы.
     */
    private function group(array $entries): array
    {
        $groups = [];
        foreach ($entries as $index => $entry) {
            if ($entry->getDelta() === 0) {
                continue;
            }

            $key = $entry->getSourceCode() ?? "\0" . $index;
            $groups[$key][] = $entry;
        }

        return $groups;
    }

    /**
     * Крайняя дельта группы.
     *
     * @param array<int, RollAdvantage> $group Записи одного источника.
     * @param bool $bonus true — самый большой плюс, false — самый большой минус.
     *
     * @return int Дельта или 0, если такой нет.
     */
    private function extreme(array $group, bool $bonus): int
    {
        $best = 0;
        $found = false;
        foreach ($group as $entry) {
            $delta = $entry->getDelta();
            if (!$this->matchesSide($delta, $bonus)) {
                continue;
            }

            if (!$found || $this->isStronger($delta, $best, $bonus)) {
                $best = $delta;
                $found = true;
            }
        }

        return $best;
    }

    /**
     * Дельта той стороны, которую ищем.
     *
     * @param int $delta Дельта.
     * @param bool $bonus true — плюс.
     *
     * @return bool true, если сторона совпала.
     */
    private function matchesSide(int $delta, bool $bonus): bool
    {
        if ($bonus) {
            return $delta > 0;
        }

        return $delta < 0;
    }

    /**
     * Новая дельта сильнее уже найденной.
     *
     * @param int $delta Кандидат.
     * @param int $best Уже найденная.
     * @param bool $bonus true — больше плюс сильнее.
     *
     * @return bool true, если кандидат сильнее.
     */
    private function isStronger(int $delta, int $best, bool $bonus): bool
    {
        if ($bonus) {
            return $delta > $best;
        }

        return $delta < $best;
    }
}
