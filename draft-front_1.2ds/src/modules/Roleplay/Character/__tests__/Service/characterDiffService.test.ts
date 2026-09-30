import { describe, expect, it } from 'vitest';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import { characterDiffService } from '@/modules/Roleplay/Character/Service/Instance/characterDiffService';

const version = (partial: Partial<CharacterVersion> = {}): CharacterVersion =>
  ({
    name: 'Герой',
    shortDescription: null,
    fullDescription: null,
    spaceCode: 'actual',
    rulesRevision: 12,
    raceRuleCode: null,
    characteristics: [],
    resources: [],
    abilities: [],
    points: { osSpent: 0, olSpent: 0, orSpent: 0, olTotal: 0, orTotal: null },
    money: 10,
    ageYears: null,
    inventory: [],
    states: [],
    senses: [],
    ...partial,
  }) as CharacterVersion;

describe('CharacterDiffService', () => {
  it('не считает равные нормализованные листы изменёнными', () => {
    const approved = version({
      abilities: [{ ruleCode: 'rule-ability', level: 1, parameters: { rank: 2, mode: 1 } }],
    });
    const actual = version({
      abilities: [{ ruleCode: 'rule-ability', level: 1, parameters: { mode: 1, rank: 2 } }],
    });

    const diff = characterDiffService.getCharacterDiff(approved, actual);

    expect(diff.availability).toBe('complete');
    expect(diff.hasChanges).toBe(false);
    expect(diff.changes).toHaveLength(0);
    expect(diff.identity.compatible).toBe(true);
  });

  it('считает изменения денег, ресурса, инвентаря и дополнительных полей', () => {
    const diff = characterDiffService.getCharacterDiff(
      version(),
      version({
        money: 20,
        budgets: { osTotal: 10, moneyBudget: 100 },
        ethnicityCode: 'eth-elf',
        nativeLanguageText: 'Старшая речь',
        resources: [{ ruleCode: 'health', current: { base: 2, size: 0 }, base: { base: 5, size: 0 }, bonuses: [] }],
        inventory: [{ id: 3, ruleCode: null, quantity: 2, equipped: true, name: 'Факел' }],
      }),
    );

    expect(diff.hasChanges).toBe(true);
    expect(diff.changes.map((change) => change.path)).toEqual(
      expect.arrayContaining([
        'money',
        'budgets',
        'ethnicityCode',
        'nativeLanguageText',
        'resources.health#1',
        'inventory.null|3#1',
      ]),
    );
  });

  it('сравнивает poison, wound и injury как persistent state data', () => {
    const approved = version({
      states: [
        {
          stateRuleCode: 'wound',
          value: 2,
          wound: { bandage: 0, clotting: 1, internal: false, aided: false, heldBy: 'character:2' },
        },
        {
          stateRuleCode: 'injury',
          maim: { permanent: false, healTotal: 3, healUnit: 'years' },
        },
        {
          stateRuleCode: 'poisoned',
          poison: { poisonRuleCode: 'venom', damage_type_code: 'piercing', strength: { base: 2, size: 0 } },
        },
      ],
    });
    const actual = version({
      states: [
        {
          stateRuleCode: 'poisoned',
          poison: { poisonRuleCode: 'venom', damage_type_code: 'piercing', strength: { base: 3, size: 0 } },
        },
        {
          stateRuleCode: 'injury',
          maim: { permanent: false, healTotal: 4, healUnit: 'years' },
        },
        {
          stateRuleCode: 'wound',
          value: 2,
          wound: { bandage: 1, clotting: 1, internal: false, aided: false, heldBy: 'character:9' },
        },
      ],
    });

    const diff = characterDiffService.getCharacterDiff(approved, actual);

    expect(diff.changes.filter((change) => change.section === 'states')).toHaveLength(3);
    expect(diff.changes.some((change) => change.after === null)).toBe(false);
  });

  it('не считает только heldBy изменением и не зависит от порядка повторяющихся states', () => {
    const approved = version({
      states: [
        { stateRuleCode: 'poisoned', value: 1 },
        { stateRuleCode: 'poisoned', value: 2 },
        { stateRuleCode: 'wound', wound: { bandage: 0, clotting: 1, internal: false, aided: false, heldBy: 'a' } },
      ],
    });
    const actual = version({
      states: [
        { stateRuleCode: 'wound', wound: { bandage: 0, clotting: 1, internal: false, aided: false, heldBy: 'b' } },
        { stateRuleCode: 'poisoned', value: 2 },
        { stateRuleCode: 'poisoned', value: 1 },
      ],
    });

    const diff = characterDiffService.getCharacterDiff(approved, actual);

    expect(diff.hasChanges).toBe(false);
  });

  it('различает first submission и недоступный actual', () => {
    const firstSubmission = characterDiffService.getCharacterDiff(null, version());
    const missingActual = characterDiffService.getCharacterDiff(version(), null);

    expect(firstSubmission).toMatchObject({
      availability: 'firstSubmission',
      hasChanges: true,
    });
    expect(missingActual).toMatchObject({
      availability: 'missingActual',
      hasChanges: false,
    });
  });

  it('выносит identity guards из обычного semantic diff', () => {
    const diff = characterDiffService.getCharacterDiff(
      version({ spaceCode: 'old', rulesRevision: 1 }),
      version({ spaceCode: 'new', rulesRevision: 2 }),
    );

    expect(diff.identity.compatible).toBe(false);
    expect(diff.hasChanges).toBe(false);
    expect(diff.changes).toHaveLength(0);
  });
});
