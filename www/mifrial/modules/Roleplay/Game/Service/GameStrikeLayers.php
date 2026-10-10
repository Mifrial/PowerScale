<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterCombatLayer;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterCombatLayers;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Mechanic\Dto\MechanicBinding;
use Mifrial\Roleplay\Mechanic\Dto\ResolveActiveOptions;
use Mifrial\Roleplay\Mechanic\Exception\MechanicInvalidException;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\Spec\DamageTypeSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Сопротивление цели из проекции слоёв. Лист не пишет.
 */
final class GameStrikeLayers
{
    /**
     * Создаёт расчёт.
     *
     * @param ICharacterCombatLayers $layers Проекция.
     * @param IMechanicEngine $engine Срез.
     * @param IMechanics $mechanics Каталог.
     * @param GameStrikeAmounts $amounts Сумма пар.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacterCombatLayers $layers,
        private readonly IMechanicEngine $engine,
        private readonly IMechanics $mechanics,
        private readonly GameStrikeAmounts $amounts,
    ) {
    }

    /**
     * Пара сопротивления цели.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $damageType Тип урона профиля.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы.
     * @param string $reaction Реакция.
     * @param int|null $blockItemInventoryId Id предмета блока.
     * @param int $rating Рейтинг попадания.
     * @param DimensionalNumber|null $penetration Проникновение атакующего.
     *
     * @return DimensionalNumber Сумма.
     *
     * @throws GameInvalidException Если тип, проекция или блок битые.
     */
    public function total(
        CharacterRuleSlice $slice,
        string $damageType,
        array $sheet,
        array $choices,
        string $reaction,
        ?int $blockItemInventoryId,
        int $rating,
        ?DimensionalNumber $penetration = null,
    ): DimensionalNumber {
        $type = $this->damageType($slice, $damageType);
        try {
            $projected = $this->layers->project(
                $slice->getSpaceId(),
                $slice->getRevision(),
                $sheet,
                $choices,
                $this->blockId($reaction, $blockItemInventoryId),
            );
            $cut = $this->engine->hasReliabilityCut(
                $this->bindings($type['rule']),
                $this->mechanics->getList(),
                new ResolveActiveOptions(),
            );
        } catch (CharacterInvalidException | CharacterNotFoundException | MechanicInvalidException $exception) {
            throw new GameInvalidException('Game strike resistance is invalid', $exception);
        }

        $values = $this->kept($projected, $damageType, $type['ignored'], $cut, $rating);
        $defense = $this->amounts->sum($values['defense']);
        $resistance = $this->amounts->sum($values['resistance']);
        $effectiveDefense = $this->amounts->effectiveDefense(
            $defense,
            $penetration ?? $this->amounts->zero(),
        );
        if ($effectiveDefense->getBase() === 0) {
            return $resistance;
        }
        if ($resistance->getBase() === 0) {
            return $effectiveDefense;
        }

        return $this->amounts->sum([$resistance, $effectiveDefense]);
    }

    /**
     * Живой тип урона.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код.
     *
     * @return array{rule: CharacterResolvedRule, ignored: bool} Правило и флаг.
     *
     * @throws GameInvalidException Если правила нет или spec чужой.
     */
    private function damageType(CharacterRuleSlice $slice, string $code): array
    {
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof DamageTypeSpec || $rule === null) {
            throw new GameInvalidException('Game strike damage type is invalid');
        }

        return ['rule' => $rule, 'ignored' => $spec->isDefenseIgnored()];
    }

    /**
     * Id одной надетой строки блока.
     *
     * @param string $reaction Реакция.
     * @param int|null $blockItemInventoryId Id строки.
     *
     * @return int|null Id или null.
     *
     * @throws GameInvalidException Если строк нет или их несколько.
     */
    private function blockId(string $reaction, ?int $blockItemInventoryId): ?int
    {
        if ($reaction !== 'block') {
            return null;
        }

        if ($blockItemInventoryId === null) {
            return null;
        }

        return $blockItemInventoryId;
    }

    /**
     * Id надетых строк кода.
     *
     * @param array<string, mixed> $choices Выборы.
     * @param string|null $ruleCode Код.
     *
     * @return list<int> Id.
     */
    private function equippedIds(array $choices, ?string $ruleCode): array
    {
        $inventory = $choices['inventory'] ?? [];
        if (!is_array($inventory) || !is_string($ruleCode)) {
            return [];
        }

        $found = [];
        foreach ($inventory as $row) {
            $id = $this->equippedRowId($row, $ruleCode);
            if ($id !== null) {
                $found[] = $id;
            }
        }

        return $found;
    }

    /**
     * Надетая строка этого кода с целым id.
     *
     * @param mixed $row Строка инвентаря.
     * @param string $ruleCode Код.
     *
     * @return int|null Id или null.
     */
    private function equippedRowId(mixed $row, string $ruleCode): ?int
    {
        if (!is_array($row) || ($row['equipped'] ?? false) !== true || ($row['ruleCode'] ?? null) !== $ruleCode) {
            return null;
        }

        return is_int($row['id'] ?? null) ? $row['id'] : null;
    }

    /**
     * Привязки механик правила.
     *
     * @param CharacterResolvedRule $rule Правило.
     *
     * @return list<MechanicBinding> Срезы.
     */
    private function bindings(CharacterResolvedRule $rule): array
    {
        $bindings = [];
        foreach ($rule->getMechanics() as $row) {
            if (
                !is_array($row)
                || !array_key_exists('mechanic_id', $row)
                || !is_int($row['mechanic_id'])
                || $row['mechanic_id'] < 1
            ) {
                throw new MechanicInvalidException('Mechanic binding is invalid');
            }

            if (!array_key_exists('mechanic_payload', $row) || !is_array($row['mechanic_payload'])) {
                throw new MechanicInvalidException('Mechanic binding payload is invalid');
            }

            $bindings[] = new MechanicBinding($rule->getCode(), $row['mechanic_id'], null);
        }

        return $bindings;
    }

    /**
     * Пары слоёв после типа, среза и source.
     *
     * @param array<int, CharacterCombatLayer> $layers Слои.
     * @param string $damageType Тип урона.
     * @param bool $defenseIgnored Защита типа выключена.
     * @param bool $cut Срез включён.
     * @param int $rating Рейтинг.
     *
     * @return array{defense: list<DimensionalNumber>, resistance: list<DimensionalNumber>} Пары по виду.
     */
    private function kept(array $layers, string $damageType, bool $defenseIgnored, bool $cut, int $rating): array
    {
        $matched = [];
        foreach ($layers as $layer) {
            if ($this->matches($layer, $damageType, $defenseIgnored) && !$this->shaved($layer, $cut, $rating)) {
                $matched[] = $layer;
            }
        }

        $values = ['defense' => [], 'resistance' => []];
        foreach (['defense', 'resistance'] as $kind) {
            foreach ($this->collapse($this->ofKind($matched, $kind)) as $layer) {
                $values[$kind][] = $layer->getValue();
            }
        }

        return $values;
    }

    /**
     * Отбирает слои одного вида до независимого source collapse.
     *
     * @param array<int, CharacterCombatLayer> $layers Слои.
     * @param string $kind defense или resistance.
     *
     * @return array<int, CharacterCombatLayer> Слои вида.
     */
    private function ofKind(array $layers, string $kind): array
    {
        return array_values(array_filter(
            $layers,
            static fn (CharacterCombatLayer $layer): bool => $layer->getKind() === $kind,
        ));
    }

    /**
     * Слой относится к типу урона.
     *
     * @param CharacterCombatLayer $layer Слой.
     * @param string $damageType Тип.
     * @param bool $defenseIgnored Защита выключена.
     *
     * @return bool true, если слой входит.
     */
    private function matches(CharacterCombatLayer $layer, string $damageType, bool $defenseIgnored): bool
    {
        if ($layer->getKind() === 'defense' && $defenseIgnored) {
            return false;
        }

        $code = $layer->getDamageTypeCode();

        return $code === null || $code === '' || $code === $damageType;
    }

    /**
     * Срез снимает слой с порогом не выше рейтинга.
     *
     * @param CharacterCombatLayer $layer Слой.
     * @param bool $cut Срез включён.
     * @param int $rating Рейтинг.
     *
     * @return bool true, если слой снят.
     */
    private function shaved(CharacterCombatLayer $layer, bool $cut, int $rating): bool
    {
        $durability = $layer->getDurability();

        return $cut && $durability !== null && $durability <= $rating;
    }

    /**
     * Сильнейший бонус и штраф названного source. Пустой source остаётся.
     *
     * @param array<int, CharacterCombatLayer> $layers Слои.
     *
     * @return list<CharacterCombatLayer> Оставшиеся.
     */
    private function collapse(array $layers): array
    {
        $bonus = [];
        $penalty = [];
        $open = [];
        foreach ($layers as $layer) {
            $this->keepSource($bonus, $penalty, $open, $layer);
        }

        return array_merge($open, array_values($bonus), array_values($penalty));
    }

    /**
     * Кладёт слой в свою корзину source.
     *
     * @param array<string, CharacterCombatLayer> $bonus Бонусы.
     * @param array<string, CharacterCombatLayer> $penalty Штрафы.
     * @param list<CharacterCombatLayer> $open Пустой source.
     * @param CharacterCombatLayer $layer Слой.
     *
     * @return void
     */
    private function keepSource(array &$bonus, array &$penalty, array &$open, CharacterCombatLayer $layer): void
    {
        $source = $layer->getSourceCode();
        if ($source === '') {
            $open[] = $layer;

            return;
        }

        $sign = $this->sign($layer->getValue());
        if ($sign > 0 && $this->stronger($layer, $bonus[$source] ?? null, true)) {
            $bonus[$source] = $layer;
        }

        if ($sign < 0 && $this->stronger($layer, $penalty[$source] ?? null, false)) {
            $penalty[$source] = $layer;
        }
    }

    /**
     * Слой сильнее текущего бонуса или штрафа.
     *
     * @param CharacterCombatLayer $layer Слой.
     * @param CharacterCombatLayer|null $current Уже выбранный.
     * @param bool $bonus true — больший бонус.
     *
     * @return bool true, если слой лучше.
     */
    private function stronger(CharacterCombatLayer $layer, ?CharacterCombatLayer $current, bool $bonus): bool
    {
        if ($current === null) {
            return true;
        }

        $size = min($layer->getValue()->getSize(), $current->getValue()->getSize());
        $left = $this->aligned($layer->getValue(), $size);
        $right = $this->aligned($current->getValue(), $size);

        return $bonus ? $left > $right : $left < $right;
    }

    /**
     * Знак пары к размеру ноль.
     *
     * @param DimensionalNumber $value Пара.
     *
     * @return int -1, 0 или 1.
     */
    private function sign(DimensionalNumber $value): int
    {
        return $this->aligned($value, min($value->getSize(), 0)) <=> 0;
    }

    /**
     * База к меньшему размеру.
     *
     * @param DimensionalNumber $value Пара.
     * @param int $size Размер.
     *
     * @return int База.
     */
    private function aligned(DimensionalNumber $value, int $size): int
    {
        $base = $value->getBase();
        $steps = $value->getSize() - $size;
        for ($step = 0; $step < $steps; $step++) {
            $base *= 2;
        }

        return $base;
    }
}
