import { describe, it, expect } from 'vitest';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { mockRuleImport } from '@/modules/Roleplay/Rule/Mock/mockRuleImport';
import type { AbilitySpecBase } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecBase';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';

const byCode = new Map(ruleCatalog.map((r) => [r.code, r]));

const abilitySpec = (code: string): AbilitySpecBase | undefined => {
  const rule = byCode.get(code);

  return rule?.type === 'ability' ? (rule.spec as AbilitySpecBase | undefined) : undefined;
};

describe('mockRuleImport (S2)', () => {
  it('содержит черты набора S2', () => {
    const codes = mockRuleImport.map((r) => r.code).sort();
    for (const code of [
      'fast-footed',
      'thick-fingers',
      'magic-resistance',
      'repulsive',
      'ugly',
      'beautiful',
      'gorgeous',
      'mute',
      'wondrous-voice',
      'deaf',
      'terrible-hearing',
      'weak-hearing',
      'sharp-hearing',
      'incredible-hearing',
      'blind',
      'terrible-vision',
      'weak-vision',
      'sharp-vision',
      'incredible-vision',
      'feeble-minded',
      'gifted',
      'intimidating',
    ]) {
      expect(codes).toContain(code);
    }
  });

  it('черты «Общие» несут признак common, уникальные — нет', () => {
    const common = byCode.get('beautiful');
    expect(common?.keywordIds).toContain(20);

    const unique = byCode.get('fast-footed');
    expect(unique?.keywordIds).not.toContain(20);
  });

  it('группы: группирующее правило type group с selectLimit, участники несут group_code и признак «часть группы»', () => {
    for (const [code, members] of [
      ['appearance', ['repulsive', 'ugly', 'beautiful', 'gorgeous']],
      ['voice', ['mute', 'wondrous-voice']],
      ['hearing', ['deaf', 'terrible-hearing', 'weak-hearing', 'sharp-hearing', 'incredible-hearing']],
      ['vision', ['blind', 'terrible-vision', 'weak-vision', 'sharp-vision', 'incredible-vision']],
      ['mental', ['feeble-minded', 'gifted']],
    ] as const) {
      const groupRule = byCode.get(code);
      expect(groupRule?.type).toBe('ability');
      expect(groupRule?.spec).toEqual({ type: 'group', selectLimit: 1 });
      expect(groupRule?.keywordIds).toContain(42);

      for (const member of members) {
        expect(abilitySpec(member)?.group_code).toBe(code);
        expect(byCode.get(member)?.keywordIds).toContain(43);
      }
    }
  });

  it('Внешность модифицирует Общение, Слух/Зрение — чувство → Внимательность', () => {
    const appearance = abilitySpec('gorgeous')?.grants?.[0]?.grants as Grant[] | undefined;
    expect(appearance?.[0]).toMatchObject({
      type: 'characteristic_modify',
      characteristic_code: 'communication',
      amount: { type: 'fixed', value: 2 },
    });

    const hearing = abilitySpec('sharp-hearing')?.grants?.[0]?.grants as Grant[] | undefined;
    expect(hearing?.[0]).toMatchObject({
      type: 'sense_modify',
      sense_code: 'sense-hearing',
      amount: { type: 'fixed', value: 3 },
      source_code: 'perfection',
    });

    const senseHearing = byCode.get('sense-hearing');
    expect(senseHearing?.type).toBe('sense');
    const senseVision = byCode.get('sense-vision');
    expect(senseVision?.type).toBe('sense');
  });

  it('Сопротивление магии X: параметрическая цена и resistance-грант', () => {
    const spec = abilitySpec('magic-resistance');
    expect(spec?.zones.os).toEqual({ kind: 'parameter', parameter_code: 'x', per_unit: 2 });
    expect(spec?.parameters?.[0]).toMatchObject({ code: 'x', resolution: 'purchase' });

    const grants = spec?.grants?.[0]?.grants as Grant[] | undefined;
    expect(grants?.[0]).toMatchObject({
      type: 'resistance',
      damage_type_code: 'magic-damage',
      value: { type: 'parameter', parameter_code: 'x', per_unit: 2 },
      source_code: 'innate',
    });

    const magicDamage = byCode.get('magic-damage');
    expect(magicDamage?.type).toBe('damage_type');
  });

  it('Врождённые характеристики (S8): табличная цена по X и грант модификатора', () => {
    const strength = abilitySpec('innate-strength');
    expect(strength?.zones.os).toEqual({
      kind: 'parameter_table',
      parameter_code: 'x',
      costs: { '-3': -3, '-2': -2, '-1': -1, '1': 2, '2': 4, '3': 8 },
    });
    expect(strength?.parameters?.[0]).toMatchObject({
      code: 'x',
      default: { base: 0, size: 0 },
      min: { base: -3, size: 0 },
      max: { base: 3, size: 0 },
      linked: { ability_code: 'innate-endurance', parameter_code: 'x', max_delta: 3 },
    });

    const grants = strength?.grants?.[0]?.grants as Grant[] | undefined;
    expect(grants?.[0]).toMatchObject({
      type: 'characteristic_modify',
      characteristic_code: 'strength',
      amount: { type: 'parameter', parameter_code: 'x', per_unit: 1 },
      source_code: 'innate',
    });
  });

  it('Врождённые черты несут признаки «Врождённая», «Характеристика» и «Модификатор» (44, 45, 46)', () => {
    for (const code of [
      'innate-strength',
      'innate-endurance',
      'innate-dexterity',
      'innate-intellect',
      'innate-perception',
    ]) {
      const rule = byCode.get(code);
      expect(rule?.keywordIds).toEqual(expect.arrayContaining([44, 45, 46]));
    }
  });

  it('Врождённая Магия X (S9): дар со значением-потолком, размерная цена из дока', () => {
    const rule = byCode.get('magic-potential');
    expect(rule?.name).toBe('Врождённая Магия X');
    // Дар: признаки «Врождённая», «Характеристика», «Дар» (47) — без «Модификатор» (46).
    expect(rule?.keywordIds).toEqual(expect.arrayContaining([44, 45, 47]));
    expect(rule?.keywordIds).not.toContain(46);

    const spec = rule?.spec as AbilitySpecBase | undefined;
    expect(spec?.zones.os).toEqual({
      kind: 'parameter_table',
      parameter_code: 'x',
      costs: { '3↓': 1, '4↓': 2, '5↓': 3, '3': 4, '4': 6, '5': 8, '3↑': 12, '4↑': 16, '5↑': 20 },
    });
    expect(spec?.parameters?.[0]).toMatchObject({
      code: 'x',
      default: { base: 3, size: 0 },
      min: { base: 3, size: -1 },
      max: { base: 5, size: 1 },
    });
  });

  it('Интеллект/Восприятие: модификатор от Телосложения ограничен ±1', () => {
    const intellect = abilitySpec('innate-intellect')?.parameters?.[0];
    expect(intellect).toMatchObject({ min: { base: -1, size: 0 }, max: { base: 1, size: 0 } });
    const perception = abilitySpec('innate-perception')?.parameters?.[0];
    expect(perception).toMatchObject({ min: { base: -1, size: 0 }, max: { base: 1, size: 0 } });
    const dexterity = abilitySpec('innate-dexterity')?.parameters?.[0];
    expect(dexterity).toMatchObject({ min: { base: -3, size: 0 }, max: { base: 3, size: 0 } });
  });

  it('Устрашающий вид: требует Омерзительную или Уродливую', () => {
    const reqs = abilitySpec('intimidating')?.requirements?.[0]?.requirements;
    expect(reqs?.[0]).toMatchObject({ type: 'or' });
    const children = (reqs?.[0] as { children: unknown[] }).children;
    expect(children).toEqual([
      { type: 'has_ability', ability_code: 'repulsive' },
      { type: 'has_ability', ability_code: 'ugly' },
    ]);
  });

  it('Слабоумный требует Интеллект ≥ 3 и даёт −3 к Интеллекту', () => {
    const reqs = abilitySpec('feeble-minded')?.requirements?.[0]?.requirements;
    expect(reqs?.[0]).toMatchObject({
      type: 'characteristic_value',
      characteristic_code: 'intellect',
      min: { base: 3, size: 0 },
    });

    const grants = abilitySpec('feeble-minded')?.grants?.[0]?.grants as Grant[] | undefined;
    expect(grants?.[0]).toMatchObject({
      type: 'characteristic_modify',
      characteristic_code: 'intellect',
      amount: { type: 'fixed', value: -3 },
    });
  });

  it('механика «Общие черты»: purchase_surcharge с filter keyword common', () => {
    const mechanicRule = byCode.get('common-traits-surcharge');
    expect(mechanicRule?.mechanicId).toBe(4);
    expect(mechanicRule?.mechanic_payload).toEqual({
      type: 'purchase_surcharge',
      filter: { keyword_code: 'common' },
      free_count: 2,
      surcharge: 2,
    });
  });
});

describe('mockRuleImport (S11, Личность)', () => {
  const ageSpec = byCode.get('age')?.spec as
    { type: string; ages: { name: string; ol: number; featureLimit: number }[] } | undefined;

  it('правило «Возраст»: тип age, 9 ступеней с ОЛ и лимитом особенностей', () => {
    const rule = byCode.get('age');
    expect(rule?.type).toBe('age');
    expect(ageSpec?.type).toBe('age');
    expect(ageSpec?.ages).toHaveLength(9);
    expect(ageSpec?.ages[4]).toEqual({ name: 'Молодой', ol: 3, featureLimit: 3, effects: [] });
    expect(ageSpec?.ages[8]).toMatchObject({ name: 'Старый', ol: 7, featureLimit: 4 });
  });

  it('виды несут таблицу лет (age_years) для всех четырёх видов', () => {
    for (const code of ['human', 'elves', 'dwarves', 'orcs']) {
      const rule = byCode.get(code);
      const spec = rule?.spec as { age_years?: { age: string; ageStart: number; ageEnd: number }[] } | undefined;
      expect(spec?.age_years?.length, code).toBeGreaterThan(0);
      expect(spec?.age_years?.[0]).toMatchObject({ age: 'Младенец', ageStart: 0 });
    }
  });

  it('15 особенностей личности: зона ol, отрицательные стоимости дают ОЛ', () => {
    const olFeatures = mockRuleImport.filter(
      (r) => r.type === 'ability' && r.code !== 'age' && (r.spec as { zones?: Record<string, unknown> })?.zones?.['ol'],
    );
    expect(olFeatures).toHaveLength(15);

    const pauper = abilitySpec('pauper');
    expect(pauper?.zones?.ol).toEqual({ kind: 'array', levels_cost: [-1] });
    const rich = abilitySpec('rich');
    expect(rich?.zones?.ol).toEqual({ kind: 'array', levels_cost: [3] });
  });

  it('богатство: деньги-грант от лимита (fixed/percent/apply) и признак wealth', () => {
    const byCodeLocal = new Map(mockRuleImport.map((r) => [r.code, r]));
    const rich = byCodeLocal.get('rich');
    expect(rich?.keywordIds).toContain(50);

    const grants = abilitySpec('rich')?.grants?.[0]?.grants as Grant[] | undefined;
    expect(grants?.[0]).toEqual({ type: 'money', fixed: 400, percent: 400, apply: 'max' });

    const pauperGrants = abilitySpec('pauper')?.grants?.[0]?.grants as Grant[] | undefined;
    expect(pauperGrants?.[0]).toEqual({ type: 'money', fixed: 10, percent: 10, apply: 'min' });
  });

  it('особенности, дающие навыки, ссылаются на существующие навыки; группы «1 из»', () => {
    for (const featureCode of ['sociable', 'bookworm', 'empathic', 'pedant', 'grudge-holder']) {
      const grants = abilitySpec(featureCode)?.grants?.[0]?.grants as Grant[] | undefined;
      const skills = (grants ?? []).filter((grant) => grant.type === 'ability');
      for (const skill of skills) {
        expect(byCode.get(skill.ability_code), `${featureCode} → ${skill.ability_code}`).toBeDefined();
      }
    }
    for (const groupCode of ['sociability', 'attentiveness', 'wealth']) {
      expect(byCode.get(groupCode)?.spec).toMatchObject({ type: 'group', selectLimit: 1 });
    }
  });
});
