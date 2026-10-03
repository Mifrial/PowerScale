<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterOsSteps;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterMechanicBindings;
use Mifrial\Roleplay\Mechanic\Constant\PurchaseSurchargeEvent;
use Mifrial\Roleplay\Mechanic\Dto\CharacterMechanicContext;
use Mifrial\Roleplay\Mechanic\Dto\MechanicBinding;
use Mifrial\Roleplay\Mechanic\Dto\MechanicRecord;
use Mifrial\Roleplay\Mechanic\Dto\MechanicState;
use Mifrial\Roleplay\Mechanic\Dto\ResolveActiveOptions;
use Mifrial\Roleplay\Mechanic\Exception\MechanicNotFoundException;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;

/**
 * Собирает binding и снимок и вызывает движок на character.osSteps.
 */
final class CharacterOsSteps implements ICharacterOsSteps
{
    /**
     * Принимает порт движка и каталог механик.
     *
     * @param IMechanicEngine $engine Движок с уже зарегистрированным purchase_surcharge.
     * @param IMechanics $mechanics Каталог поставок.
     *
     * @return void
     */
    public function __construct(
        private readonly IMechanicEngine $engine,
        private readonly IMechanics $mechanics,
        private readonly CharacterMechanicBindings $bindings = new CharacterMechanicBindings(),
    ) {
    }

    /**
     * Считает доплату character.osSteps. Запись персонажа не делает.
     *
     * @param CharacterRuleSlice $slice Уже загруженный срез.
     * @param array<string, int> $abilityLevels Уровни по коду способности, в порядке вставки.
     * @param array<int, string> $racialAbilityCodes Коды способностей выбранной расы.
     *
     * @return CharacterMechanicContext Контекст с аккумуляторами доплаты.
     */
    public function runOsSteps(
        CharacterRuleSlice $slice,
        array $abilityLevels,
        array $racialAbilityCodes,
    ): CharacterMechanicContext {
        $bindings = $this->bindings->fromSlice($slice);
        $context = new CharacterMechanicContext($this->stateOf($slice, $abilityLevels, $racialAbilityCodes));
        $active = $this->engine->resolveActive($bindings, $this->catalogOf($bindings), new ResolveActiveOptions());
        $this->engine->runEvent(PurchaseSurchargeEvent::NAME, $context, $active);

        return $context;
    }

    /**
     * Строки каталога по уникальным id. Отсутствующая строка пропускается.
     *
     * @param array<int, MechanicBinding> $bindings Срезы.
     *
     * @return array<int, MechanicRecord> Найденные поставки.
     */
    private function catalogOf(array $bindings): array
    {
        $records = [];
        $seen = [];
        foreach ($bindings as $binding) {
            $mechanicId = $binding->getMechanicId();
            if ($mechanicId === null || isset($seen[$mechanicId])) {
                continue;
            }

            $seen[$mechanicId] = true;
            $record = $this->findMechanic($mechanicId);
            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * Поставка по id или null, если строки нет.
     *
     * @param int $mechanicId Id каталога.
     *
     * @return MechanicRecord|null Строка или null.
     */
    private function findMechanic(int $mechanicId): ?MechanicRecord
    {
        try {
            return $this->mechanics->get($mechanicId);
        } catch (MechanicNotFoundException) {
            return null;
        }
    }

    /**
     * Узкий снимок: уровни вызывающего и признаки live по коду.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, int> $abilityLevels Уровни.
     * @param array<int, string> $racialAbilityCodes Коды расы.
     *
     * @return MechanicState Снимок.
     */
    private function stateOf(
        CharacterRuleSlice $slice,
        array $abilityLevels,
        array $racialAbilityCodes,
    ): MechanicState {
        $abilityKeywords = [];
        foreach (array_keys($abilityLevels) as $code) {
            $abilityKeywords[$code] = $slice->findLive((string) $code)?->getKeywordCodes() ?? [];
        }

        return new MechanicState($abilityLevels, $abilityKeywords, $racialAbilityCodes);
    }
}
