import { describe, it, expect } from 'vitest';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { weaponProfileViews, itemParamsView } from '@/modules/Roleplay/Character/Utils/itemWeaponProfiles';
import type { FormulaContext } from '@/modules/Roleplay/Character/Dto/FormulaContext';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';

const specOf = (name: string): ItemSpec => {
  const rule = ruleCatalog.find((r) => r.name === name);
  expect(rule, name).toBeDefined();

  return rule!.spec as ItemSpec;
};

const characteristicValues = new Map<string, DimensionalNumberValue>([
  ['strength', { base: 5, size: 0 }],
  ['dexterity', { base: 4, size: 0 }],
]);

const context: FormulaContext = {
  characteristicValues,
  abilityLevels: new Map(),
};

const resolveName = (code: string): string | null => {
  const names: Record<string, string> = { strength: 'Сила', dexterity: 'Ловкость' };

  return names[code] ?? null;
};

describe('weaponProfileViews (панель предмета редактора)', () => {
  it('пата: два профиля удара, значения урона/пробития от текущей Силы', () => {
    const views = weaponProfileViews(specOf('Пата'), context, resolveName);

    expect(views).toHaveLength(2);
    const [rubyashchiy, kolyushchiy] = views;

    // рубящий: [Сила − 4] → Сила 5 даёт 4↓ (значение 2), пробитие [Сила − 3] → 5↓, точность 4, дистанция ½ ипари (1↓)
    expect(rubyashchiy?.profileTypeLabel).toBe('Удар');
    expect(rubyashchiy?.damageLabel).toBe('4↓ рубящего');
    expect(rubyashchiy?.damageFormula).toBe('Сила − 4');
    expect(rubyashchiy?.penetrationLabel).toBe('5↓ пробития');
    expect(rubyashchiy?.penetrationFormula).toBe('Сила − 3');
    expect(rubyashchiy?.accuracyLabel).toBe('4');
    expect(rubyashchiy?.distanceLabel).toBe('1↓');
    expect(rubyashchiy?.falloffLabel).toBeNull();

    // колющий: [Сила − 3] → 5↓ колющего, пробитие [Сила − 2] → 3, точность 3
    expect(kolyushchiy?.damageLabel).toBe('5↓ колющего');
    expect(kolyushchiy?.damageFormula).toBe('Сила − 3');
    expect(kolyushchiy?.penetrationLabel).toBe('3 пробития');
    expect(kolyushchiy?.accuracyLabel).toBe('3');
  });

  it('ручной арбалет: фикс. урон/пробитие (без формулы в скобках), дистанция от базы действия (3×10), дальнобойность', () => {
    const [shoot] = weaponProfileViews(specOf('Ручной арбалет'), context, resolveName);

    expect(shoot?.profileTypeLabel).toBe('Выстрел');
    expect(shoot?.damageLabel).toBe('3 колющего');
    expect(shoot?.damageFormula).toBeNull();
    expect(shoot?.penetrationLabel).toBe('0 пробития');
    expect(shoot?.penetrationFormula).toBeNull();
    expect(shoot?.accuracyLabel).toBe('3');
    expect(shoot?.distanceLabel).toBe('30');
    expect(shoot?.falloffLabel).toBe('10');
  });

  it('маленький щит: атакующий профиль из ShieldBlock.weapon_profiles', () => {
    const [strike] = weaponProfileViews(specOf('Маленький щит'), context, resolveName);

    expect(strike?.profileTypeLabel).toBe('Удар');
    expect(strike?.damageLabel).toBe('4 дробящего');
    expect(strike?.damageFormula).toBe('Сила − 1');
    expect(strike?.penetrationLabel).toBe('0 пробития');
    expect(strike?.accuracyLabel).toBe('4');
    expect(strike?.distanceLabel).toBe('0');
  });

  it('не-оружие (доспех) — пусто', () => {
    expect(weaponProfileViews(specOf('Латный доспех'), context, resolveName)).toEqual([]);
    expect(weaponProfileViews(undefined, context, resolveName)).toEqual([]);
  });
});

describe('itemParamsView (параметры предмета)', () => {
  it('пата (оружие): вес, мин. сила, прочность, защита/эффективность блокирования', () => {
    const params = itemParamsView(specOf('Пата'), characteristicValues, ruleCatalog);

    expect(params).toEqual({
      weightLabel: '1 кг',
      minStrengthLabel: '4',
      durabilityLabel: '5↑⁴',
      blockDefenseLabel: '5',
      blockEfficiencyLabel: '4',
      characteristicLimitsLabel: null,
      resistanceLabels: [],
      maxAgilityLabel: null,
      strengthPenaltyLabel: null,
      defenseLines: [],
    });
  });

  it('ручной арбалет: параметры из WeaponBlock', () => {
    const params = itemParamsView(specOf('Ручной арбалет'), characteristicValues, ruleCatalog);

    expect(params?.weightLabel).toBe('0.8 кг');
    expect(params?.minStrengthLabel).toBe('4');
    expect(params?.blockDefenseLabel).toBe('4');
    expect(params?.blockEfficiencyLabel).toBe('3');
  });

  it('маленький щит: блок + макс. Ловкость/Реакция (формула [Сила])', () => {
    const params = itemParamsView(specOf('Маленький щит'), characteristicValues, ruleCatalog);

    expect(params?.weightLabel).toBe('1.5 кг');
    expect(params?.minStrengthLabel).toBe('4');
    expect(params?.durabilityLabel).toBe('3↑');
    expect(params?.blockDefenseLabel).toBe('6');
    expect(params?.blockEfficiencyLabel).toBe('5');
    expect(params?.characteristicLimitsLabel).toBe('Макс. Ловкость/Реакция: 5 (Сила)');
    expect(params?.defenseLines).toEqual([]);
  });

  it('латный доспех: вес, макс. ловкость, штраф к силе и слои защиты', () => {
    const params = itemParamsView(specOf('Латный доспех'), characteristicValues, ruleCatalog);

    expect(params?.weightLabel).toBe('25 кг');
    expect(params?.maxAgilityLabel).toBe('4↓²');
    expect(params?.strengthPenaltyLabel).toBe('-2');
    expect(params?.defenseLines).toEqual([
      { defense: '12', sourceLabel: 'доспеха', durability: 3 },
      { defense: '3', sourceLabel: 'доспеха', durability: 6 },
    ]);
    expect(params?.minStrengthLabel).toBeNull();
    expect(params?.blockDefenseLabel).toBeNull();
  });

  it('не-снаряжение (зелье/undefined) — null', () => {
    expect(itemParamsView(specOf('Зелье концентрации'), characteristicValues, ruleCatalog)).toBeNull();
    expect(itemParamsView(undefined, characteristicValues, ruleCatalog)).toBeNull();
  });
});
