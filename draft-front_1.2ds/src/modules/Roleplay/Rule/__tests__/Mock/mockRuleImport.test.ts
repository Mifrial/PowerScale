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
      'excellent-hearing',
      'blind',
      'terrible-vision',
      'weak-vision',
      'sharp-vision',
      'incredible-vision',
      'excellent-vision',
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

  it('группы: контейнер type group, участники с group_code и признаком домена, без «части группы»', () => {
    for (const [code, members] of [
      ['appearance', ['repulsive', 'ugly', 'beautiful', 'gorgeous']],
      ['voice', ['mute', 'wondrous-voice']],
      [
        'hearing',
        ['deaf', 'terrible-hearing', 'weak-hearing', 'sharp-hearing', 'excellent-hearing', 'incredible-hearing'],
      ],
      ['vision', ['blind', 'terrible-vision', 'weak-vision', 'sharp-vision', 'excellent-vision', 'incredible-vision']],
    ] as const) {
      const groupRule = byCode.get(code);
      expect(groupRule?.type).toBe('ability');
      expect(groupRule?.spec).toEqual({ type: 'group', selectLimit: 1 });
      expect(groupRule?.keywordIds).toContain(42);

      for (const member of members) {
        expect(abilitySpec(member)?.group_code).toBe(code);
        expect(byCode.get(member)?.keywordIds).toContain(
          { appearance: 43, voice: 223, hearing: 224, vision: 225 }[code],
        );
        expect(byCode.get(member)?.keywordIds).not.toContain(42);
      }
    }
    expect(byCode.get('mental')).toBeUndefined();
    expect(byCode.get('feeble-minded')).toBeUndefined();
    expect(byCode.get('gifted')).toBeUndefined();
  });

  it('Быстроногий удваивает дистанцию бега грантом', () => {
    const grants = abilitySpec('fast-footed')?.grants?.[0]?.grants as Grant[] | undefined;
    expect(grants?.[0]).toMatchObject({
      type: 'process_distance_multiplier',
      ability_code: 'run',
      multiplier: 2,
    });
  });

  it('Толстые пальцы: −6 к проверке мелкой моторики от состояния', () => {
    const grants = abilitySpec('thick-fingers')?.grants?.[0]?.grants as Grant[] | undefined;
    expect(grants?.[0]).toMatchObject({
      type: 'characteristic_modify',
      characteristic_code: 'dexterity',
      amount: { type: 'fixed', value: -6 },
      source_code: 'from-state',
      check_codes: ['check-fine-motor'],
    });
    const check = byCode.get('check-fine-motor');
    expect(check?.spec).toMatchObject({
      parent_check_code: 'check-dexterity',
      characteristic_code: 'dexterity',
      allow_characteristic_override: true,
    });
  });

  it('Глухота убивает чувство Слух', () => {
    const grants = abilitySpec('deaf')?.grants?.[0]?.grants as Grant[] | undefined;
    expect(grants?.[0]).toMatchObject({
      type: 'sense_modify',
      sense_code: 'sense-hearing',
      status: 'absent',
    });
  });

  it('Слепота убивает чувство Зрение', () => {
    const grants = abilitySpec('blind')?.grants?.[0]?.grants as Grant[] | undefined;
    expect(grants?.[0]).toMatchObject({
      type: 'sense_modify',
      sense_code: 'sense-vision',
      status: 'absent',
    });
  });

  it('Немой ждёт вербальный компонент', () => {
    expect(byCode.get('mute')?.contentNote).toMatch(/вербальн/);
  });

  it('Быстроногий: contentNote про непрочитанный грант бега', () => {
    expect(byCode.get('fast-footed')?.contentNote).toMatch(/не читает/);
    expect(byCode.get('fast-footed')?.catalogSection).toBe('abilities-innate-individual');
    expect(byCode.get('thick-fingers')?.catalogSection).toBe('abilities-innate-individual');
    expect(byCode.get('magic-resistance')?.catalogSection).toBe('abilities-innate-magic-individual');
  });

  it('Внешность копит Привлекательность, Слух/Зрение — чувство', () => {
    const appearance = abilitySpec('gorgeous')?.grants?.[0]?.grants as Grant[] | undefined;
    expect(appearance?.[0]).toMatchObject({
      type: 'state_modify',
      state_code: 'attractiveness',
      amount: { type: 'fixed', value: 2 },
      source_code: 'from-appearance',
    });

    const hearing = abilitySpec('sharp-hearing')?.grants?.[0]?.grants as Grant[] | undefined;
    expect(hearing?.[0]).toMatchObject({
      type: 'sense_modify',
      sense_code: 'sense-hearing',
      amount: { type: 'fixed', value: 1 },
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
      damage_type_code: 'arcane',
      value: { type: 'parameter', parameter_code: 'x', per_unit: 1 },
      source_code: 'innate',
    });

    const magicDamage = byCode.get('magic-damage');
    expect(magicDamage?.type).toBe('damage_type');
    expect(magicDamage?.spec).toMatchObject({
      type: 'damage_type',
      forms: { genitive: 'магического урона', dative: 'магическому урону' },
    });
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

  it('Врождённое магическое ядро X: дар с табличной ценой мощи', () => {
    const rule = byCode.get('magic-core-capacity');
    expect(rule?.name).toBe('Врождённое магическое ядро');
    // Дар: признаки «Врождённая», «Характеристика», «Дар» (47) — без «Модификатор» (46).
    expect(rule?.keywordIds).toEqual(expect.arrayContaining([44, 45, 47]));
    expect(rule?.keywordIds).not.toContain(46);

    const spec = rule?.spec as AbilitySpecBase | undefined;
    expect(spec?.zones.os).toEqual({
      kind: 'parameter_table',
      parameter_code: 'x',
      costs: { '3↓': 1, '4↓': 2, '5↓': 3, '3': 4, '4': 6, '5': 8, '3↑': 12, '4↑': 16, '5↑': 20 },
    });
    expect(spec?.zones.or).toBeUndefined();
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

  it('Устрашающий вид: требует Омерзительную или Уродливую и даёт преимущество на запугивание', () => {
    const reqs = abilitySpec('intimidating')?.requirements?.[0]?.requirements;
    expect(reqs?.[0]).toMatchObject({ type: 'or' });
    const children = (reqs?.[0] as { children: unknown[] }).children;
    expect(children).toEqual([
      { type: 'has_ability', ability_code: 'repulsive' },
      { type: 'has_ability', ability_code: 'ugly' },
    ]);
    const grants = abilitySpec('intimidating')?.grants?.[0]?.grants as Grant[] | undefined;
    expect(grants?.[0]).toMatchObject({ type: 'check_advantage', amount: 1, check_codes: ['intimidation'] });
  });

  it('Пачка 3: секции, Бугай, ночное зрение, холод игнорирует защиту', () => {
    expect(byCode.get('cold-resistance')?.catalogSection).toBe('abilities-innate-individual');
    expect(byCode.get('dark-vision')?.catalogSection).toBe('abilities-innate-individual');
    expect(byCode.get('beerborn')?.catalogSection).toBe('abilities-innate-individual');
    expect(byCode.get('small-step')?.catalogSection).toBe('abilities-innate-individual');
    expect(byCode.get('big-build')?.name).toBe('Бугай');
    expect(byCode.get('big-build')?.catalogSection).toBe('abilities-innate-individual');
    expect(abilitySpec('big-build')?.zones.os).toEqual({ kind: 'array', levels_cost: [6] });
    expect(abilitySpec('big-build')?.grants?.[0]?.grants).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ characteristic_code: 'weight', amount: { type: 'fixed', value: 3 } }),
        expect.objectContaining({ characteristic_code: 'strength', amount: { type: 'fixed', value: 2 } }),
        expect.objectContaining({ characteristic_code: 'endurance', amount: { type: 'fixed', value: 1 } }),
      ]),
    );
    expect(byCode.get('dark-vision')?.name).toBe('Ночное зрение');
    expect(abilitySpec('dark-vision')?.grants?.[0]?.grants?.[0]).toMatchObject({
      type: 'sense_modify',
      sense_code: 'sense-vision',
      treat_as_good_down_to: 'minimal',
    });
    expect(byCode.get('cold')?.contentNote).toMatch(/Эффектов/);
    expect(byCode.get('cold')?.spec).toMatchObject({ defense_ignored: true });
    expect(byCode.get('magic-resistance')?.name).toBe('Сопротивление магии');
    expect(byCode.get('innate-strength')?.name).toBe('Врождённая Сила');
  });

  it('Пачка 4: врождённые характеристики, доплата, группы 1 из', () => {
    for (const code of [
      'innate-strength',
      'innate-endurance',
      'innate-dexterity',
      'innate-intellect',
      'innate-perception',
    ]) {
      expect(byCode.get(code)?.catalogSection).toBe('abilities-innate-characteristics');
      expect(byCode.get(code)?.contentNote).toMatch(/не входит в прогрессивную доплату/);
    }
    expect(byCode.get('innate-strength')?.description).toMatch(/Стойкости больше чем на 3/);
    expect(byCode.get('innate-intellect')?.description).toMatch(/−1…\+1/);
    expect(byCode.get('common-traits-surcharge')?.catalogSection).toBe('abilities-innate-common');
    expect(byCode.get('common-traits-surcharge')?.description).toMatch(/признаком «общая»/);
    expect(byCode.get('appearance')?.catalogSection).toBe('abilities-innate-common');
    expect(byCode.get('voice')?.description).toMatch(/Чудесный голос/);
    expect(byCode.get('hearing')?.description).toMatch(/Глухота/);
    expect(byCode.get('vision')?.description).toMatch(/Ночное зрение в эту группу не входит/);
  });

  it('Пачка 5: источники «От *», чувства, возраст', () => {
    expect(byCode.get('from-appearance')?.name).toBe('От внешности');
    expect(byCode.get('from-voice')?.name).toBe('От голоса');
    expect(byCode.get('from-state')?.name).toBe('От состояния');
    expect(byCode.get('from-size')?.name).toBe('От размера');
    expect(byCode.get('perfection')?.name).toBe('От совершенства');
    expect(byCode.get('character')?.name).toBe('От личности');
    expect(byCode.get('alcoholism')?.name).toBe('От алкоголизма');
    expect(byCode.get('development')?.name).toBe('От развития');
    for (const code of [
      'from-appearance',
      'from-voice',
      'from-state',
      'from-size',
      'perfection',
      'character',
      'alcoholism',
      'development',
    ]) {
      expect(byCode.get(code)?.catalogSection).toBe('basic-sources');
    }
    expect(byCode.get('sense-hearing')?.catalogSection).toBe('basic-senses');
    expect(byCode.get('sense-vision')?.catalogSection).toBe('basic-senses');
    expect(byCode.get('age')?.description).toMatch(/только в попапе/);
    expect(byCode.get('alcoholism')?.contentNote).toMatch(/не реализован/);
  });

  it('Пачка 6: заглушки навыков удалены, личность в одном корне, живые гранты', () => {
    expect(byCode.get('literacy')).toBeUndefined();
    expect(byCode.get('communication-mastery')).toBeUndefined();
    expect(byCode.get('sociability')?.name).toBe('Личность: Общительность');
    expect(byCode.get('sociability')?.catalogSection).toBe('abilities-personality');
    expect(byCode.get('attentiveness')?.name).toBe('Личность: Внимательность');
    expect(byCode.get('withdrawn')?.description).toMatch(/Красноречию/);
    expect(abilitySpec('withdrawn')?.grants?.[0]?.grants?.[0]).toMatchObject({
      type: 'characteristic_modify',
      characteristic_code: 'communication',
      amount: { type: 'ability_level', ability_code: 'razvitie-obscheniya', offset: -3 },
    });
    expect(abilitySpec('bookworm')?.grants?.[0]?.grants).toEqual([
      expect.objectContaining({
        type: 'skill_study',
        ability_codes: ['znanie', 'pismennost'],
        max_level: 1,
        paid_cost: 0,
        max_instances: 1,
      }),
    ]);
    expect(abilitySpec('empathic')?.grants?.[0]?.grants).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ type: 'ability', ability_code: 'pronitsatelnost' }),
        expect.objectContaining({ type: 'check_advantage', check_codes: ['check-insight'] }),
      ]),
    );
    expect(abilitySpec('pedant')?.grants?.[0]?.grants?.[0]).toMatchObject({
      type: 'ability',
      ability_code: 'razvitie-vnimatelnosti',
      level: 2,
    });
    expect(abilitySpec('grudge-holder')?.grants?.[0]?.grants?.[0]).toMatchObject({
      type: 'ability',
      ability_code: 'razvitie-pamyati',
    });
    expect(byCode.get('sociable')?.catalogSection).toBe('abilities-personality');
  });

  it('Чудесный голос: +1 Привлекательность и преимущество на музицирование голосом', () => {
    const grants = abilitySpec('wondrous-voice')?.grants?.[0]?.grants as Grant[] | undefined;
    expect(grants).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          type: 'state_modify',
          state_code: 'attractiveness',
          amount: { type: 'fixed', value: 1 },
          source_code: 'from-voice',
        }),
        expect.objectContaining({ type: 'check_advantage', amount: 1, check_codes: ['voice-music'] }),
      ]),
    );
  });

  it('механика «Общие черты»: purchase_surcharge с filter keyword common', () => {
    const mechanicRule = byCode.get('common-traits-surcharge');
    expect(mechanicRule?.mechanicId).toBe(4);
    expect(mechanicRule?.mechanicPayload).toEqual({
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
    for (const featureCode of ['sociable', 'empathic', 'pedant', 'grudge-holder']) {
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
