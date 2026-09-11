import { describe, expect, it } from 'vitest';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import { CharacterEditorService } from '@/modules/Roleplay/Character/Service/CharacterEditorService';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { keywords } from '@/modules/Roleplay/Keyword/Mock/mockKeywords';

const service = new CharacterEditorService();
const config: CharacterCreationConfig = { osTotal: 20, orTotal: 12, moneyBudget: 100 };

function makeBuild(overrides: Partial<CharacterBuild> = {}): CharacterBuild {
  return {
    name: 'Тест',
    shortDescription: null,
    fullDescription: null,
    spaceId: 1,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    raceRuleCode: null,
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

describe('изучение заклинания как экземпляр пути', () => {
  it('разряд закрыт, пока нет становления; после становления открыт', () => {
    expect(
      service
        .build(makeBuild(), ruleCatalog, config, keywords)
        .abilities.find((ability) => ability.ruleCode === 'discharge')?.levels[0]?.met,
    ).toBe(false);

    const withPath = makeBuild({ abilities: [{ ruleCode: 'becoming-arcanist', level: 1 }] });
    const discharge = service
      .build(withPath, ruleCatalog, config, keywords)
      .abilities.find((ability) => ability.ruleCode === 'discharge');
    expect(discharge?.levels[0]?.met).toBe(true);
    expect(discharge?.multiple).toBe(true);
    expect(discharge?.domainOptions).toEqual([expect.objectContaining({ code: 'arcanist', name: 'Арканист' })]);

    const learned = characterBuildService.addAbilityInstance(withPath, 'discharge', 'Арканист', ruleCatalog, {
      zone: 'or',
      domainCode: 'arcanist',
    });
    expect(learned.abilities.some((ability) => ability.ruleCode === 'discharge' && ability.domain === 'Арканист')).toBe(
      true,
    );
  });

  it('пробуждение требует магическую мощь 4↓', () => {
    const becomingOf = (power: { base: number; size: number }) =>
      service
        .build(
          makeBuild({
            abilities: [{ ruleCode: 'magic-core-capacity', level: 1, parameters: { x: power } }],
          }),
          ruleCatalog,
          config,
          keywords,
        )
        .abilities.find((ability) => ability.ruleCode === 'psionic-awakening');

    expect(becomingOf({ base: 3, size: -1 })?.levels[0]?.met).toBe(false);
    expect(becomingOf({ base: 4, size: -1 })?.levels[0]?.met).toBe(true);
  });

  it('пробуждение открывает одно заклинание пути псионика за 0, без самого пути', () => {
    const withCore = makeBuild({
      abilities: [
        { ruleCode: 'magic-core-capacity', level: 1, parameters: { x: { base: 4, size: -1 } } },
        { ruleCode: 'psionic-awakening', level: 1 },
      ],
    });
    const model = service.build(withCore, ruleCatalog, config, keywords);
    const discharge = model.abilities.find((ability) => ability.ruleCode === 'discharge');
    expect(discharge?.levels[0]?.met).toBe(true);
    expect(discharge?.domainOptions).toEqual([expect.objectContaining({ code: 'psionic', name: 'Псионик' })]);
    expect(discharge?.nextInstanceCost).toBe(0);

    const learned = characterBuildService.addAbilityInstance(withCore, 'discharge', 'Псионик', ruleCatalog, {
      zone: 'or',
      domainCode: 'psionic',
    });
    const after = service.build(learned, ruleCatalog, config, keywords);
    expect(after.budgets.or.spent).toBe(0);
    const strike = after.abilities.find((ability) => ability.ruleCode === 'lightning-strike');
    expect(strike?.levels[0]?.met).toBe(false);
  });

  it('пробуждение не даёт выбрать Арканиста и не открывает пару «два за 1»', () => {
    const withBoth = makeBuild({
      abilities: [
        { ruleCode: 'magic-core-capacity', level: 1, parameters: { x: { base: 4, size: -1 } } },
        { ruleCode: 'psionic-awakening', level: 1 },
        { ruleCode: 'estestvoznanie', level: 1 },
        { ruleCode: 'becoming-arcanist', level: 1 },
        { ruleCode: 'discharge', level: 1, domain: 'Псионик', domainCode: 'psionic' },
      ],
    });
    const model = service.build(withBoth, ruleCatalog, config, keywords);
    const discharge = model.abilities.find((ability) => ability.ruleCode === 'discharge');
    expect(discharge?.instances[0]?.bound).toBe(true);
    expect(discharge?.instances[0]?.paidCost).toBe(0);
    expect(discharge?.domainOptions.map((option) => option.code)).toEqual(['arcanist']);
    expect(discharge?.nextInstanceCost).toBe(1);
    const strike = model.abilities.find((ability) => ability.ruleCode === 'lightning-strike');
    expect(strike?.domainOptions).toEqual([]);
    expect(strike?.nextInstanceCost).toBeUndefined();
    expect(model.budgets.or.spent).toBeGreaterThanOrEqual(2);

    const forget = characterBuildService.removeAbilityInstance(withBoth, 'discharge', 'Псионик', ruleCatalog, {
      domainCode: 'psionic',
    });
    expect(forget.abilities.some((ability) => ability.ruleCode === 'discharge')).toBe(true);

    const droppedAwakening = characterBuildService.setAbilityLevel(withBoth, 'psionic-awakening', 0, ruleCatalog, {
      zone: 'or',
    });
    expect(droppedAwakening.abilities.some((ability) => ability.ruleCode === 'discharge')).toBe(false);
  });

  it('цепная молния не открывается становлением (нужен грант не-spell)', () => {
    const withSpells = makeBuild({
      abilities: [
        { ruleCode: 'becoming-arcanist', level: 1 },
        { ruleCode: 'lightning-strike', level: 1, domain: 'Арканист', domainCode: 'arcanist' },
      ],
    });
    const chain = service
      .build(withSpells, ruleCatalog, config, keywords)
      .abilities.find((ability) => ability.ruleCode === 'chain-lightning');
    expect(chain?.levels[0]?.met).toBe(false);
  });

  it('цепная молния открывается подменой структур', () => {
    const withSubstitution = makeBuild({
      abilities: [
        { ruleCode: 'becoming-arcanist', level: 1 },
        { ruleCode: 'structure-substitution', level: 1 },
        { ruleCode: 'lightning-strike', level: 1, domain: 'Арканист', domainCode: 'arcanist' },
      ],
    });
    const chain = service
      .build(withSubstitution, ruleCatalog, config, keywords)
      .abilities.find((ability) => ability.ruleCode === 'chain-lightning');
    expect(chain?.levels[0]?.met).toBe(true);
  });

  it('аккуратное волшебство изучается как экземпляр пути, не свободный домен', () => {
    const closed = service
      .build(makeBuild({ abilities: [{ ruleCode: 'becoming-arcanist', level: 1 }] }), ruleCatalog, config, keywords)
      .abilities.find((ability) => ability.ruleCode === 'careful-magic');
    expect(closed?.levels[0]?.met).toBe(false);

    const open = makeBuild({
      abilities: [
        { ruleCode: 'becoming-arcanist', level: 1 },
        { ruleCode: 'structure-substitution', level: 1 },
      ],
    });
    const careful = service
      .build(open, ruleCatalog, config, keywords)
      .abilities.find((a) => a.ruleCode === 'careful-magic');
    expect(careful?.multiple).toBe(true);
    expect(careful?.domainRef).toBe('magic-path');
    expect(careful?.levels[0]?.met).toBe(true);
    expect(careful?.domainOptions).toEqual([expect.objectContaining({ code: 'arcanist', name: 'Арканист' })]);
  });

  it('шаман открывает аккуратное волшебство грантом non_spell', () => {
    const open = makeBuild({ abilities: [{ ruleCode: 'otherworldly-contact', level: 1 }] });
    const careful = service
      .build(open, ruleCatalog, config, keywords)
      .abilities.find((ability) => ability.ruleCode === 'careful-magic');
    expect(careful?.levels[0]?.met).toBe(true);
    expect(careful?.domainOptions).toEqual([expect.objectContaining({ code: 'shaman', name: 'Шаман' })]);

    const learned = characterBuildService.addAbilityInstance(open, 'careful-magic', 'Шаман', ruleCatalog, {
      zone: 'or',
      domainCode: 'shaman',
    });
    expect(
      learned.abilities.some((ability) => ability.ruleCode === 'careful-magic' && ability.domainCode === 'shaman'),
    ).toBe(true);
  });

  it('шаман не перекупает псионический разряд и берёт удар молнии по включению пути', () => {
    const mixed = makeBuild({
      abilities: [
        { ruleCode: 'otherworldly-contact', level: 1 },
        { ruleCode: 'discharge', level: 1, domain: 'Псионик', domainCode: 'psionic' },
      ],
    });
    const model = service.build(mixed, ruleCatalog, config, keywords);
    const discharge = model.abilities.find((ability) => ability.ruleCode === 'discharge');
    expect(discharge?.domainOptions.map((option) => option.code)).not.toContain('shaman');
    const strike = model.abilities.find((ability) => ability.ruleCode === 'lightning-strike');
    expect(strike?.domainOptions).toEqual([expect.objectContaining({ code: 'shaman', name: 'Шаман' })]);

    const rejected = characterBuildService.addAbilityInstance(mixed, 'discharge', 'Шаман', ruleCatalog, {
      zone: 'or',
      domainCode: 'shaman',
    });
    expect(rejected.abilities.filter((ability) => ability.ruleCode === 'discharge')).toHaveLength(1);

    const withoutDischarge = makeBuild({ abilities: [{ ruleCode: 'otherworldly-contact', level: 1 }] });
    const illegal = characterBuildService.addAbilityInstance(
      withoutDischarge,
      'lightning-strike',
      'Шаман',
      ruleCatalog,
      { zone: 'or', domainCode: 'shaman' },
    );
    expect(illegal.abilities.some((ability) => ability.ruleCode === 'lightning-strike')).toBe(false);

    const learned = characterBuildService.addAbilityInstance(mixed, 'lightning-strike', 'Шаман', ruleCatalog, {
      zone: 'or',
      domainCode: 'shaman',
    });
    expect(
      learned.abilities.some((ability) => ability.ruleCode === 'discharge' && ability.domainCode === 'psionic'),
    ).toBe(true);
    const after = service.build(learned, ruleCatalog, config, keywords);
    const shamanStrike = after.abilities
      .find((ability) => ability.ruleCode === 'lightning-strike')
      ?.instances.find((instance) => instance.domainCode === 'shaman');
    expect(shamanStrike?.levels[0]?.met).toBe(true);
  });

  it('псионик не использует шаманский разряд для своего удара молнии', () => {
    const mixed = makeBuild({
      abilities: [
        { ruleCode: 'psionic-awakening', level: 1 },
        { ruleCode: 'psionic-control', level: 1 },
        { ruleCode: 'otherworldly-contact', level: 1 },
        { ruleCode: 'discharge', level: 1, domain: 'Шаман', domainCode: 'shaman' },
      ],
    });
    const strike = service
      .build(mixed, ruleCatalog, config, keywords)
      .abilities.find((ability) => ability.ruleCode === 'lightning-strike');
    expect(strike?.domainOptions.map((option) => option.code)).not.toContain('psionic');
  });

  it('setAbilityLevel не изучает заклинание-экземпляр', () => {
    const build = makeBuild({ abilities: [{ ruleCode: 'becoming-arcanist', level: 1 }] });
    const next = characterBuildService.setAbilityLevel(build, 'discharge', 1, ruleCatalog, { zone: 'or' });
    expect(next.abilities.some((ability) => ability.ruleCode === 'discharge')).toBe(false);
  });

  it('становление требует Естествознание и магическую мощь 3↓', () => {
    const dim = (base: number, size = 0) => ({ base, size });
    const becomingOf = (build: CharacterBuild) =>
      service
        .build(build, ruleCatalog, config, keywords)
        .abilities.find((ability) => ability.ruleCode === 'becoming-arcanist');

    expect(becomingOf(makeBuild())?.levels[0]?.met).toBe(false);
    expect(becomingOf(makeBuild({ abilities: [{ ruleCode: 'estestvoznanie', level: 1 }] }))?.levels[0]?.met).toBe(
      false,
    );
    expect(
      becomingOf(
        makeBuild({
          abilities: [
            { ruleCode: 'estestvoznanie', level: 1 },
            { ruleCode: 'magic-core-capacity', level: 1, parameters: { x: dim(3, -1) } },
          ],
        }),
      )?.levels[0]?.met,
    ).toBe(true);
  });
});
