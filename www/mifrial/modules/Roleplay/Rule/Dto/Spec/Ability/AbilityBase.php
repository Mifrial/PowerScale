<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ActionEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Hit\HitResolution;

/**
 * Общие поля способности, кроме ветки type.
 */
final class AbilityBase
{
    /**
     * Создаёт базу.
     *
     * @param array<string, AbilityZone> $zones Цены по зонам.
     * @param array<int, array{level: int, requirements: array<int, AbilityRequirement>}> $requirements Требования.
     * @param array<int, AbilityGrantBlock> $grantBlocks Гранты.
     * @param array<int, ActionEffect> $actionEffects Эффекты.
     * @param array<int, AbilityParameter> $parameters Параметры.
     * @param string|null $parentAbilityCode Родитель.
     * @param string|null $attackMode Режим атаки.
     * @param int|null $minTotalActionCost Нижняя цена ОД.
     * @param int|null $strikeCount Число ударов.
     * @param bool $distinctWeapons Разное оружие.
     * @param bool $sameWeapon Одно оружие.
     * @param int|null $minWeapons Минимум экземпляров.
     * @param int|null $maxWeapons Максимум экземпляров.
     * @param bool $liftParentMaxWeapons Снять потолок родителя.
     * @param int|null $maxTargets Максимум целей.
     * @param string|null $combatAction Роль в бою.
     * @param bool $peakConcentration Пик концентрации.
     * @param bool $willFocus Воля.
     * @param bool $longTension Долгая концентрация.
     * @param bool $multiple Множественный навык.
     * @param string|null $weaponItemCode Предмет оружия.
     * @param string|null $groupCode Группа.
     * @param string|null $domainRef Справочник домена.
     * @param string|null $knowledgeTemplateField Шаблон знания.
     * @param string|null $parentKnowledgeField Фильтр знания.
     * @param int|null $movementStepSizeDelta Шаг.
     * @param HitResolution|null $hitResolution Доставка попадания.
     * @param PushSpec|null $push Толчок.
     * @param SpellUpgrade|null $spellUpgrade Улучшение заклинания.
     * @param SpellSaturation|null $spellSaturation Насыщение.
     * @param NextCastDifficulty|null $nextCastDifficulty Следующая сложность.
     * @param SpellTouch|null $spellTouch Касание.
     * @param CoverAlly|null $coverAlly Прикрытие.
     * @param KnownAttackDefense|null $knownAttackDefense Известная атака.
     * @param StrikeUpgrade|null $strikeUpgrade Улучшение удара.
     * @param AbilityAggregate|null $aggregate Агрегат уровня.
     * @param DerivedLevel|null $derivedLevel Производный уровень.
     *
     * @return void
     */
    public function __construct(
        private readonly array $zones,
        private readonly array $requirements,
        private readonly array $grantBlocks,
        private readonly array $actionEffects,
        private readonly array $parameters,
        private readonly ?string $parentAbilityCode,
        private readonly ?string $attackMode,
        private readonly ?int $minTotalActionCost,
        private readonly ?int $strikeCount,
        private readonly bool $distinctWeapons,
        private readonly bool $sameWeapon,
        private readonly ?int $minWeapons,
        private readonly ?int $maxWeapons,
        private readonly bool $liftParentMaxWeapons,
        private readonly ?int $maxTargets,
        private readonly ?string $combatAction,
        private readonly bool $peakConcentration,
        private readonly bool $willFocus,
        private readonly bool $longTension,
        private readonly bool $multiple,
        private readonly ?string $weaponItemCode,
        private readonly ?string $groupCode,
        private readonly ?string $domainRef,
        private readonly ?string $knowledgeTemplateField,
        private readonly ?string $parentKnowledgeField,
        private readonly ?int $movementStepSizeDelta,
        private readonly ?HitResolution $hitResolution,
        private readonly ?PushSpec $push,
        private readonly ?SpellUpgrade $spellUpgrade,
        private readonly ?SpellSaturation $spellSaturation,
        private readonly ?NextCastDifficulty $nextCastDifficulty,
        private readonly ?SpellTouch $spellTouch,
        private readonly ?CoverAlly $coverAlly,
        private readonly ?KnownAttackDefense $knownAttackDefense,
        private readonly ?StrikeUpgrade $strikeUpgrade,
        private readonly ?AbilityAggregate $aggregate,
        private readonly ?DerivedLevel $derivedLevel,
    ) {
    }

    /**
     * Блоки грантов.
     *
     * @return array<int, AbilityGrantBlock> Блоки.
     */
    public function getGrantBlocks(): array
    {
        return $this->grantBlocks;
    }

    /**
     * Родитель.
     *
     * @return string|null Код или null.
     */
    public function getParentAbilityCode(): ?string
    {
        return $this->parentAbilityCode;
    }

    /**
     * Зоны цен.
     *
     * @return array<string, AbilityZone> Зоны.
     */
    public function getZones(): array
    {
        return $this->zones;
    }

    /**
     * Требования.
     *
     * @return array<int, array{level: int, requirements: array<int, AbilityRequirement>}> Требования.
     */
    public function getRequirements(): array
    {
        return $this->requirements;
    }

    /**
     * Эффекты действия.
     *
     * @return array<int, ActionEffect> Эффекты.
     */
    public function getActionEffects(): array
    {
        return $this->actionEffects;
    }

    /**
     * Параметры.
     *
     * @return array<int, AbilityParameter> Параметры.
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Режим атаки.
     *
     * @return string|null single, wide или null.
     */
    public function getAttackMode(): ?string
    {
        return $this->attackMode;
    }

    /**
     * Нижняя цена действия.
     *
     * @return int|null Очки или null.
     */
    public function getMinTotalActionCost(): ?int
    {
        return $this->minTotalActionCost;
    }

    /**
     * Число ударов.
     *
     * @return int|null Число или null.
     */
    public function getStrikeCount(): ?int
    {
        return $this->strikeCount;
    }

    /**
     * Удары разным оружием.
     *
     * @return bool true, если да.
     */
    public function isDistinctWeapons(): bool
    {
        return $this->distinctWeapons;
    }

    /**
     * Несколько экземпляров одного оружия.
     *
     * @return bool true, если да.
     */
    public function isSameWeapon(): bool
    {
        return $this->sameWeapon;
    }

    /**
     * Минимум экземпляров.
     *
     * @return int|null Число или null.
     */
    public function getMinWeapons(): ?int
    {
        return $this->minWeapons;
    }

    /**
     * Максимум экземпляров.
     *
     * @return int|null Число или null.
     */
    public function getMaxWeapons(): ?int
    {
        return $this->maxWeapons;
    }

    /**
     * Снять потолок родителя.
     *
     * @return bool true, если да.
     */
    public function isLiftParentMaxWeapons(): bool
    {
        return $this->liftParentMaxWeapons;
    }

    /**
     * Максимум целей.
     *
     * @return int|null Число или null.
     */
    public function getMaxTargets(): ?int
    {
        return $this->maxTargets;
    }

    /**
     * Роль в бою.
     *
     * @return string|null Код или null.
     */
    public function getCombatAction(): ?string
    {
        return $this->combatAction;
    }

    /**
     * Пик концентрации.
     *
     * @return bool true, если включён.
     */
    public function isPeakConcentration(): bool
    {
        return $this->peakConcentration;
    }

    /**
     * Фокус воли.
     *
     * @return bool true, если включён.
     */
    public function isWillFocus(): bool
    {
        return $this->willFocus;
    }

    /**
     * Долгая концентрация.
     *
     * @return bool true, если включена.
     */
    public function isLongTension(): bool
    {
        return $this->longTension;
    }

    /**
     * Множественный навык.
     *
     * @return bool true, если да.
     */
    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * Код предмета оружия.
     *
     * @return string|null Код или null.
     */
    public function getWeaponItemCode(): ?string
    {
        return $this->weaponItemCode;
    }

    /**
     * Код группы.
     *
     * @return string|null Код или null.
     */
    public function getGroupCode(): ?string
    {
        return $this->groupCode;
    }

    /**
     * Справочник домена.
     *
     * @return string|null Код или null.
     */
    public function getDomainRef(): ?string
    {
        return $this->domainRef;
    }

    /**
     * Шаблон знания.
     *
     * @return string|null Поле или null.
     */
    public function getKnowledgeTemplateField(): ?string
    {
        return $this->knowledgeTemplateField;
    }

    /**
     * Фильтр знания родителя.
     *
     * @return string|null Поле или null.
     */
    public function getParentKnowledgeField(): ?string
    {
        return $this->parentKnowledgeField;
    }

    /**
     * Сдвиг размера шага.
     *
     * @return int|null Число или null.
     */
    public function getMovementStepSizeDelta(): ?int
    {
        return $this->movementStepSizeDelta;
    }

    /**
     * Доставка попадания.
     *
     * @return HitResolution|null Доставка или null.
     */
    public function getHitResolution(): ?HitResolution
    {
        return $this->hitResolution;
    }

    /**
     * Толчок.
     *
     * @return PushSpec|null Толчок или null.
     */
    public function getPush(): ?PushSpec
    {
        return $this->push;
    }

    /**
     * Улучшение заклинания.
     *
     * @return SpellUpgrade|null Улучшение или null.
     */
    public function getSpellUpgrade(): ?SpellUpgrade
    {
        return $this->spellUpgrade;
    }

    /**
     * Насыщение.
     *
     * @return SpellSaturation|null Насыщение или null.
     */
    public function getSpellSaturation(): ?SpellSaturation
    {
        return $this->spellSaturation;
    }

    /**
     * Следующая сложность.
     *
     * @return NextCastDifficulty|null Сложность или null.
     */
    public function getNextCastDifficulty(): ?NextCastDifficulty
    {
        return $this->nextCastDifficulty;
    }

    /**
     * Касание.
     *
     * @return SpellTouch|null Касание или null.
     */
    public function getSpellTouch(): ?SpellTouch
    {
        return $this->spellTouch;
    }

    /**
     * Прикрытие союзника.
     *
     * @return CoverAlly|null Прикрытие или null.
     */
    public function getCoverAlly(): ?CoverAlly
    {
        return $this->coverAlly;
    }

    /**
     * Защита от известной атаки.
     *
     * @return KnownAttackDefense|null Защита или null.
     */
    public function getKnownAttackDefense(): ?KnownAttackDefense
    {
        return $this->knownAttackDefense;
    }

    /**
     * Улучшение удара.
     *
     * @return StrikeUpgrade|null Улучшение или null.
     */
    public function getStrikeUpgrade(): ?StrikeUpgrade
    {
        return $this->strikeUpgrade;
    }

    /**
     * Агрегат уровня.
     *
     * @return AbilityAggregate|null Агрегат или null.
     */
    public function getAggregate(): ?AbilityAggregate
    {
        return $this->aggregate;
    }

    /**
     * Производный уровень.
     *
     * @return DerivedLevel|null Уровень или null.
     */
    public function getDerivedLevel(): ?DerivedLevel
    {
        return $this->derivedLevel;
    }
}
