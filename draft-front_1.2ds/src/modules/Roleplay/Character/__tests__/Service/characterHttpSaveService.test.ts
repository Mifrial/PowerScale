import { describe, expect, it } from 'vitest';
import { characterHttpSaveService } from '@/modules/Roleplay/Character/Service/Instance/characterHttpSaveService';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';

const choices: CharacterBuild = {
  name: 'Воюн',
  shortDescription: null,
  fullDescription: null,
  spaceId: 1,
  spaceCode: 'razrabotka',
  rulesRevision: 3,
  raceRuleCode: 'human',
  characteristicPurchases: [{ characteristicCode: 'strength', cost: 2 }],
  abilities: [{ ruleCode: 'silovoy-udar', level: 1 }],
  resources: [],
  inventory: [],
  states: [],
  money: 40,
  ageYears: 20,
  olTotal: 0,
};

const config: CharacterCreationConfig = { osTotal: 10, orTotal: 8, moneyBudget: 100 };

describe('CharacterHttpSaveService documentOf', () => {
  it('передаёт параметры экземпляра и донора выданного предмета', () => {
    const body = characterHttpSaveService.documentFromVersion(
      {
        name: 'Колдунец',
        shortDescription: null,
        fullDescription: null,
        spaceCode: 'razrabotka',
        rulesRevision: 50,
        raceRuleCode: 'kogir',
        characteristics: [],
        resources: [],
        abilities: [
          { ruleCode: 'magic-core-capacity', level: 1, parameters: { x: { base: 4, size: 0 } } },
          { ruleCode: 'discharge', level: 1, zone: 'or', targets: { core: 5 } },
        ],
        points: { osSpent: 0, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: 0 },
        money: 0,
        ageYears: 18,
        inventory: [
          {
            id: 5,
            ruleCode: 'magic-core',
            quantity: 1,
            equipped: true,
            parameters: { power: { base: 4, size: 0 } },
            grantedBy: { ruleCode: 'magic-core-capacity' },
            payload: { volume: 30, recorded_spells: [{ ruleCode: 'spark', level: 1 }] },
          },
        ],
        states: [],
        senses: [],
      },
      config,
    );

    expect(body.inventory).toEqual([
      {
        id: 5,
        quantity: 1,
        equipped: true,
        ruleCode: 'magic-core',
        parameters: { power: { base: 4, size: 0 } },
        grantedBy: { ruleCode: 'magic-core-capacity' },
        payload: { volume: 30, recorded_spells: [{ ruleCode: 'spark', level: 1 }] },
      },
    ]);
    expect(body.abilities).toContainEqual({
      ruleCode: 'discharge',
      level: 1,
      zone: 'or',
      targets: { core: 5 },
    });
  });

  it('документ NPC без spaceId и с пустыми лимитами', () => {
    const body = characterHttpSaveService.documentOf(choices, {
      osTotal: null,
      orTotal: null,
      moneyBudget: null,
    });

    expect(body).not.toHaveProperty('spaceId');
    expect(body).not.toHaveProperty('revision');
    expect(body.limits).toEqual({ os: null, or: null });
    expect(body.shortDescription).toBe('');
    expect(body.money).toBe(40);
  });
});

describe('CharacterHttpSaveService migrate bodies', () => {
  it('remap: только id, revision, expectedVersion', () => {
    const body = characterHttpSaveService.migrateRemapBody(7, 4, 2);

    expect(body).toEqual({ id: 7, revision: 4, expectedVersion: 2 });
    expect(body).not.toHaveProperty('spaceId');
    expect(body).not.toHaveProperty('toSpaceId');
    expect(body).not.toHaveProperty('name');
  });

  it('resume: списки, limits без money, money сверху, без spaceId', () => {
    const body = characterHttpSaveService.migrateResumeBody(7, 4, 2, choices, config);
    const limits = body.limits as Record<string, unknown>;

    expect(body.id).toBe(7);
    expect(body.revision).toBe(4);
    expect(body.expectedVersion).toBe(2);
    expect(body.name).toBe('Воюн');
    expect(body.raceCode).toBe('human');
    expect(body.customRules).toEqual([]);
    expect(body.abilities).toEqual([{ ruleCode: 'silovoy-udar', level: 1 }]);
    expect(body.inventory).toEqual([]);
    expect(body.characteristicPurchases).toEqual([{ characteristicCode: 'strength', cost: 2 }]);
    expect(body.money).toBe(40);
    expect(limits).toEqual({ os: 10, or: 8 });
    expect(limits).not.toHaveProperty('money');
    expect(body).not.toHaveProperty('spaceId');
    expect(body).not.toHaveProperty('toSpaceId');
  });
});
