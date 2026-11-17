<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameActiveEffectRecord;
use Mifrial\Roleplay\Game\Dto\GameActiveSpellRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellEffectSource;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellSenseEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellStateEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\MagicPath\MagicPathSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\SenseSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Временные эффекты заклинания: чувства кастера и состояния цели.
 *
 * Эффект читается из спеки карточки и строки `game_active_spell` или
 * `game_active_effect`; в лист он не пишется. Пока строка жива, эффект есть;
 * строка удалена — эффект исчез.
 */
final class GameSpellEffect
{
    /**
     * Эффекты карточки допустимы только у своей длительности.
     *
     * @param AbilitySpellSpec $card Карточка.
     *
     * @return void
     *
     * @throws GameInvalidException Если эффект есть, а длительность чужая.
     */
    public function assertSupported(AbilitySpellSpec $card): void
    {
        $duration = $card->getSpell()->getDurationType();
        foreach ($card->getSpell()->getEffects() as $effect) {
            if ($effect instanceof SpellSenseEffect && $duration !== 'sustained') {
                throw new GameInvalidException('Game spell effects are not supported for this duration');
            }

            if ($effect instanceof SpellStateEffect && $duration !== 'lingering') {
                throw new GameInvalidException('Game spell state is not supported for this duration');
            }
        }
    }

    /**
     * Строки состояний цели от наложенных заклинаний.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<int, GameActiveEffectRecord> $effects Строки цели.
     *
     * @return array<int, array<string, mixed>> Строки для пересчёта листа.
     *
     * @throws GameInvalidException Если карточка, состояние или источник битые.
     */
    public function runtimeStates(CharacterRuleSlice $slice, array $effects): array
    {
        $rows = [];
        foreach ($effects as $effect) {
            if ($effect->getSourceType() !== 'spell') {
                continue;
            }

            $card = $this->card($slice, $effect->getSourceRuleCode());
            foreach ($card->getSpell()->getEffects() as $item) {
                if ($item instanceof SpellStateEffect) {
                    $rows[] = $this->stateRow($slice, $effect, $item);
                }
            }
        }

        return $rows;
    }

    /**
     * Строка одного состояния цели.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param GameActiveEffectRecord $effect Строка эффекта.
     * @param SpellStateEffect $item Эффект карточки.
     *
     * @return array<string, mixed> Строка состояния.
     *
     * @throws GameInvalidException Если состояния или источника нет.
     */
    private function stateRow(
        CharacterRuleSlice $slice,
        GameActiveEffectRecord $effect,
        SpellStateEffect $item,
    ): array {
        $code = $item->getStateCode();
        $spec = $slice->findLive($code)?->getSpec();
        if (!$spec instanceof StateSpec) {
            throw new GameInvalidException('Game spell state is invalid');
        }

        return [
            'stateRuleCode' => $code,
            'value' => $this->stateUnits($effect, $item, $spec),
            'sourceRuleCode' => $effect->getSourceRuleCode(),
            'runtimeId' => $effect->getId() . ':' . $code,
            'runtime' => true,
        ];
    }

    /**
     * Единицы состояния из строки эффекта.
     *
     * @param GameActiveEffectRecord $effect Строка эффекта.
     * @param SpellStateEffect $item Эффект карточки.
     * @param StateSpec $spec Спека состояния.
     *
     * @return int Единицы.
     *
     * @throws GameInvalidException Если источник не поддержан.
     */
    private function stateUnits(
        GameActiveEffectRecord $effect,
        SpellStateEffect $item,
        StateSpec $spec,
    ): int {
        if ($spec->getValueType() === 'flag') {
            return 1;
        }

        $source = $item->getValue();
        $from = $source?->getFrom() ?? 'power';
        if ($from === 'power') {
            $strength = $effect->getStrength() ?? ['base' => 1, 'size' => 0];
            $units = $strength['base'];
        } elseif ($from === 'parameter') {
            $code = $source?->getParameterCode();
            $value = $code === null ? null : ($effect->getContext()['parameterValues'][$code] ?? null);
            $units = is_int($value) ? $value : 1;
        } else {
            throw new GameInvalidException('Game spell state source is invalid');
        }

        $units = max(1, $units);

        return $item->getMax() === null ? $units : min($item->getMax(), $units);
    }


    /**
     * Строки временных чувств от набора поддерживаемых заклинаний.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<int, GameActiveSpellRecord> $spells Строки кастера.
     * @param array<string, mixed> $sheet Пересчитанный лист кастера.
     *
     * @return array<int, array<string, mixed>> Строки для пересчёта листа.
     *
     * @throws GameInvalidException Если карточка, путь или чувство битые.
     */
    public function runtimeSenses(CharacterRuleSlice $slice, array $spells, array $sheet): array
    {
        $rows = [];
        foreach ($spells as $spell) {
            $card = $this->card($slice, $spell->getSpellCode());
            foreach ($card->getSpell()->getEffects() as $effect) {
                if (!$effect instanceof SpellSenseEffect) {
                    continue;
                }

                $rows[] = $this->senseRow($slice, $spell, $effect, $sheet);
            }
        }

        return $rows;
    }

    /**
     * Строка одного чувства.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param GameActiveSpellRecord $spell Строка.
     * @param SpellSenseEffect $effect Эффект.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameInvalidException Если чувство или путь битые.
     */
    private function senseRow(
        CharacterRuleSlice $slice,
        GameActiveSpellRecord $spell,
        SpellSenseEffect $effect,
        array $sheet,
    ): array {
        $sense = $slice->findLive($effect->getSenseCode())?->getSpec();
        if (!$sense instanceof SenseSpec) {
            throw new GameInvalidException('Game spell sense is invalid');
        }

        $row = [
            'ruleCode' => $effect->getSenseCode(),
            'sourceRuleCode' => $spell->getSpellCode(),
            'value' => 0,
        ];
        if ($effect->getStatus() !== null) {
            $row['status'] = $effect->getStatus();
        }

        $control = null;
        $radius = $effect->getRadius();
        $value = $effect->getValue();
        if (($radius !== null && $radius->getFrom() === 'control')
            || ($value !== null && $value->getFrom() === 'control')) {
            $control = $this->control($slice, $spell, $sheet);
        }

        if ($radius !== null) {
            $radiusValue = $this->dimensional($radius, $spell, $control);
            $row['radius'] = ['base' => $radiusValue->getBase(), 'size' => $radiusValue->getSize()];
        }

        if ($value !== null) {
            $row['value'] = $this->scalar($value, $spell, $control);
        }

        return $row;
    }

    /**
     * Контроль пути кастера.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param GameActiveSpellRecord $spell Строка.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return DimensionalNumber Контроль.
     *
     * @throws GameInvalidException Если пути или характеристики нет.
     */
    private function control(CharacterRuleSlice $slice, GameActiveSpellRecord $spell, array $sheet): DimensionalNumber
    {
        $path = $slice->findLive($spell->getPathCode())?->getSpec();
        if (!$path instanceof MagicPathSpec) {
            throw new GameInvalidException('Game spell effect path is invalid');
        }

        $code = $path->getControlCharacteristicCode();
        if ($code === null || $code === '') {
            throw new GameInvalidException('Game spell effect control is missing');
        }

        foreach (is_array($sheet['characteristics'] ?? null) ? $sheet['characteristics'] : [] as $row) {
            if (!is_array($row) || ($row['characteristicCode'] ?? null) !== $code) {
                continue;
            }

            $value = is_array($row['value'] ?? null) ? $row['value'] : [];
            $base = $value['base'] ?? null;
            $size = $value['size'] ?? null;
            if (is_int($base) && is_int($size)) {
                return new DimensionalNumber($base, $size);
            }
        }

        throw new GameInvalidException('Game spell effect control is missing');
    }

    /**
     * Размерная величина источника.
     *
     * @param SpellEffectSource $source Источник.
     * @param GameActiveSpellRecord $spell Строка.
     * @param DimensionalNumber|null $control Контроль или null.
     *
     * @return DimensionalNumber Величина.
     *
     * @throws GameInvalidException Если источник неизвестен.
     */
    private function dimensional(
        SpellEffectSource $source,
        GameActiveSpellRecord $spell,
        ?DimensionalNumber $control,
    ): DimensionalNumber {
        return match ($source->getFrom()) {
            'power' => $this->pair($spell->getUsedPower()),
            'sustain_power' => $this->pair($spell->getSustainPower()),
            'control' => $control ?? throw new GameInvalidException('Game spell effect control is missing'),
            'parameter' => $this->parameterPair($spell, $source),
            default => throw new GameInvalidException('Game spell effect source is invalid'),
        };
    }

    /**
     * Скалярное значение источника.
     *
     * @param SpellEffectSource $source Источник.
     * @param GameActiveSpellRecord $spell Строка.
     * @param DimensionalNumber|null $control Контроль или null.
     *
     * @return int Значение.
     *
     * @throws GameInvalidException Если источник неизвестен.
     */
    private function scalar(
        SpellEffectSource $source,
        GameActiveSpellRecord $spell,
        ?DimensionalNumber $control,
    ): int {
        if ($source->getFrom() === 'parameter') {
            return $this->parameterScalar($spell, $source);
        }

        $value = $this->dimensional($source, $spell, $control);
        $reference = $source->getReference();
        if ($reference === null) {
            return 0;
        }

        return (new CharacteristicNumber($value->getBase(), $value->getSize()))
            ->characteristicDiffTo($reference);
    }

    /**
     * Параметр строки как пара.
     *
     * @param GameActiveSpellRecord $spell Строка.
     * @param SpellEffectSource $source Источник.
     *
     * @return DimensionalNumber Пара.
     *
     * @throws GameInvalidException Если параметра нет или он не размерный.
     */
    private function parameterPair(GameActiveSpellRecord $spell, SpellEffectSource $source): DimensionalNumber
    {
        $code = $source->getParameterCode();
        $value = $code === null ? null : ($spell->getParameterValues()[$code] ?? null);
        if (is_int($value)) {
            return new DimensionalNumber($value, 0);
        }

        if (!is_array($value)) {
            throw new GameInvalidException('Game spell effect parameter is invalid');
        }

        return new DimensionalNumber($value['base'], $value['size']);
    }

    /**
     * Параметр строки как скаляр.
     *
     * @param GameActiveSpellRecord $spell Строка.
     * @param SpellEffectSource $source Источник.
     *
     * @return int Значение.
     *
     * @throws GameInvalidException Если параметра нет или он размерный.
     */
    private function parameterScalar(GameActiveSpellRecord $spell, SpellEffectSource $source): int
    {
        $code = $source->getParameterCode();
        $value = $code === null ? null : ($spell->getParameterValues()[$code] ?? null);
        if (!is_int($value)) {
            throw new GameInvalidException('Game spell effect parameter is invalid');
        }

        return $value;
    }

    /**
     * Пара из массива.
     *
     * @param array{base: int, size: int} $value Пара.
     *
     * @return DimensionalNumber Пара.
     */
    private function pair(array $value): DimensionalNumber
    {
        return new DimensionalNumber($value['base'], $value['size']);
    }

    /**
     * Карточка заклинания.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код.
     *
     * @return AbilitySpellSpec Карточка.
     *
     * @throws GameInvalidException Если карточка не заклинание.
     */
    private function card(CharacterRuleSlice $slice, string $code): AbilitySpellSpec
    {
        $card = $slice->findLive($code)?->getSpec();
        if (!$card instanceof AbilitySpellSpec) {
            throw new GameInvalidException('Game spell effect card is invalid');
        }

        return $card;
    }
}
