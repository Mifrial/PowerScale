<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service\Handler;

use Mifrial\Roleplay\Mechanic\Constant\RollEvent;
use Mifrial\Roleplay\Mechanic\Dto\RollMechanicContext;
use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;
use Mifrial\Roleplay\Mechanic\Interface\MechanicPayload;

/**
 * Правило «6 и 1»: единица добавляет успех, грань выше эффективности снимает успех.
 */
final class SixOneRuleHandler implements IMechanicHandler
{
    /**
     * Код семейства.
     *
     * @return string Код six_one_rule.
     */
    public function getCode(): string
    {
        return 'six_one_rule';
    }

    /**
     * Поставка контракта.
     *
     * @return string Версия 4.5.0.
     */
    public function getVersion(): string
    {
        return '4.5.0';
    }

    /**
     * Подписка на подсчёт с приоритетом 10.
     *
     * @return array<string, int> Карта.
     */
    public function getSubscriptions(): array
    {
        return [RollEvent::SCORE => 10];
    }

    /**
     * Начисляет дельты успехам граней. Payload не читает.
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
        if (!$context instanceof RollMechanicContext || $event !== RollEvent::SCORE) {
            return;
        }

        if ($this->adjust($context)) {
            $context->markApplied($this->getCode());
        }
    }

    /**
     * Прибавляет дельты к успехам тех же индексов.
     *
     * @param RollMechanicContext $context Контекст после базового подсчёта.
     *
     * @return bool true, если хотя бы одна дельта не ноль.
     */
    private function adjust(RollMechanicContext $context): bool
    {
        $changed = false;
        foreach ($context->getAdjustedRolls() as $index => $value) {
            $delta = $this->delta($value, $context);
            if ($delta === 0) {
                continue;
            }

            $context->addSuccess($index, $delta);
            $changed = true;
        }

        return $changed;
    }

    /**
     * Дельта одной грани: +1 за единицу, −1 за грань выше эффективности.
     *
     * @param int $value Грань.
     * @param RollMechanicContext $context Порог и число граней.
     *
     * @return int Дельта.
     */
    private function delta(int $value, RollMechanicContext $context): int
    {
        $delta = 0;
        if ($value === 1) {
            $delta++;
        }

        if ($value === $context->getDieFaces() && $value > $context->getEfficiency()) {
            $delta--;
        }

        return $delta;
    }
}
