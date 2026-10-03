<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterProblemList;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Mechanic\Exception\MechanicNotFoundException;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;

/**
 * Неподдержанный handler строки purchase_surcharge — problem. Чужая форма молчит.
 */
final class CharacterMechanicSupport
{
    /**
     * Принимает каталог механик.
     *
     * @param IMechanics $mechanics Каталог.
     * @param CharacterMechanicBindings $bindings Разбор строк механик.
     *
     * @return void
     */
    public function __construct(
        private readonly IMechanics $mechanics,
        private readonly CharacterMechanicBindings $bindings,
    ) {
    }

    /**
     * Сверяет поставки binding-строк с purchase_surcharge 1.0.0.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return void
     */
    public function check(CharacterRuleSlice $slice, CharacterProblemList $problems): void
    {
        foreach ($this->bindings->fromSlice($slice) as $binding) {
            $mechanicId = $binding->getMechanicId();
            if ($mechanicId !== null) {
                $this->checkMechanic($binding->getRuleCode(), $mechanicId, $problems);
            }
        }
    }

    /**
     * Поставка каталога поддержана движком.
     *
     * @param string $ruleCode Код правила.
     * @param int $mechanicId Id.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return void
     */
    private function checkMechanic(string $ruleCode, int $mechanicId, CharacterProblemList $problems): void
    {
        try {
            $record = $this->mechanics->get($mechanicId);
        } catch (MechanicNotFoundException) {
            return;
        }

        if ($record->getCode() === 'purchase_surcharge' && $record->getHandlerVersion() === '1.0.0') {
            return;
        }

        $problems->add(
            'CHARACTER_HANDLER',
            'Mechanic handler is not supported',
            'mechanic',
            $ruleCode,
        );
    }
}
