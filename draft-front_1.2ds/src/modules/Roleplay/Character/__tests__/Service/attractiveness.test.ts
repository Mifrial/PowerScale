import { describe, expect, it } from 'vitest';
import { CharacterEditorService } from '@/modules/Roleplay/Character/Service/CharacterEditorService';
import { stateRuntimeEffectsService } from '@/modules/Roleplay/Character/Service/Instance/stateRuntimeEffectsService';
import { abilityCheckAdvantagesService } from '@/modules/Roleplay/Character/Service/Instance/abilityCheckAdvantagesService';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { keywords } from '@/modules/Roleplay/Rule/Mock/mockKeywords';
import { ATTRACTIVENESS_STATE_CODE } from '@/modules/Roleplay/Rule/init';

const config = { osTotal: 20, orTotal: 10, moneyBudget: 100 };
const service = new CharacterEditorService();

function makeBuild(overrides: Partial<CharacterBuild> = {}): CharacterBuild {
  return {
    name: 'Тест',
    shortDescription: null,
    fullDescription: null,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    spaceId: 1,
    raceRuleId: '',
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

describe('Привлекательность', () => {
  it('без черт внешности статус есть и равен 0', () => {
    const version = service.toVersion(makeBuild(), ruleCatalog, config, keywords);
    const rule = ruleCatalog.find((entry) => entry.code === ATTRACTIVENESS_STATE_CODE);
    const state = version.states.find((entry) => entry.stateRuleId === rule?.id);
    expect(state?.value).toBe(0);
  });

  it('Восхитительная + Чудесный голос: значение 3; ±1 на убеждение, 3 на обольщение, 0 на запугивание', () => {
    const gorgeous = ruleCatalog.find((entry) => entry.code === 'gorgeous')!;
    const voice = ruleCatalog.find((entry) => entry.code === 'wondrous-voice')!;
    const version = service.toVersion(
      makeBuild({
        abilities: [
          { ruleId: gorgeous.id, level: 1 },
          { ruleId: voice.id, level: 1 },
        ],
      }),
      ruleCatalog,
      config,
      keywords,
    );
    const rule = ruleCatalog.find((entry) => entry.code === ATTRACTIVENESS_STATE_CODE);
    expect(version.states.find((entry) => entry.stateRuleId === rule?.id)?.value).toBe(3);

    expect(
      stateRuntimeEffectsService.checkAdvantageFromStates(version, ruleCatalog, {
        kind: 'check',
        code: 'persuasion',
      }),
    ).toBe(1);
    expect(
      stateRuntimeEffectsService.checkAdvantageFromStates(version, ruleCatalog, {
        kind: 'check',
        code: 'seduction',
      }),
    ).toBe(3);
    expect(
      stateRuntimeEffectsService.checkAdvantageFromStates(version, ruleCatalog, {
        kind: 'check',
        code: 'intimidation',
      }),
    ).toBe(0);

    expect(
      abilityCheckAdvantagesService.checkAdvantageModifiersFromAbilities(version, ruleCatalog, {
        kind: 'check',
        code: 'voice-music',
      }),
    ).toEqual([expect.objectContaining({ delta: 1, source_label: 'Чудесный голос' })]);
  });

  it('Омерзительная: −1 на обман, −2 на обольщение', () => {
    const repulsive = ruleCatalog.find((entry) => entry.code === 'repulsive')!;
    const version = service.toVersion(
      makeBuild({ abilities: [{ ruleId: repulsive.id, level: 1 }] }),
      ruleCatalog,
      config,
      keywords,
    );
    expect(
      stateRuntimeEffectsService.checkAdvantageFromStates(version, ruleCatalog, { kind: 'check', code: 'deception' }),
    ).toBe(-1);
    expect(
      stateRuntimeEffectsService.checkAdvantageFromStates(version, ruleCatalog, { kind: 'check', code: 'seduction' }),
    ).toBe(-2);
  });
});
