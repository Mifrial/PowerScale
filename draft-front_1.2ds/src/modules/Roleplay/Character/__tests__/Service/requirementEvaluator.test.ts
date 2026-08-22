import { describe, it, expect } from 'vitest';
import type { CharacterSnapshot } from '@/modules/Roleplay/Character/Dto/Editor/CharacterSnapshot';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement';
import { RequirementEvaluator } from '@/modules/Roleplay/Character/Service/RequirementEvaluator';

const dim = (base: number, size = 0): DimensionalNumberValue => ({ base, size });

const snapshot: CharacterSnapshot = {
  abilityLevels: new Map([
    ['melee-fighting', 2],
    ['keen-hearing', 1],
  ]),
  abilityKeywords: new Map([['melee-fighting', new Set(['combat', 'skill'])]]),
  characteristicValues: new Map([
    ['strength', dim(3)],
    ['dexterity', dim(3, 1)],
  ]),
  resourceLimits: new Map([['action-points', 2]]),
  keywordCodes: new Set(['combat', 'magic']),
};

const evaluator = new RequirementEvaluator();

// Тот же снимок, но с человекочитаемыми именами (как их наполняет buildSnapshot).
const namedSnapshot: CharacterSnapshot = {
  ...snapshot,
  abilityNames: new Map([
    ['melee-fighting', 'Ближний бой'],
    ['repulsive', 'Омерзительная'],
    ['ugly', 'Уродливая'],
  ]),
  keywordNames: new Map([
    ['combat', 'Бой'],
    ['stealth', 'Скрытность'],
  ]),
  characteristicNames: new Map([
    ['strength', 'Сила'],
    ['dexterity', 'Ловкость'],
  ]),
  resourceNames: new Map([['action-points', 'Очки действия']]),
};

describe('RequirementEvaluator', () => {
  it('has_ability: выполнено при достаточном уровне, иначе причина', () => {
    expect(evaluator.evaluate({ type: 'has_ability', ability_code: 'melee-fighting', min_level: 2 }, snapshot)).toBe(
      true,
    );
    expect(evaluator.evaluate({ type: 'has_ability', ability_code: 'melee-fighting', min_level: 3 }, snapshot)).toBe(
      false,
    );
    expect(
      evaluator.firstFailure([{ type: 'has_ability', ability_code: 'melee-fighting', min_level: 3 }], snapshot),
    ).toBe('требуется способность «melee-fighting» уровня 3');
  });

  it('has_ability без min_level — уровень 1', () => {
    expect(evaluator.evaluate({ type: 'has_ability', ability_code: 'melee-fighting' }, snapshot)).toBe(true);
    expect(evaluator.evaluate({ type: 'has_ability', ability_code: 'missing' }, snapshot)).toBe(false);
  });

  it('has_keyword: признак у персонажа (раса/способности)', () => {
    expect(evaluator.evaluate({ type: 'has_keyword', keyword_code: 'combat' }, snapshot)).toBe(true);
    expect(evaluator.evaluate({ type: 'has_keyword', keyword_code: 'stealth' }, snapshot)).toBe(false);
  });

  it('has_ability_keyword: N взятых способностей с признаком', () => {
    expect(evaluator.evaluate({ type: 'has_ability_keyword', keyword_code: 'combat', min_count: 1 }, snapshot)).toBe(
      true,
    );
    expect(evaluator.evaluate({ type: 'has_ability_keyword', keyword_code: 'combat', min_count: 2 }, snapshot)).toBe(
      false,
    );
  });

  it('characteristic_value: размерное сравнение (3↑ = 6 >= 3)', () => {
    expect(
      evaluator.evaluate({ type: 'characteristic_value', characteristic_code: 'dexterity', min: dim(3) }, snapshot),
    ).toBe(true);
    expect(
      evaluator.evaluate({ type: 'characteristic_value', characteristic_code: 'dexterity', min: dim(5) }, snapshot),
    ).toBe(true);
    expect(
      evaluator.evaluate({ type: 'characteristic_value', characteristic_code: 'dexterity', min: dim(7) }, snapshot),
    ).toBe(false);
    expect(
      evaluator.evaluate({ type: 'characteristic_value', characteristic_code: 'strength', min: dim(4) }, snapshot),
    ).toBe(false);
  });

  it('resource_limit: наличие и лимит (числовой и размерный минимум)', () => {
    expect(evaluator.evaluate({ type: 'resource_limit', resource_code: 'action-points' }, snapshot)).toBe(true);
    expect(evaluator.evaluate({ type: 'resource_limit', resource_code: 'action-points', min: 2 }, snapshot)).toBe(true);
    expect(evaluator.evaluate({ type: 'resource_limit', resource_code: 'action-points', min: 3 }, snapshot)).toBe(
      false,
    );
    expect(evaluator.evaluate({ type: 'resource_limit', resource_code: 'missing' }, snapshot)).toBe(false);
  });

  it('and: все части; or: хотя бы одна', () => {
    const and: Requirement = {
      type: 'and',
      children: [
        { type: 'has_keyword', keyword_code: 'combat' },
        { type: 'has_ability', ability_code: 'melee-fighting', min_level: 1 },
      ],
    };
    expect(evaluator.evaluate(and, snapshot)).toBe(true);

    const or: Requirement = {
      type: 'or',
      children: [
        { type: 'has_keyword', keyword_code: 'stealth' },
        { type: 'has_ability', ability_code: 'melee-fighting', min_level: 1 },
      ],
    };
    expect(evaluator.evaluate(or, snapshot)).toBe(true);
  });

  it('firstFailure: первая причина в списке (неявное И)', () => {
    const requirements: Requirement[] = [
      { type: 'has_keyword', keyword_code: 'stealth' },
      { type: 'has_ability', ability_code: 'melee-fighting', min_level: 1 },
    ];
    expect(evaluator.firstFailure(requirements, snapshot)).toBe('требуется признак «stealth»');
  });

  it('человекочитаемые имена в причинах (снимок с именами)', () => {
    expect(
      evaluator.firstFailure([{ type: 'has_ability', ability_code: 'melee-fighting', min_level: 3 }], namedSnapshot),
    ).toBe('требуется способность «Ближний бой» уровня 3');
    expect(evaluator.firstFailure([{ type: 'has_keyword', keyword_code: 'stealth' }], namedSnapshot)).toBe(
      'требуется признак «Скрытность»',
    );
    expect(
      evaluator.firstFailure([{ type: 'has_ability_keyword', keyword_code: 'stealth', min_count: 2 }], namedSnapshot),
    ).toBe('требуется признак «Скрытность» на 2 способностях');
    expect(
      evaluator.firstFailure(
        [{ type: 'characteristic_value', characteristic_code: 'strength', min: dim(4) }],
        namedSnapshot,
      ),
    ).toBe('требуется характеристика «Сила» от 4');
    expect(
      evaluator.firstFailure([{ type: 'resource_limit', resource_code: 'action-points', min: 3 }], namedSnapshot),
    ).toBe('требуется лимит ресурса «Очки действия» от 3');
    expect(evaluator.firstFailure([{ type: 'resource_limit', resource_code: 'missing' }], namedSnapshot)).toBe(
      'требуется ресурс «missing»',
    );
  });

  it('or из has_ability: компактное перечисление альтернатив', () => {
    const or: Requirement = {
      type: 'or',
      children: [
        { type: 'has_ability', ability_code: 'repulsive' },
        { type: 'has_ability', ability_code: 'ugly' },
      ],
    };
    expect(evaluator.firstFailure([or], namedSnapshot)).toBe(
      'нужна одна из способностей: «Омерзительная», «Уродливая»',
    );
  });

  it('or со смешанными детьми: джойн причин', () => {
    const or: Requirement = {
      type: 'or',
      children: [
        { type: 'has_keyword', keyword_code: 'stealth' },
        { type: 'has_ability', ability_code: 'repulsive' },
      ],
    };
    expect(evaluator.firstFailure([or], namedSnapshot)).toBe(
      'требуется признак «Скрытность» или требуется способность «Омерзительная» уровня 1',
    );
  });

  it('min_weapon_mastery: владение оружием по тэгу', () => {
    const wsSnapshot: CharacterSnapshot = {
      ...snapshot,
      weaponProficiencyLevels: new Map([['fam-kinzhal-nozh', 3]]),
      weaponFamilyTags: new Map([['dagger', new Set(['fam-kinzhal-nozh'])]]),
    };

    // Тэг есть, уровень достаточный
    expect(evaluator.evaluate({ type: 'min_weapon_mastery', keyword_code: 'dagger', min_level: 2 }, wsSnapshot)).toBe(
      true,
    );

    // Тэг есть, уровень недостаточный
    expect(evaluator.evaluate({ type: 'min_weapon_mastery', keyword_code: 'dagger', min_level: 4 }, wsSnapshot)).toBe(
      false,
    );

    // Тэга нет ни в одной семье
    expect(evaluator.evaluate({ type: 'min_weapon_mastery', keyword_code: 'bow', min_level: 1 }, wsSnapshot)).toBe(
      false,
    );

    // Нет данных о владении — не проходит
    expect(
      evaluator.evaluate({ type: 'min_weapon_mastery', keyword_code: 'dagger', min_level: 1 }, {
        ...snapshot,
      } as CharacterSnapshot),
    ).toBe(false);
  });
});
