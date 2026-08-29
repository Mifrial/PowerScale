import { describe, it, expect } from 'vitest';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import { CharacterEditorService } from '@/modules/Roleplay/Character/Service/CharacterEditorService';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { weaponProficiencyService } from '@/modules/Roleplay/Character/Service/Instance/weaponProficiencyService';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';

const service = new CharacterEditorService();
const config: CharacterCreationConfig = { osTotal: 20, orTotal: 12, moneyBudget: 100 };

const keywords: Keyword[] = [
  { id: 20, code: 'common', name: 'Общая', active: true },
  { id: 31, code: 'racial', name: 'Расовая', active: true },
  { id: 21, code: 'human', name: 'Человек', active: true },
  { id: 44, code: 'innate', name: 'Врождённая', active: true },
  { id: 45, code: 'characteristic', name: 'Характеристика', active: true },
  { id: 46, code: 'modifier', name: 'Модификатор', active: true },
  { id: 47, code: 'gift', name: 'Дар', active: true },
  { id: 50, code: 'wealth', name: 'Богатство', active: true },
  { id: 48, code: 'sociability', name: 'Общительность', active: true },
  { id: 49, code: 'attentiveness', name: 'Внимательность', active: true },
  { id: 51, code: 'memory', name: 'Память', active: true },
  { id: 52, code: 'insight', name: 'Проницательность', active: true },
  { id: 53, code: 'reaction', name: 'Реакция', active: true },
  { id: 54, code: 'maneuver', name: 'Манёвр', active: true },
  { id: 56, code: 'method-perception', name: 'Метод развития восприятия', active: true },
  { id: 57, code: 'method-intellect', name: 'Метод развития интеллекта', active: true },
  { id: 58, code: 'method-communication', name: 'Метод развития общения', active: true },
  { id: 59, code: 'section-medicine', name: 'Медицина', active: true },
  { id: 64, code: 'section-melee', name: 'Ближний бой', active: true },
  { id: 68, code: 'throwing', name: 'Метание', active: true },
];

function makeBuild(overrides: Partial<CharacterBuild> = {}): CharacterBuild {
  return {
    name: 'Тест',
    shortDescription: null,
    fullDescription: null,
    spaceId: 1,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    raceRuleId: null,
    characteristicPurchases: [],
    abilities: [],
    resources: [],
    inventory: [],
    states: [],
    money: 0,
    ageYears: null,
    olTotal: 0,
    ...overrides,
  };
}

describe('CharacterEditorService с каталогом правил (интеграция)', () => {
  it('вид human: расы-дети несут cost_os и характеристики', () => {
    const human = ruleCatalog.find((r) => r.code === 'human');
    expect(human?.type).toBe('species');

    const alierets = ruleCatalog.find((r) => r.code === 'alierets');
    expect(alierets?.type).toBe('race');
    expect((alierets?.spec as { parent_race_code?: string | null } | undefined)?.parent_race_code).toBe('human');
  });

  it('раса human (acelatl): purchased/фикс характеристики, стоимость 0', () => {
    const rule = ruleCatalog.find((r) => r.code === 'acelatl');
    const model = service.build(makeBuild({ raceRuleId: rule?.id ?? '' }), ruleCatalog, config);

    expect(model.race).toMatchObject({ name: 'Ацелатль', costOs: 0 });
    expect(model.characteristics.map((c) => c.code).sort()).toEqual([
      'attention',
      'communication',
      'dexterity',
      'endurance',
      'intellect',
      'melee-combat',
      'memory',
      'perception',
      'ranged-combat',
      'reaction',
      'reasoning',
      'strength',
      'weight',
      'willpower',
    ]);
    expect(model.characteristics.find((c) => c.code === 'endurance')?.base).toEqual({ base: 5, size: 0 });
    expect(model.characteristics.find((c) => c.code === 'strength')?.base).toEqual({ base: 3, size: 0 });
    expect(model.budgets.os.spent).toBe(0);
    const fast = model.abilities.find((a) => a.code === 'fast-footed');
    expect(fast?.racial).toBe(true);
  });

  it('раса aeron: cost_os 12, характеристики с размерностью', () => {
    const rule = ruleCatalog.find((r) => r.code === 'aeron');
    const model = service.build(makeBuild({ raceRuleId: rule?.id ?? '' }), ruleCatalog, config);

    expect(model.race).toMatchObject({ costOs: 12 });
    expect(model.budgets.os.spent).toBe(12);
    expect(model.characteristics.find((c) => c.code === 'memory')?.base).toEqual({ base: 3, size: 1 });
    expect(model.characteristics.find((c) => c.code === 'reaction')?.base).toEqual({ base: 3, size: -1 });
  });

  it('производные характеристики вычисляются из баз (Восприятие = min(Внимательность, Реакция))', () => {
    const aeron = service.build(
      makeBuild({ raceRuleId: ruleCatalog.find((r) => r.code === 'aeron')?.id ?? '' }),
      ruleCatalog,
      config,
    );

    expect(aeron.characteristics.find((c) => c.code === 'perception')?.value).toEqual({ base: 3, size: -1 });
    expect(aeron.characteristics.find((c) => c.code === 'perception')?.modifiers).toEqual([]);
    expect(aeron.characteristics.find((c) => c.code === 'intellect')?.value).toEqual({ base: 3, size: 0 });
  });

  it('орки: «Интеллект 5↓» из баз Память/Мышление 5↓, модификатор к производной идёт на базы', () => {
    const orgul = service.build(
      makeBuild({ raceRuleId: ruleCatalog.find((r) => r.code === 'orgul')?.id ?? '' }),
      ruleCatalog,
      config,
    );

    expect(orgul.characteristics.find((c) => c.code === 'memory')?.base).toEqual({ base: 5, size: -1 });
    expect(orgul.characteristics.find((c) => c.code === 'reasoning')?.base).toEqual({ base: 5, size: -1 });
    expect(orgul.characteristics.find((c) => c.code === 'intellect')?.value).toEqual({ base: 5, size: -1 });

    // «Врождённый Интеллект X» — модификатор к Памяти и Мышлению (а не напрямую к производной).
    const rule = ruleCatalog.find((r) => r.code === 'innate-intellect')!;
    const boosted = service.build(
      makeBuild({
        raceRuleId: ruleCatalog.find((r) => r.code === 'orgul')?.id ?? '',
        abilities: [{ ruleId: rule.id, level: 1, parameters: { x: { base: 1, size: 0 } } }],
      }),
      ruleCatalog,
      config,
      keywords,
    );
    const memory = boosted.characteristics.find((c) => c.code === 'memory');
    const reasoning = boosted.characteristics.find((c) => c.code === 'reasoning');
    const intellect = boosted.characteristics.find((c) => c.code === 'intellect');
    expect(memory?.modifiers.length).toBe(1);
    expect(reasoning?.modifiers.length).toBe(1);
    expect(intellect?.modifiers.length).toBe(0);
  });

  it('раса arilet: наследуемые способности вида (keen-hearing) — расовые', () => {
    const model = service.build(makeBuild({ raceRuleId: 'rule-126' }), ruleCatalog, config);

    expect(model.race.costOs).toBe(2);
    expect(model.budgets.os.spent).toBe(2);
    const keen = model.abilities.find((a) => a.code === 'keen-hearing');
    expect(keen?.racial).toBe(true);
    expect(keen?.automatic).toBe(true);
  });

  it('двойной удар требует «Навыки боя»: недоступен без опыта ближнего боя, доступен с ним', () => {
    const without = service.build(makeBuild(), ruleCatalog, config, keywords);
    const strike = without.abilities.find((a) => a.code === 'sdvoennyy-udar');
    expect(strike?.levels[0].met).toBe(false);

    const withSkill = service.build(
      makeBuild({ abilities: [{ ruleId: 'rule-329', level: 1 }] }),
      ruleCatalog,
      config,
      keywords,
    );
    const strikeReady = withSkill.abilities.find((a) => a.code === 'sdvoennyy-udar');
    expect(strikeReady?.levels[0].met).toBe(true);
  });

  it('Устрашающий вид: причина требования перечисляет альтернативы человекочитаемо', () => {
    const model = service.build(makeBuild(), ruleCatalog, config, keywords);
    const intimidating = model.abilities.find((a) => a.code === 'intimidating');

    expect(intimidating?.levels[0].met).toBe(false);
    expect(intimidating?.levels[0].reason).toBe('нужна одна из способностей: «Омерзительная», «Уродливая»');
  });

  it('toVersion: arilet + покупка способности даёт корректные очки', () => {
    const build = makeBuild({ raceRuleId: 'rule-126', abilities: [{ ruleId: 'rule-25', level: 1 }] });
    const version = service.toVersion(build, ruleCatalog, config);

    expect(version.points.osSpent).toBe(2);
    expect(version.raceRuleId).toBe('rule-126');
  });

  it('видимость черт: общие видны всем, уникальные только от расы, старые скрыты', () => {
    // Без расы: общие черты (beautiful — common) видны; уникальные (fast-footed — racial) скрыты.
    const noRace = service.build(makeBuild(), ruleCatalog, config, keywords);
    expect(noRace.abilities.find((a) => a.code === 'beautiful')?.visible).toBe(true);
    expect(noRace.abilities.find((a) => a.code === 'fast-footed')?.visible).toBe(false);

    // Ацелатль даёт Быстроногий расой — виден.
    const acelatlRule = ruleCatalog.find((r) => r.code === 'acelatl');
    const withRace = service.build(makeBuild({ raceRuleId: acelatlRule?.id ?? '' }), ruleCatalog, config, keywords);
    expect(withRace.abilities.find((a) => a.code === 'fast-footed')?.visible).toBe(true);

    // Выбранная способность всегда видна (даже если скрыта в каталоге).
    const chosen = service.build(
      makeBuild({ abilities: [{ ruleId: 'rule-329', level: 1 }] }),
      ruleCatalog,
      config,
      keywords,
    );
    expect(chosen.abilities.find((a) => a.code === 'borba')?.visible).toBe(true);
  });

  it('наследование способностей по дереву вид→подвид→раса (Арилет)', () => {
    const arilet = ruleCatalog.find((r) => r.code === 'arilet');
    const model = service.build(makeBuild({ raceRuleId: arilet?.id ?? '' }), ruleCatalog, config, keywords);

    expect(model.race).toMatchObject({ costOs: 2 });
    expect(model.budgets.os.spent).toBe(2);
    // keen-hearing наследуется от elves через цепочку verto → wood-elves → elves.
    const keen = model.abilities.find((a) => a.code === 'keen-hearing');
    expect(keen?.racial).toBe(true);
    expect(keen?.visible).toBe(true);
    // Характеристики: Выносливость 5, остальные 3.
    expect(model.characteristics.find((c) => c.code === 'endurance')?.base).toEqual({ base: 5, size: 0 });
    expect(model.characteristics.find((c) => c.code === 'strength')?.base).toEqual({ base: 5, size: -1 });
  });

  it('дерево рас: вид→подвид→раса (Эльфы→Лесной→Верто→Арилет)', () => {
    const tree = ruleCatalog.filter((r) =>
      ['elves', 'wood-elves', 'verto', 'arilet', 'dwarves', 'turim', 'orcs'].includes(r.code),
    );
    const specOf = (code: string) =>
      tree.find((r) => r.code === code)?.spec as { parent_race_code?: string | null } | undefined;

    expect(specOf('wood-elves')?.parent_race_code).toBe('elves');
    expect(specOf('verto')?.parent_race_code).toBe('wood-elves');
    expect(specOf('arilet')?.parent_race_code).toBe('verto');
    expect(specOf('dwarves')?.parent_race_code).toBeNull();
    expect(specOf('turim')?.parent_race_code).toBe('dwarves');
    expect(specOf('orcs')?.parent_race_code).toBeNull();
  });

  it('расовые automatic-способности даются бесплатно (Дюариец: Сопротивление холоду)', () => {
    const duariets = ruleCatalog.find((r) => r.code === 'duariets');
    const model = service.build(makeBuild({ raceRuleId: duariets?.id ?? '' }), ruleCatalog, config, keywords);

    const cold = model.abilities.find((a) => a.code === 'cold-resistance');
    expect(cold?.racial).toBe(true);
    expect(cold?.automatic).toBe(true);
    // Приобретаемые расовые черты — расовые, но не автоматические.
    const magicPotential = model.abilities.find((a) => a.code === 'magic-potential');
    expect(magicPotential?.racial).toBe(true);
    expect(magicPotential?.automatic).toBe(false);
  });

  it('группы: group-правила вне списка способностей, участники внутри своих групп', () => {
    const model = service.build(makeBuild(), ruleCatalog, config, keywords);

    const groups = new Map(model.groups.map((group) => [group.code, group]));
    const appearance = groups.get('appearance');
    expect(appearance?.name).toBe('Внешность');
    expect(appearance?.selectLimit).toBe(1);
    expect(appearance?.members.map((m) => m.code).sort()).toEqual(['beautiful', 'gorgeous', 'repulsive', 'ugly']);

    // Участники несут groupCode, group-правило не попало в способности.
    expect(model.abilities.find((a) => a.code === 'beautiful')?.groupCode).toBe('appearance');
    expect(model.abilities.find((a) => a.code === 'appearance')).toBeUndefined();
    expect(model.abilities.find((a) => a.code === 'gifted')).toBeUndefined();
    expect(model.abilities.some((a) => a.groupCode === null || a.groupCode === undefined)).toBe(true);
  });

  it('Дюариец: Сопротивление магии ограничено потолком X=2 (покупаемая)', () => {
    const duariets = ruleCatalog.find((r) => r.code === 'duariets');
    const model = service.build(makeBuild({ raceRuleId: duariets?.id ?? '' }), ruleCatalog, config, keywords);

    const res = model.abilities.find((a) => a.code === 'magic-resistance');
    expect(res?.racial).toBe(true);
    expect(res?.automatic).toBe(false);
    expect(res?.parameters).toEqual([
      {
        code: 'x',
        label: 'X',
        value: { base: 0, size: 0 },
        min: { base: 0, size: 0 },
        max: { base: 2, size: 0 },
        perUnit: 2,
        steps: [],
        cappedByRace: true,
        freeValue: 0,
        freeStepCost: 0,
      },
    ]);

    // Взятие с X=2: стоимость = 2 × 2 = 4 ОС.
    const taken = service.build(
      makeBuild({
        raceRuleId: duariets?.id ?? '',
        abilities: [{ ruleId: res?.ruleId ?? '', level: 1, parameters: { x: { base: 2, size: 0 } } }],
      }),
      ruleCatalog,
      config,
      keywords,
    );
    expect(taken.budgets.os.spent).toBe(4);
  });

  it('Ахтар: автоматическое Сопротивление магии несёт значение X=2 из расы', () => {
    const ahtar = ruleCatalog.find((r) => r.code === 'ahtar');
    const model = service.build(makeBuild({ raceRuleId: ahtar?.id ?? '' }), ruleCatalog, config, keywords);

    const res = model.abilities.find((a) => a.code === 'magic-resistance');
    expect(res?.automatic).toBe(true);
    const param = res?.parameters.find((p) => p.code === 'x');
    expect(param?.value).toEqual({ base: 2, size: 0 });
    expect(param?.cappedByRace).toBe(true);
  });

  it('Невероятное зрение: чувство Зрение +3 → Внимательность {3|1}', () => {
    const vision = ruleCatalog.find((r) => r.code === 'incredible-vision');
    const model = service.build(
      makeBuild({ raceRuleId: 'rule-126', abilities: [{ ruleId: vision?.id ?? '', level: 1 }] }),
      ruleCatalog,
      config,
      keywords,
    );

    const attention = model.characteristics.find((c) => c.code === 'attention');
    expect(attention?.base).toEqual({ base: 3, size: 0 });
    expect(attention?.delta).toBe(3);
    expect(attention?.value).toEqual({ base: 3, size: 1 });
    expect(attention?.modifiers).toEqual([expect.objectContaining({ delta: 3, target: 'attention' })]);

    const visionSense = ruleCatalog.find((r) => r.code === 'sense-vision');
    const sense = model.senses.find((s) => s.ruleId === visionSense?.id);
    expect(sense?.value).toBe(3);
    expect(sense?.status).toBe('precise');
    expect(sense?.radius).toEqual({ base: 30, size: 0 });
    expect(model.senses.find((s) => s.ruleId === sense?.ruleId)?.modifiers[0]).toMatchObject({ delta: 3 });
  });

  it('чувства: -6 слух + +3 зрение → Внимательность берёт максимум (+3)', () => {
    const hearing = ruleCatalog.find((r) => r.code === 'terrible-hearing');
    const vision = ruleCatalog.find((r) => r.code === 'incredible-vision');
    const model = service.build(
      makeBuild({
        raceRuleId: 'rule-126',
        abilities: [
          { ruleId: hearing?.id ?? '', level: 1 },
          { ruleId: vision?.id ?? '', level: 1 },
        ],
      }),
      ruleCatalog,
      config,
      keywords,
    );

    const attention = model.characteristics.find((c) => c.code === 'attention');
    expect(attention?.delta).toBe(3);
    expect(attention?.value).toEqual({ base: 3, size: 1 });
  });

  it('чувства: -6 слух + нормальное зрение (не взято) → 0 к Внимательности', () => {
    const hearing = ruleCatalog.find((r) => r.code === 'terrible-hearing');
    const model = service.build(
      makeBuild({ raceRuleId: 'rule-126', abilities: [{ ruleId: hearing?.id ?? '', level: 1 }] }),
      ruleCatalog,
      config,
      keywords,
    );

    const attention = model.characteristics.find((c) => c.code === 'attention');
    expect(attention?.delta).toBe(0);
    expect(attention?.value).toEqual({ base: 3, size: 0 });
    expect(attention?.modifiers.length).toBe(0);
  });

  it('чувства: максимум среди чувств, чувство без грантов = 0 (нормальное)', () => {
    const terribleHearing = ruleCatalog.find((r) => r.code === 'terrible-hearing');
    const weakHearing = ruleCatalog.find((r) => r.code === 'weak-hearing');
    const model = service.build(
      makeBuild({
        raceRuleId: 'rule-126',
        abilities: [
          { ruleId: terribleHearing?.id ?? '', level: 1 },
          { ruleId: weakHearing?.id ?? '', level: 1 },
        ],
      }),
      ruleCatalog,
      config,
      keywords,
    );

    // Оба модификатора слуха от источника «Совершенство» → действует только наибольший штраф (−6);
    // зрение нормальное (0) → максимум среди чувств = 0, Внимательность не меняется.
    const hearingSense = ruleCatalog.find((r) => r.code === 'sense-hearing');
    const sense = model.senses.find((s) => s.ruleId === hearingSense?.id);
    expect(sense?.value).toBe(-6);
    const attention = model.characteristics.find((c) => c.code === 'attention');
    expect(attention?.delta).toBe(0);
  });

  it('орки: автоматическое Сопротивление магии несёт значение расы (Орхан X=2, Орзак X=3)', () => {
    for (const [code, x] of [
      ['orgul', 1],
      ['orhan', 2],
      ['orzack', 3],
    ] as const) {
      const race = ruleCatalog.find((r) => r.code === code);
      const model = service.build(makeBuild({ raceRuleId: race?.id ?? '' }), ruleCatalog, config, keywords);

      const res = model.abilities.find((a) => a.code === 'magic-resistance');
      expect(res?.automatic).toBe(true);
      expect(res?.parameters[0]).toMatchObject({
        value: { base: x, size: 0 },
        max: { base: x, size: 0 },
        freeValue: x,
      });
    }
  });

  it('орки: Магия доступна с потолком X=3', () => {
    const race = ruleCatalog.find((r) => r.code === 'orgul');
    const model = service.build(makeBuild({ raceRuleId: race?.id ?? '' }), ruleCatalog, config, keywords);

    const potential = model.abilities.find((a) => a.code === 'magic-potential');
    expect(potential?.racial).toBe(true);
    expect(potential?.automatic).toBe(false);
    expect(potential?.parameters[0]).toMatchObject({ max: { base: 3, size: 0 }, cappedByRace: true, freeValue: 0 });
  });

  it('эльфы (Арилет): бесплатно Сопротивление магии 1, доступна Магия 4 и Сопротивление магии до 3', () => {
    const race = ruleCatalog.find((r) => r.code === 'arilet');
    const model = service.build(makeBuild({ raceRuleId: race?.id ?? '' }), ruleCatalog, config, keywords);

    const res = model.abilities.find((a) => a.code === 'magic-resistance');
    expect(res?.automatic).toBe(true);
    expect(res?.parameters[0]).toMatchObject({ value: { base: 1, size: 0 }, max: { base: 3, size: 0 }, freeValue: 1 });

    const potential = model.abilities.find((a) => a.code === 'magic-potential');
    expect(potential?.parameters[0]).toMatchObject({ max: { base: 4, size: 0 }, cappedByRace: true });
  });

  it('Турим: Магия доступна с потолком X=5', () => {
    const race = ruleCatalog.find((r) => r.code === 'turim');
    const model = service.build(makeBuild({ raceRuleId: race?.id ?? '' }), ruleCatalog, config, keywords);

    const potential = model.abilities.find((a) => a.code === 'magic-potential');
    expect(potential?.parameters[0]).toMatchObject({ max: { base: 5, size: 0 }, cappedByRace: true });
  });

  it('Аэрон: авто Сопротивление магии X=2, докупка до 5 сверх авто, без задвоения гранта', () => {
    const race = ruleCatalog.find((r) => r.code === 'aeron');
    const mr = ruleCatalog.find((r) => r.code === 'magic-resistance');
    const plain = service.build(makeBuild({ raceRuleId: race?.id ?? '' }), ruleCatalog, config, keywords);

    const res = plain.abilities.find((a) => a.code === 'magic-resistance');
    expect(res?.automatic).toBe(true);
    expect(res?.parameters[0]).toMatchObject({
      value: { base: 2, size: 0 },
      max: { base: 5, size: 0 },
      freeValue: 2,
      cappedByRace: true,
    });

    // Докупка до 5: оплачивается только 5−2 = 3 единицы × per_unit 2 = 6 ОС (поверх cost_os 12).
    const taken = service.build(
      makeBuild({
        raceRuleId: race?.id ?? '',
        abilities: [{ ruleId: mr?.id ?? '', level: 1, parameters: { x: { base: 5, size: 0 } } }],
      }),
      ruleCatalog,
      config,
      keywords,
    );
    expect(taken.budgets.os.spent).toBe(12 + 6);
    const takenRes = taken.abilities.find((a) => a.code === 'magic-resistance');
    expect(takenRes?.parameters[0]).toMatchObject({ value: { base: 5, size: 0 } });
  });

  it('Му’укай: нет доступной Магии (убрана по доке), авто Сопротивление магии 1', () => {
    const race = ruleCatalog.find((r) => r.code === 'muukai');
    const model = service.build(makeBuild({ raceRuleId: race?.id ?? '' }), ruleCatalog, config, keywords);

    const potential = model.abilities.find((a) => a.code === 'magic-potential');
    expect(potential?.racial ?? false).toBe(false);
    const res = model.abilities.find((a) => a.code === 'magic-resistance');
    expect(res?.parameters[0]).toMatchObject({ value: { base: 1, size: 0 }, freeValue: 1 });
  });

  it('Дюариец: автоматическое Сопротивление холоду несёт значение X=1', () => {
    const race = ruleCatalog.find((r) => r.code === 'duariets');
    const model = service.build(makeBuild({ raceRuleId: race?.id ?? '' }), ruleCatalog, config, keywords);

    const cold = model.abilities.find((a) => a.code === 'cold-resistance');
    expect(cold?.automatic).toBe(true);
    expect(cold?.parameters[0]).toMatchObject({ value: { base: 1, size: 0 }, max: { base: 1, size: 0 }, freeValue: 1 });
  });

  // --- S8 «Врождённые характеристики» (Телосложение) ---

  it('Врождённая Сила X: табличная цена по значению параметра (X=2 → 4 ОС)', () => {
    const rule = ruleCatalog.find((r) => r.code === 'innate-strength');
    const model = service.build(makeBuild(), ruleCatalog, config, keywords);

    const innate = model.abilities.find((a) => a.code === 'innate-strength');
    expect(innate?.visible).toBe(true);
    expect(innate?.parameters[0]).toMatchObject({
      value: { base: 0, size: 0 },
      min: { base: -3, size: 0 },
      max: { base: 3, size: 0 },
      perUnit: 0,
      costs: { '-3': -3, '-2': -2, '-1': -1, '1': 2, '2': 4, '3': 8 },
      cappedByRace: false,
      freeValue: 0,
    });

    const withX = service.build(
      makeBuild({ abilities: [{ ruleId: rule?.id ?? '', level: 1, parameters: { x: { base: 2, size: 0 } } }] }),
      ruleCatalog,
      config,
      keywords,
    );
    expect(withX.budgets.os.spent).toBe(4);
  });

  it('Врождённая Сила X: отрицательный модификатор возвращает ОС (X=−1 → −1) и применяет −1 к Силе', () => {
    const race = ruleCatalog.find((r) => r.code === 'acelatl');
    const rule = ruleCatalog.find((r) => r.code === 'innate-strength');
    const model = service.build(
      makeBuild({
        raceRuleId: race?.id ?? '',
        abilities: [{ ruleId: rule?.id ?? '', level: 1, parameters: { x: { base: -1, size: 0 } } }],
      }),
      ruleCatalog,
      config,
      keywords,
    );

    expect(model.budgets.os.spent).toBe(-1);
    expect(model.characteristics.find((c) => c.code === 'strength')?.delta).toBe(-1);
  });

  it('Врождённая Сила/Стойкость связаны: |X_силы − X_стойкости| ≤ 3', () => {
    const strength = ruleCatalog.find((r) => r.code === 'innate-strength');
    const endurance = ruleCatalog.find((r) => r.code === 'innate-endurance');

    // Стойкость не выбрана (0): Сила может быть до +3.
    const onlyStrength = service.build(
      makeBuild({ abilities: [{ ruleId: strength?.id ?? '', level: 1, parameters: { x: { base: 1, size: 0 } } }] }),
      ruleCatalog,
      config,
      keywords,
    );
    expect(onlyStrength.abilities.find((a) => a.code === 'innate-strength')?.parameters[0].max).toEqual({
      base: 3,
      size: 0,
    });

    // Сила X=2 → Стойкость ограничена до +3 (2+1) и не ниже −1 (2−3).
    const withEndurance = service.build(
      makeBuild({
        abilities: [
          { ruleId: strength?.id ?? '', level: 1, parameters: { x: { base: 2, size: 0 } } },
          { ruleId: endurance?.id ?? '', level: 1, parameters: { x: { base: 0, size: 0 } } },
        ],
      }),
      ruleCatalog,
      config,
      keywords,
    );
    const enduranceParam = withEndurance.abilities.find((a) => a.code === 'innate-endurance')?.parameters[0];
    expect(enduranceParam?.min).toEqual({ base: -1, size: 0 });
    expect(enduranceParam?.max).toEqual({ base: 3, size: 0 });

    // Стойкость X=−2 → Сила ограничена до +1 (max 1) и не ниже −3.
    const constrainedStrength = service.build(
      makeBuild({
        abilities: [
          { ruleId: strength?.id ?? '', level: 1, parameters: { x: { base: 0, size: 0 } } },
          { ruleId: endurance?.id ?? '', level: 1, parameters: { x: { base: -2, size: 0 } } },
        ],
      }),
      ruleCatalog,
      config,
      keywords,
    );
    const strengthParam = constrainedStrength.abilities.find((a) => a.code === 'innate-strength')?.parameters[0];
    expect(strengthParam?.min).toEqual({ base: -3, size: 0 });
    expect(strengthParam?.max).toEqual({ base: 1, size: 0 });
  });

  it('Врождённые Ловкость ±3; Интеллект/Восприятие ±1 (док: максимум от Телосложения +1)', () => {
    const model = service.build(makeBuild(), ruleCatalog, config, keywords);

    const dexterity = model.abilities.find((a) => a.code === 'innate-dexterity');
    expect(dexterity?.visible).toBe(true);
    expect(dexterity?.parameters[0]).toMatchObject({ min: { base: -3, size: 0 }, max: { base: 3, size: 0 } });

    for (const code of ['innate-intellect', 'innate-perception']) {
      const ability = model.abilities.find((a) => a.code === code);
      expect(ability?.visible).toBe(true);
      expect(ability?.parameters[0]).toMatchObject({ min: { base: -1, size: 0 }, max: { base: 1, size: 0 } });
    }
  });

  it('Врождённая Магия X: дар с размерной лестницей значений и табличной ценой', () => {
    const model = service.build(makeBuild(), ruleCatalog, config, keywords);

    const potential = model.abilities.find((a) => a.code === 'magic-potential');
    expect(potential?.visible).toBe(true);
    expect(potential?.characteristic).toBe(true);
    expect(potential?.characteristicCode).toBe('magic');
    const param = potential?.parameters[0];
    expect(param).toMatchObject({
      min: { base: 3, size: -1 },
      max: { base: 5, size: 1 },
    });
    expect(param?.steps.map((step) => new DimensionalNumber(step.value).toString())).toEqual([
      '3↓',
      '4↓',
      '5↓',
      '3',
      '4',
      '5',
      '3↑',
      '4↑',
      '5↑',
    ]);
    expect(param?.steps.map((step) => step.cost)).toEqual([1, 2, 3, 4, 6, 8, 12, 16, 20]);
  });

  it('Врождённая Магия X: выбор значения 5 → 8 ОС, размерное значение хранится как есть', () => {
    const rule = ruleCatalog.find((r) => r.code === 'magic-potential');
    const model = service.build(
      makeBuild({ abilities: [{ ruleId: rule?.id ?? '', level: 1, parameters: { x: { base: 5, size: 0 } } }] }),
      ruleCatalog,
      config,
      keywords,
    );

    expect(model.budgets.os.spent).toBe(8);
    const param = model.abilities.find((a) => a.code === 'magic-potential')?.parameters[0];
    expect(param?.value).toEqual({ base: 5, size: 0 });
    expect(param?.steps.find((step) => new DimensionalNumber(step.value).toString() === '5')?.cost).toBe(8);

    // Приобретённая Магия появляется среди характеристик (база = выбранное значение).
    const magic = model.characteristics.find((c) => c.code === 'magic');
    expect(magic).toBeDefined();
    expect(magic?.base).toEqual({ base: 5, size: 0 });
  });

  it('Врождённая Магия X: не выбранная — характеристики Магии нет', () => {
    const model = service.build(makeBuild(), ruleCatalog, config, keywords);

    expect(model.characteristics.find((c) => c.code === 'magic')).toBeUndefined();
  });

  it('Ахтар: Магия 4↓ расы бесплатна (дар не взят) — value/min 4↓, ступени ниже недоступны', () => {
    const ahtar = ruleCatalog.find((r) => r.code === 'ahtar');
    const model = service.build(makeBuild({ raceRuleId: ahtar?.id ?? '' }), ruleCatalog, config, keywords);

    const potential = model.abilities.find((a) => a.code === 'magic-potential');
    expect(potential?.racial).toBe(true);
    expect(potential?.automatic).toBe(false);
    const param = potential?.parameters[0];
    expect(param?.value).toEqual({ base: 4, size: -1 });
    expect(param?.min).toEqual({ base: 4, size: -1 });
    expect(param?.max).toEqual({ base: 5, size: 0 });
    expect(param?.freeStepCost).toBe(2);
    expect(param?.freeValue).toBe(2);
    expect(param?.steps.map((step) => new DimensionalNumber(step.value).toString())).toEqual([
      '4↓',
      '5↓',
      '3',
      '4',
      '5',
    ]);
    // Инкрементальная цена: табл(X) − табл(4↓) = табл(X) − 2.
    expect(param?.steps.map((step) => step.cost)).toEqual([2, 3, 4, 6, 8]);
    expect(model.budgets.os.spent).toBe(0);

    // Расовое фикс. значение 4↓ отображается как характеристика без трат ОС.
    const magic = model.characteristics.find((c) => c.code === 'magic');
    expect(magic?.base).toEqual({ base: 4, size: -1 });
    expect(magic?.value).toEqual({ base: 4, size: -1 });
  });

  it('Ахтар: покупка Магии 5↓ стоит 1 ОС (инкрементально от 4↓) и переопределяет базу', () => {
    const ahtar = ruleCatalog.find((r) => r.code === 'ahtar');
    const potential = ruleCatalog.find((r) => r.code === 'magic-potential');
    const model = service.build(
      makeBuild({
        raceRuleId: ahtar?.id ?? '',
        abilities: [{ ruleId: potential?.id ?? '', level: 1, parameters: { x: { base: 5, size: -1 } } }],
      }),
      ruleCatalog,
      config,
      keywords,
    );

    expect(model.budgets.os.spent).toBe(1);
    const param = model.abilities.find((a) => a.code === 'magic-potential')?.parameters[0];
    expect(param?.value).toEqual({ base: 5, size: -1 });
    // Итог в блоке характеристик: 5↓ (переопределяет расовую 4↓).
    const magic = model.characteristics.find((c) => c.code === 'magic');
    expect(magic?.base).toEqual({ base: 5, size: -1 });
    expect(magic?.value).toEqual({ base: 5, size: -1 });
  });

  it('Ахтар: покупка Магии 5 (средней) стоит 6 ОС (8 − 2)', () => {
    const ahtar = ruleCatalog.find((r) => r.code === 'ahtar');
    const potential = ruleCatalog.find((r) => r.code === 'magic-potential');
    const model = service.build(
      makeBuild({
        raceRuleId: ahtar?.id ?? '',
        abilities: [{ ruleId: potential?.id ?? '', level: 1, parameters: { x: { base: 5, size: 0 } } }],
      }),
      ruleCatalog,
      config,
      keywords,
    );

    expect(model.budgets.os.spent).toBe(6);
    const magic = model.characteristics.find((c) => c.code === 'magic');
    expect(magic?.value).toEqual({ base: 5, size: 0 });
  });

  it('Врождённые черты характеристик не попадают в каталог этапа «Основа» (BaseTab фильтрует)', () => {
    const model = service.build(makeBuild(), ruleCatalog, config, keywords);

    const innate = model.abilities.filter((a) => a.characteristic);
    expect(innate.length).toBeGreaterThanOrEqual(6);
    expect(innate.every((a) => a.visible)).toBe(true);
  });

  it('возраст: каталог несёт правило age и таблицы лет видов; ступень резолвится по цепочке расы', () => {
    const alierets = ruleCatalog.find((r) => r.code === 'alierets');
    expect(alierets).toBeDefined();

    // Алиерц → вид human: годы 22 → Молодой (18–25) по наследуемой таблице.
    const model = service.build(
      makeBuild({ raceRuleId: alierets?.id ?? '', ageYears: 22 }),
      ruleCatalog,
      config,
      keywords,
    );
    expect(model.personality.hasAgeRule).toBe(true);
    expect(model.personality.ageName).toBe('Молодой');
    expect(model.personality.ol).toBe(3);
    expect(model.personality.featureLimit).toBe(3);
    expect(model.budgets.ol.total).toBe(3);
  });

  it('дефолт возраста в каталоге: средняя ступень шкалы (9 ступеней → «Молодой», минимум 18)', () => {
    const alierets = ruleCatalog.find((r) => r.code === 'alierets');
    const model = service.build(makeBuild({ raceRuleId: alierets?.id ?? '' }), ruleCatalog, config, keywords);

    expect(model.personality.ageScale).toHaveLength(9);
    expect(model.personality.defaultAgeYears).toBe(18);
  });

  it('возраст: ступень «Старый» за диапазонами лет с эффектами', () => {
    const alierets = ruleCatalog.find((r) => r.code === 'alierets');
    const model = service.build(
      makeBuild({ raceRuleId: alierets?.id ?? '', ageYears: 200 }),
      ruleCatalog,
      config,
      keywords,
    );

    expect(model.personality.ageName).toBe('Старый');
    expect(model.personality.ol).toBe(7);
    const perception = model.characteristics.find((c) => c.code === 'perception');
    expect(perception).toBeDefined();
  });

  it('15 ol-особенностей в модели редактора, группы богатства/общительности/внимательности', () => {
    const model = service.build(makeBuild(), ruleCatalog, config, keywords);
    const olFeatures = model.abilities.filter((ability) => ability.zones.some((zone) => zone.zoneCode === 'ol'));
    expect(olFeatures).toHaveLength(15);

    const groupCodes = model.groups.map((group) => group.code).sort();
    expect(groupCodes).toContain('wealth');
    expect(groupCodes).toContain('sociability');
    expect(groupCodes).toContain('attentiveness');
  });

  it('особенность «Богатый»: бюджет денег = max(фикс, % от лимита); богатство вне лимита особенностей', () => {
    const rich = ruleCatalog.find((r) => r.code === 'rich');
    expect(rich).toBeDefined();
    const alierets = ruleCatalog.find((r) => r.code === 'alierets');
    const model = service.build(
      makeBuild({ raceRuleId: alierets?.id ?? '', ageYears: 22, abilities: [{ ruleId: rich?.id ?? '', level: 1 }] }),
      ruleCatalog,
      config,
      keywords,
    );

    expect(model.budgets.money.total).toBe(400);
    expect(model.personality.wealthRuleIds).toContain(rich?.id);
    // Лимит числа особенностей (Молодой = 3) не учитывает богатство: взятая 1 особенность не исчерпывает лимит.
    expect(model.personality.featureLimit).toBe(3);
  });

  it('дары-навыки (D100): «Общительный» даёт «Тренировку Красноречия 1» и «Манеру общения 1», снять нельзя', () => {
    const sociable = ruleCatalog.find((r) => r.code === 'sociable');
    const model = service.build(
      makeBuild({ abilities: [{ ruleId: sociable?.id ?? '', level: 1 }] }),
      ruleCatalog,
      config,
      keywords,
    );

    const training = model.abilities.find((a) => a.code === 'krasnorechie');
    expect(training?.gifted).toBe(true);
    expect(training?.level).toBe(1);
    const manner = model.abilities.find((a) => a.code === 'manera-obscheniya');
    expect(manner?.gifted).toBe(true);
    expect(manner?.level).toBe(1);
  });

  it('дары-навыки (D100): «Общительный» не даёт бюджет — gifted-записи не списывают ОР', () => {
    const sociable = ruleCatalog.find((r) => r.code === 'sociable');
    const model = service.build(
      makeBuild({ abilities: [{ ruleId: sociable?.id ?? '', level: 1 }] }),
      ruleCatalog,
      config,
      keywords,
    );

    expect(model.budgets.or.spent).toBe(0);
  });

  it('апгрейд дарованного навыка (D100): списывается только разница сверх подаренного уровня', () => {
    const sociable = ruleCatalog.find((r) => r.code === 'sociable');
    const training = ruleCatalog.find((r) => r.code === 'krasnorechie');
    // «Общительный» даёт «Тренировку Красноречия 1» бесплатно; 2-й уровень зоны [1,2,3] — доплата 2 ОР.
    const model = service.build(
      makeBuild({
        abilities: [
          { ruleId: sociable?.id ?? '', level: 1 },
          { ruleId: training?.id ?? '', level: 2 },
        ],
      }),
      ruleCatalog,
      config,
      keywords,
    );

    const krasnorechie = model.abilities.find((a) => a.code === 'krasnorechie');
    expect(krasnorechie?.gifted).toBe(true);
    expect(krasnorechie?.giftedLevel).toBe(1);
    expect(krasnorechie?.level).toBe(2);
    expect(model.budgets.or.spent).toBe(2);
  });

  it('автоматические характеристики: у нелюдской расы (эльф arilet) base-характеристики = 3 средних', () => {
    const rule = ruleCatalog.find((r) => r.code === 'arilet');
    const model = service.build(makeBuild({ raceRuleId: rule?.id ?? '' }), ruleCatalog, config, keywords);

    for (const code of ['attention', 'reaction', 'memory', 'reasoning', 'communication', 'willpower']) {
      expect(model.characteristics.find((c) => c.code === code)?.base).toEqual({ base: 3, size: 0 });
    }
    // «Мастерство боя» — автополучение со своей базой 3 маленьких ({3|-1}).
    expect(model.characteristics.find((c) => c.code === 'melee-combat')?.base).toEqual({ base: 3, size: -1 });
    // Раса переопределяет базу автоматической характеристики (Выносливость 5) — её значение приоритетнее.
    expect(model.characteristics.find((c) => c.code === 'endurance')?.base).toEqual({ base: 5, size: 0 });
  });

  it('«Тренировка Красноречия»: +{уровень} к Красноречию от тренировки, не метод развития общения', () => {
    const rule = ruleCatalog.find((r) => r.code === 'krasnorechie');
    const model = service.build(
      makeBuild({ abilities: [{ ruleId: rule?.id ?? '', level: 2 }] }),
      ruleCatalog,
      config,
      keywords,
    );

    const training = model.abilities.find((a) => a.code === 'krasnorechie');
    expect(training?.name).toBe('Тренировка Красноречия');
    expect(training?.keywordIds).not.toContain(58);
    const communication = model.characteristics.find((c) => c.code === 'communication');
    expect(communication?.modifiers.some((m) => m.delta === 2)).toBe(true);
  });

  it('«Манера общения»: домен из типов проверок общения (не множественный)', () => {
    const model = service.build(makeBuild(), ruleCatalog, config, keywords);

    const manner = model.abilities.find((a) => a.code === 'manera-obscheniya');
    expect(manner?.multiple).toBe(false);
    expect(manner?.domainRef).toBe('communication-check');
    expect(manner?.domainOptions.map((option) => option.name)).toEqual([
      'Запугивание',
      'Убеждение',
      'Обман',
      'Обольщение',
      'Торговля',
    ]);
  });

  it('«Общительный»: домен «Манера общения» материализует gifted-запись без списания ОР', () => {
    const sociable = ruleCatalog.find((r) => r.code === 'sociable');
    const manner = ruleCatalog.find((r) => r.code === 'manera-obscheniya');
    const build = characterBuildService.setAbilityDomain(
      makeBuild({ abilities: [{ ruleId: sociable?.id ?? '', level: 1 }] }),
      manner?.id ?? '',
      'Запугивание',
      { domainCode: 'intimidation' },
    );
    const model = service.build(build, ruleCatalog, config, keywords);

    const mannerAbility = model.abilities.find((a) => a.code === 'manera-obscheniya');
    expect(mannerAbility?.gifted).toBe(true);
    expect(mannerAbility?.level).toBe(1);
    expect(mannerAbility?.domain).toBe('Запугивание');
    expect(mannerAbility?.domainCode).toBe('intimidation');
    expect(model.budgets.or.spent).toBe(0);
  });

  it('производный «Ближний бой» (D109): уровень из суммы стоимостей навыков ближнего боя', () => {
    const cost2 = (code: string) => {
      const rule = ruleCatalog.find((r) => r.code === code);

      return rule ? { ruleId: rule.id, level: 1 } : null;
    };
    const taken = [
      cost2('otstuplenie'), // 1
      cost2('zaschita-znaniem'), // 2
      cost2('adaptatsiya-k-protivniku'), // 2
      cost2('podderzhka'), // 2
      cost2('fekhtovanie'), // 2 → сумма 9
    ].filter((x): x is { ruleId: string; level: number } => x !== null);

    const model = service.build(makeBuild({ abilities: taken }), ruleCatalog, config, keywords);

    const melee = model.abilities.find((a) => a.code === 'blizhniy-boy');
    expect(melee?.level).toBe(2); // 9 ≥ 8
    // Требование «Ближний бой 1» теперь выполняется (например, у «Обезоруживания»).
    const obezoruzhivanie = model.abilities.find((a) => a.code === 'obezoruzhivanie');
    expect(obezoruzhivanie?.levels[0].met).toBe(true);
  });

  it('«Навыки боя» (D109): даёт +{уровень} к «Мастерству боя» от тренировок', () => {
    const cost2 = (code: string) => {
      const rule = ruleCatalog.find((r) => r.code === code);

      return rule ? { ruleId: rule.id, level: 1 } : null;
    };
    const taken = [
      cost2('otstuplenie'), // 1
      cost2('zaschita-znaniem'), // 2
      cost2('adaptatsiya-k-protivniku'), // 2
      cost2('podderzhka'), // 2
      cost2('fekhtovanie'), // 2 → сумма 9
    ].filter((x): x is { ruleId: string; level: number } => x !== null);

    const model = service.build(makeBuild({ abilities: taken }), ruleCatalog, config, keywords);

    const skills = model.abilities.find((a) => a.code === 'blizhniy-boy');
    expect(skills?.name).toBe('Навыки боя');
    expect(skills?.level).toBe(2);
    const melee = model.characteristics.find((c) => c.code === 'melee-combat');
    expect(melee?.base).toEqual({ base: 3, size: -1 });
    expect(melee?.modifiers.some((m) => m.delta === 2)).toBe(true);
  });

  it('агрегат «Развитие восприятия» (D108): два метода стоимостью 2 дают уровень 1', () => {
    const skill = (code: string) => {
      const rule = ruleCatalog.find((r) => r.code === code);

      return rule ? { ruleId: rule.id, level: 1 } : null;
    };
    // Ориентирование и Чтение следов — методы развития восприятия, Стоимость 2 каждый.
    const taken = [skill('orientirovanie'), skill('chtenie-sledov')].filter(
      (x): x is { ruleId: string; level: number } => x !== null,
    );
    const alierets = ruleCatalog.find((r) => r.code === 'alierets');
    const model = service.build(
      makeBuild({ raceRuleId: alierets?.id ?? '', abilities: taken }),
      ruleCatalog,
      config,
      keywords,
    );

    // Уровень агрегата 1: два метода ≥1 есть, но для уровня 2 нужны ещё два ≥2 — их нет.
    const aggregate = model.abilities.find((a) => a.code === 'razvitie-vospriyatiya');
    expect(aggregate?.level).toBe(1);
    // Бонус от уровня агрегата приходит на базы производной Восприятие (attention/reaction).
    const attention = model.characteristics.find((c) => c.code === 'attention');
    expect(attention?.modifiers.some((m) => m.delta === 1)).toBe(true);
    const reaction = model.characteristics.find((c) => c.code === 'reaction');
    expect(reaction?.modifiers.some((m) => m.delta === 1)).toBe(true);
    // Восприятие (min баз) выросло на 1.
    const perception = model.characteristics.find((c) => c.code === 'perception');
    expect(new DimensionalNumber(perception?.value ?? { base: 0, size: 0 }).toNumber()).toBe(4);
  });

  it('«Развитие внимательности/реакции» дают модификатор от тренировки к базам Восприятия', () => {
    const vnim = ruleCatalog.find((r) => r.code === 'razvitie-vnimatelnosti');
    const reak = ruleCatalog.find((r) => r.code === 'razvitie-reaktsii');
    const alierets = ruleCatalog.find((r) => r.code === 'alierets');
    const model = service.build(
      makeBuild({
        raceRuleId: alierets?.id ?? '',
        abilities: [
          { ruleId: vnim?.id ?? '', level: 1 },
          { ruleId: reak?.id ?? '', level: 2 },
        ],
      }),
      ruleCatalog,
      config,
      keywords,
    );

    // Модификатор от «Тренировки» (источник training): +1 к Внимательности за уровень навыка.
    const attention = model.characteristics.find((c) => c.code === 'attention');
    const attentionMod = attention?.modifiers.find((m) => m.sourceRuleId === 'rule-38');
    expect(attentionMod?.delta).toBe(1);
    expect(attention?.value).toEqual({ base: 4, size: 0 });

    // +2 к Реакции за второй уровень.
    const reaction = model.characteristics.find((c) => c.code === 'reaction');
    const reactionMod = reaction?.modifiers.find((m) => m.sourceRuleId === 'rule-38');
    expect(reactionMod?.delta).toBe(2);
    expect(reaction?.value).toEqual({ base: 5, size: 0 });

    // Восприятие = min(Внимательность, Реакция) выросло соответственно.
    const perception = model.characteristics.find((c) => c.code === 'perception');
    expect(new DimensionalNumber(perception?.value ?? { base: 0, size: 0 }).toNumber()).toBe(4);
  });

  it('«Развитие внимательности/реакции» не методы развития восприятия (D108 их не считает)', () => {
    const skill = (code: string) => {
      const rule = ruleCatalog.find((r) => r.code === code);

      return rule ? { ruleId: rule.id, level: 1 } : null;
    };
    // Два «Развития внимательности/реакции» — раньше ошибочно давали бонус агрегата «Развитие восприятия».
    const taken = [skill('razvitie-vnimatelnosti'), skill('razvitie-reaktsii')].filter(
      (x): x is { ruleId: string; level: number } => x !== null,
    );
    const alierets = ruleCatalog.find((r) => r.code === 'alierets');
    const model = service.build(
      makeBuild({ raceRuleId: alierets?.id ?? '', abilities: taken }),
      ruleCatalog,
      config,
      keywords,
    );

    // Агрегат D108 требует ≥2 методов с method-perception: эти навыки больше не методы → бонуса нет.
    const attention = model.characteristics.find((c) => c.code === 'attention');
    expect(attention?.modifiers.some((m) => m.delta === 2)).toBe(false);
    // Восприятие = min баз (3 + 1 = 4 у каждой базы), без бонуса агрегата → итог 4, а не 5.
    const perception = model.characteristics.find((c) => c.code === 'perception');
    expect(new DimensionalNumber(perception?.value ?? { base: 0, size: 0 }).toNumber()).toBe(4);
  });

  it('«Развитие памяти/мышления» дают модификатор от тренировки к Памяти/Мышлению', () => {
    const pamyat = ruleCatalog.find((r) => r.code === 'razvitie-pamyati');
    const myshlenie = ruleCatalog.find((r) => r.code === 'razvitie-myshleniya');
    const alierets = ruleCatalog.find((r) => r.code === 'alierets');
    const model = service.build(
      makeBuild({
        raceRuleId: alierets?.id ?? '',
        abilities: [
          { ruleId: pamyat?.id ?? '', level: 1 },
          { ruleId: myshlenie?.id ?? '', level: 2 },
        ],
      }),
      ruleCatalog,
      config,
      keywords,
    );

    const memory = model.characteristics.find((c) => c.code === 'memory');
    const memoryMod = memory?.modifiers.find((m) => m.sourceRuleId === 'rule-38');
    expect(memoryMod?.delta).toBe(1);
    expect(memory?.value).toEqual({ base: 4, size: 0 });

    const reasoning = model.characteristics.find((c) => c.code === 'reasoning');
    const reasoningMod = reasoning?.modifiers.find((m) => m.sourceRuleId === 'rule-38');
    expect(reasoningMod?.delta).toBe(2);
    expect(reasoning?.value).toEqual({ base: 5, size: 0 });

    const intellect = model.characteristics.find((c) => c.code === 'intellect');
    expect(new DimensionalNumber(intellect?.value ?? { base: 0, size: 0 }).toNumber()).toBe(4);
  });

  it('агрегат «Развитие восприятия»: уровень 1 за два метода стоимостью ≥1 ([2,2])', () => {
    const skill = (code: string) => {
      const rule = ruleCatalog.find((r) => r.code === code);

      return rule ? { ruleId: rule.id, level: 1 } : null;
    };
    // Два метода стоимости 2 (Ориентирование, Чтение следов): для уровня 1 хватает, для 2 — нет.
    const taken = [skill('orientirovanie'), skill('chtenie-sledov')].filter(
      (x): x is { ruleId: string; level: number } => x !== null,
    );
    const alierets = ruleCatalog.find((r) => r.code === 'alierets');
    const model = service.build(
      makeBuild({ raceRuleId: alierets?.id ?? '', abilities: taken }),
      ruleCatalog,
      config,
      keywords,
    );

    const aggregate = model.abilities.find((a) => a.code === 'razvitie-vospriyatiya');
    expect(aggregate?.level).toBe(1);
    // Бонус от уровня агрегата приходит на базы Восприятия (attention/reaction) источником «Развитие».
    const attention = model.characteristics.find((c) => c.code === 'attention');
    expect(attention?.modifiers.some((m) => m.delta === 1 && m.sourceRuleId === 'rule-176')).toBe(true);
  });

  it('агрегат «Развитие восприятия»: уровень 2 за [2,1,2,1] (без пересечения методов)', () => {
    const skill = (code: string) => {
      const rule = ruleCatalog.find((r) => r.code === code);

      return rule ? { ruleId: rule.id, level: 1 } : null;
    };
    // Два метода стоимости 2 + два метода стоимости 1: уровень 1 берёт [1,1], уровень 2 — [2,2].
    const taken = [
      skill('orientirovanie'), // 2
      skill('orientirovanie-po-sledam'), // 1
      skill('chtenie-sledov'), // 2
      skill('postoyannaya-bditelnost'), // 1
    ].filter((x): x is { ruleId: string; level: number } => x !== null);
    const alierets = ruleCatalog.find((r) => r.code === 'alierets');
    const model = service.build(
      makeBuild({ raceRuleId: alierets?.id ?? '', abilities: taken }),
      ruleCatalog,
      config,
      keywords,
    );

    const aggregate = model.abilities.find((a) => a.code === 'razvitie-vospriyatiya');
    expect(aggregate?.level).toBe(2);
    // +2 к базам Восприятия от агрегата (плюс модификаторы методов не дают сами по себе).
    const attention = model.characteristics.find((c) => c.code === 'attention');
    expect(attention?.modifiers.some((m) => m.delta === 2)).toBe(true);
  });

  it('улучшения: parentCode из карточки + авто-требование родителя', () => {
    const akrobatika = ruleCatalog.find((r) => r.code === 'akrobatika');

    const withoutParent = service.build(makeBuild(), ruleCatalog, config);
    const boevaya = withoutParent.abilities.find((a) => a.code === 'boevaya-akrobatika');
    expect(boevaya?.parentCode).toBe('akrobatika');
    expect(boevaya?.levels[0]?.met).toBe(false);
    expect(boevaya?.levels[0]?.reason).toContain('Акробатика');

    // Цепочка улучшений: требование родителя — это синтез из привязки, не ручное требование.
    const tryuki = withoutParent.abilities.find((a) => a.code === 'smertonosnye-tryuki');
    expect(tryuki?.parentCode).toBe('boevaya-akrobatika');
    expect(tryuki?.levels[0]?.met).toBe(false);

    // Родитель взят — улучшение доступно.
    const withParent = service.build(
      makeBuild({ abilities: [{ ruleId: akrobatika?.id ?? '', level: 1 }] }),
      ruleCatalog,
      config,
    );
    const withAcrobatics = withParent.abilities.find((a) => a.code === 'boevaya-akrobatika');
    expect(withAcrobatics?.levels[0]?.met).toBe(true);

    // «Смертоносные трюки» требуют «Боевую акробатику» (одной «Акробатики» недостаточно).
    const withTryuki = withParent.abilities.find((a) => a.code === 'smertonosnye-tryuki');
    expect(withTryuki?.levels[0]?.met).toBe(false);
  });

  it('множественный навык: экземпляры со своим уровнем, бюджет = сумма по экземплярам', () => {
    const language = ruleCatalog.find((r) => r.code === 'vladenie-yazykom');

    // «Владение языком»: [2,2,2] — экземпляр уровня 3 = 6, уровня 1 = 2. Итог 8.
    const model = service.build(
      makeBuild({
        abilities: [
          { ruleId: language?.id ?? '', level: 3, domain: 'Эльфийский', zone: 'or', domainCode: 'language-elf' },
          { ruleId: language?.id ?? '', level: 1, domain: 'Орочий', zone: 'or', domainCode: 'language-orc' },
        ],
      }),
      ruleCatalog,
      config,
      keywords,
    );

    const ability = model.abilities.find((a) => a.code === 'vladenie-yazykom');
    expect(ability?.multiple).toBe(true);
    // level = max по экземплярам; instances со своими уровнями, кодами словаря и пер-экземплярными уровнями.
    expect(ability?.level).toBe(3);
    expect(ability?.instances.map(({ domain, domainCode, level }) => ({ domain, domainCode, level }))).toEqual([
      { domain: 'Эльфийский', domainCode: 'language-elf', level: 3 },
      { domain: 'Орочий', domainCode: 'language-orc', level: 1 },
    ]);
    expect(ability?.instances.every((instance) => Array.isArray(instance.levels) && instance.levels.length === 3)).toBe(
      true,
    );
    expect(model.budgets.or.spent).toBe(8);

    // Словарь языков резолвится из правил ревизии (type 'language').
    expect(ability?.domainOptions.some((option) => option.code === 'language-elf')).toBe(true);
    expect(ability?.domainOptions.some((option) => option.code === 'language-dwarf')).toBe(true);
  });

  it('множественный навык: уровень экземпляра для требований = max (has_ability)', () => {
    const language = ruleCatalog.find((r) => r.code === 'vladenie-yazykom');
    // Улучшение «Правильное произношение» требует «Владение языком» уровня 1.
    const without = service.build(makeBuild(), ruleCatalog, config, keywords);
    const pronunciation = without.abilities.find((a) => a.code === 'pravilnoe-proiznoshenie');
    expect(pronunciation?.levels[0]?.met).toBe(false);

    const withLanguage = service.build(
      makeBuild({ abilities: [{ ruleId: language?.id ?? '', level: 1, domain: 'Эльфийский', zone: 'or' }] }),
      ruleCatalog,
      config,
      keywords,
    );
    const available = withLanguage.abilities.find((a) => a.code === 'pravilnoe-proiznoshenie');
    expect(available?.levels[0]?.met).toBe(true);
  });
});

describe('«Владение оружием» (C2): семья оружия и лестница', () => {
  const skill = ruleCatalog.find((r) => r.code === 'vladenie-oruzhiem');
  const famKinzhal = ruleCatalog.find((r) => r.code === 'fam-kinzhal-nozh');

  it('домен «Владения оружием» = семьи (weapon_family); экземпляр с доменом', () => {
    const model = service.build(makeBuild(), ruleCatalog, config, keywords);
    const ability = model.abilities.find((a) => a.code === 'vladenie-oruzhiem');
    expect(ability?.multiple).toBe(true);
    expect(ability?.domainOptions.length).toBeGreaterThanOrEqual(20);
    expect(ability?.domainOptions.some((option) => option.code === 'fam-kinzhal-nozh')).toBe(true);
    expect(famKinzhal?.type).toBe('weapon_family');
  });

  it('бюджет списывает лестницу семьи (Кинжал и Нож [1,3,5])', () => {
    const build = makeBuild({
      abilities: [
        { ruleId: skill?.id ?? '', level: 1, domain: 'Кинжал и Нож', domainCode: 'fam-kinzhal-nozh', zone: 'or' },
      ],
    });
    const model = service.build(build, ruleCatalog, config, keywords);
    expect(model.budgets.or.spent).toBe(1);

    const lvl3 = service.build(
      makeBuild({
        abilities: [
          { ruleId: skill?.id ?? '', level: 3, domain: 'Кинжал и Нож', domainCode: 'fam-kinzhal-nozh', zone: 'or' },
        ],
      }),
      ruleCatalog,
      config,
      keywords,
    );
    expect(lvl3.budgets.or.spent).toBe(1 + 3 + 5);
  });

  it('лимит уровня экземпляра = длина лестницы семьи (Лук 5 уровней)', () => {
    const base = makeBuild();
    const withLuk = characterBuildService.addAbilityInstance(base, skill?.id ?? '', 'Лук', ruleCatalog, {
      zone: 'or',
      domainCode: 'fam-luk',
    });
    const up = characterBuildService.setAbilityInstanceLevel(withLuk, skill?.id ?? '', 'Лук', 5, ruleCatalog, {
      zone: 'or',
    });
    expect(up.abilities.find((a) => a.ruleId === skill?.id && a.domain === 'Лук')?.level).toBe(5);

    const over = characterBuildService.setAbilityInstanceLevel(withLuk, skill?.id ?? '', 'Лук', 6, ruleCatalog, {
      zone: 'or',
    });
    expect(over.abilities.find((a) => a.ruleId === skill?.id && a.domain === 'Лук')?.level).toBe(1);
  });
});

describe('«Владение оружием» — мастерство оружий семьи (C3)', () => {
  const skill = ruleCatalog.find((r) => r.code === 'vladenie-oruzhiem');
  const famKinzhal = ruleCatalog.find((r) => r.code === 'fam-kinzhal-nozh');

  it('weaponProficiencyLevels: семьи из экземпляров владения', () => {
    const levels = weaponProficiencyService.weaponProficiencyLevels(
      [
        { ruleId: skill?.id ?? '', level: 3, domain: 'Кинжал и Нож', domainCode: 'fam-kinzhal-nozh' },
        { ruleId: skill?.id ?? '', level: 2, domain: 'Лук', domainCode: 'fam-luk' },
      ],
      ruleCatalog,
    );
    expect(levels.get('fam-kinzhal-nozh')).toBe(3);
    expect(levels.get('fam-luk')).toBe(2);
  });

  it('weaponMasteryEntries: ближний бой — оружия семьи (Кинжал, Стилет, Метательный нож) со бонусом', () => {
    const model = service.build(
      makeBuild({
        abilities: [
          { ruleId: skill?.id ?? '', level: 3, domain: 'Кинжал и Нож', domainCode: 'fam-kinzhal-nozh', zone: 'or' },
        ],
      }),
      ruleCatalog,
      config,
      keywords,
    );
    const melee = model.characteristics.find((c) => c.code === 'melee-combat');
    const levels = weaponProficiencyService.weaponProficiencyLevels(
      [{ ruleId: skill?.id ?? '', level: 3, domain: 'Кинжал и Нож', domainCode: 'fam-kinzhal-nozh' }],
      ruleCatalog,
    );
    const entries = weaponProficiencyService.weaponMasteryEntries('melee-combat', melee!, levels, ruleCatalog);
    const names = entries.map((entry) => entry.weaponName).sort();
    expect(names).toContain('Кинжал');
    expect(names).toContain('Стилет');
    expect(names).toContain('Метательный нож');
    expect(entries.every((entry) => entry.bonus === 3)).toBe(true);
    // дальний бой: у семьи «Кинжал и Нож» есть метательные профили → тайл тоже есть
    const ranged = model.characteristics.find((c) => c.code === 'ranged-combat');
    const rangedEntries = weaponProficiencyService.weaponMasteryEntries('ranged-combat', ranged!, levels, ruleCatalog);
    expect(rangedEntries.length).toBeGreaterThan(0);
  });

  it('fam-kinzhal-nozh — правило weapon_family (семья с лестницей)', () => {
    expect(famKinzhal?.type).toBe('weapon_family');
    expect((famKinzhal?.spec as { costs?: number[] }).costs).toEqual([1, 3, 5]);
  });

  describe('бюджет денег + инвентарь (шаг «Инвентарь», R2/R6)', () => {
    const dagger = ruleCatalog.find((r) => r.code === 'kinzhal') ?? ruleCatalog.find((r) => r.id === 'rule-404');
    const sword =
      ruleCatalog.find((r) => r.code === 'fehtovalnyy-mech') ?? ruleCatalog.find((r) => r.id === 'rule-407');
    const moneyConfig: CharacterCreationConfig = { osTotal: 20, orTotal: 12, moneyBudget: 3000 };

    it('покупка списывает деньги; бюджет считает потраченное; превышение гейтится', () => {
      expect(dagger).toBeDefined();
      expect(sword).toBeDefined();

      // New: после донорма (ensureInventoryBaseline) build.money = effectiveMoney.
      let build = makeBuild({ money: 3000 });
      let model = service.build(build, ruleCatalog, moneyConfig);
      expect(model.budgets.money.total).toBe(3000);
      expect(model.budgets.money.spent).toBe(0);
      expect(model.budgets.money.exceeded).toBe(false);

      // Покупка кинжала (800 гм): деньги уменьшаются, потрачено = 800.
      build = characterBuildService.buyItem(build, dagger?.id ?? '', 1, ruleCatalog);
      model = service.build(build, ruleCatalog, moneyConfig);
      expect(build.money).toBe(2200);
      expect(model.budgets.money.spent).toBe(800);
      expect(model.budgets.money.exceeded).toBe(false);

      // Покупка двух мечей (2000 гм каждый): уходим в минус → превышение (R6 — в модели разрешено).
      build = characterBuildService.buyItem(build, sword?.id ?? '', 2, ruleCatalog);
      model = service.build(build, ruleCatalog, moneyConfig);
      expect(build.money).toBe(-1800);
      expect(build.inventory.filter((item) => item.ruleId === sword?.id)).toHaveLength(2);
      expect(model.budgets.money.spent).toBe(4800);
      expect(model.budgets.money.exceeded).toBe(true);
    });

    it('отмена покупки возвращает деньги в бюджет', () => {
      const baseline = { inventory: [], money: 3000 };
      let build = makeBuild({ money: 3000 });
      build = characterBuildService.buyItem(build, dagger?.id ?? '', 1, ruleCatalog);

      build = characterBuildService.cancelItemPurchase(build, baseline, dagger?.id ?? '', 1, ruleCatalog);

      expect(build.money).toBe(3000);
      expect(build.inventory).toEqual([]);
      const model = service.build(build, ruleCatalog, moneyConfig);
      expect(model.budgets.money.exceeded).toBe(false);
    });
  });

  describe('экипировка влияет на характеристики (доспех/щит)', () => {
    it('латный доспех: штраф к силе −2 и потолок ловкости 4↓²', () => {
      const ahtar = ruleCatalog.find((r) => r.code === 'ahtar');
      const armor = ruleCatalog.find((r) => r.name === 'Латный доспех');
      expect(ahtar).toBeDefined();
      expect(armor).toBeDefined();

      const model = service.build(
        makeBuild({
          raceRuleId: ahtar!.id,
          inventory: [{ id: 1, ruleId: armor!.id, quantity: 1, equipped: true }],
        }),
        ruleCatalog,
        config,
      );

      const strength = model.characteristics.find((c) => c.code === 'strength');
      const dexterity = model.characteristics.find((c) => c.code === 'dexterity');
      // база 3 + штраф −2 → 4↓ (значение 2)
      expect(strength?.value).toEqual({ base: 4, size: -1 });
      expect(strength?.modifiers.some((m) => m.sourceRuleId === armor!.id && m.delta === -2)).toBe(true);
      // потолок 4↓² (значение 1): база 3 зажимается
      expect(dexterity?.value).toEqual({ base: 4, size: -2 });
      const capMod = dexterity?.modifiers.find((m) => m.limit != null);
      expect(capMod?.limit).toEqual({ base: 4, size: -2 });
      expect(capMod?.sourceRuleId).toBe(armor!.id);
      // фикс. потолок (max_agility) формулы не несёт
      expect(capMod?.limitFormula ?? null).toBeNull();
    });

    it('щит ограничивает Ловкость формулой [Сила] (значение и формула в модификаторе)', () => {
      const liten = ruleCatalog.find((r) => r.code === 'liten');
      const shield = ruleCatalog.find((r) => r.name === 'Маленький щит');
      expect(liten).toBeDefined();
      expect(shield).toBeDefined();

      const model = service.build(
        makeBuild({
          raceRuleId: liten!.id,
          inventory: [{ id: 1, ruleId: shield!.id, quantity: 1, equipped: true }],
        }),
        ruleCatalog,
        config,
      );

      const dexterity = model.characteristics.find((c) => c.code === 'dexterity');
      // Ловкость 4 (база) > потолок [Сила] = Сила 5↓ (значение 2) → зажата до 5↓
      expect(dexterity?.value).toEqual({ base: 5, size: -1 });
      const capMod = dexterity?.modifiers.find((m) => m.limit != null);
      expect(capMod?.limit).toEqual({ base: 5, size: -1 });
      expect(capMod?.limitFormula).toBe('Сила');
      expect(capMod?.sourceRuleId).toBe(shield!.id);
    });

    it('неэкипированный предмет не влияет на характеристики', () => {
      const ahtar = ruleCatalog.find((r) => r.code === 'ahtar');
      const armor = ruleCatalog.find((r) => r.name === 'Латный доспех');

      const model = service.build(
        makeBuild({
          raceRuleId: ahtar!.id,
          inventory: [{ id: 1, ruleId: armor!.id, quantity: 1, equipped: false }],
        }),
        ruleCatalog,
        config,
      );

      expect(model.characteristics.find((c) => c.code === 'strength')?.value).toEqual({ base: 3, size: 0 });
      expect(model.characteristics.find((c) => c.code === 'dexterity')?.value).toEqual({ base: 3, size: 0 });
    });
  });

  it('баг: врождённые черты характеристик не доплачиваются как «Общие черты»', () => {
    const surchargeMechanic: Mechanic = {
      id: 4,
      code: 'purchase_surcharge',
      name: 'Прогрессивная доплата',
      description: '',
      version: '1.0.0',
    };
    const innateRuleId = (code: string): string => ruleCatalog.find((r) => r.code === code)?.id ?? '';
    let build = makeBuild();
    build = characterBuildService.setAbilityParameter(build, innateRuleId('innate-strength'), 'x', 1, ruleCatalog);
    build = characterBuildService.setAbilityParameter(build, innateRuleId('innate-endurance'), 'x', 1, ruleCatalog);
    build = characterBuildService.setAbilityParameter(build, innateRuleId('innate-dexterity'), 'x', 1, ruleCatalog);

    const model = service.build(build, ruleCatalog, config, keywords, [surchargeMechanic]);

    // Три врождённые черты характеристик (свой блок «Характеристики») — НЕ «общие»: доплаты нет.
    expect(model.budgets.osSurcharge).toBeUndefined();
    // X=1 каждой: табличная цена 2 ОС, без доплаты.
    expect(model.budgets.os.spent).toBe(6);
  });

  it('основные боевые действия — зона or и явная секция каталога', () => {
    const model = service.build(makeBuild(), ruleCatalog, config, keywords);
    for (const code of ['dodge', 'block', 'simple-melee-attack', 'simple-ranged-attack', 'turn']) {
      const ability = model.abilities.find((entry) => entry.code === code);
      expect(
        ability?.zones.map((zone) => zone.zoneCode),
        code,
      ).toEqual(['or']);
      expect(ability?.automatic, code).toBe(true);
      expect(ability?.visible, code).toBe(false);
      const rule = ruleCatalog.find((entry) => entry.code === code);
      expect(rule?.catalogSection).toBe(
        code === 'simple-melee-attack' || code === 'simple-ranged-attack'
          ? 'scenes-combat-basic-attacks'
          : 'scenes-combat-defense',
      );
      expect(rule?.keywordIds, code).not.toContain(20);
    }
    expect(ruleCatalog.some((rule) => rule.code === 'melee-fighting')).toBe(false);
    expect(model.abilities.some((ability) => ability.code === 'melee-fighting')).toBe(false);
  });
});
