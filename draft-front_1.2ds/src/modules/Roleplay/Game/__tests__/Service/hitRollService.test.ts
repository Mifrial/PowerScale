import { describe, expect, it } from 'vitest';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import { hitRollService } from '@/modules/Roleplay/Game/Service/Instance/hitRollService';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import { resolveStrikeProcedure } from '@/modules/Roleplay/Game/Utils/resolveStrikeProcedure';
import { strikeProcedureRegistry } from '@/modules/Roleplay/Game/Service/Strike/Instance/strikeProcedureRegistry';
import { strikeV1 } from '@/modules/Roleplay/Game/Service/Strike/strikeV1';
import { STRIKE_PROCEDURE_RULE_CODE } from '@/modules/Roleplay/Rule/init';

function rngFromDice(values: number[], faces = 6): DiceRng {
  let i = 0;

  return () => (values[i++] - 1) / faces;
}

const checkSimple: Rule = {
  id: null,
  code: 'check-simple',
  type: 'check',
  name: 'Простая проверка',
  description: '',
  spaceId: 1,
  spec: {
    type: 'check',
    difficulty_input: { kind: 'ask' },
    allowed_modes: 'both',
    attached_rule_codes: [],
  },
  keywordIds: [],
  mechanicId: null,
  createdAt: '2026-01-01T00:00:00Z',
};

const checkHit: Rule = {
  ...checkSimple,
  id: null,
  code: 'check-hit',
  name: 'Попадание',
  spec: {
    type: 'check',
    parent_check_code: 'check-simple',
    difficulty_input: { kind: 'ask' },
    allowed_modes: 'both',
  },
};

const strikeRule = (mechanicId: number): Rule => ({
  id: null,
  code: STRIKE_PROCEDURE_RULE_CODE,
  type: 'simple',
  name: 'Удар',
  description: '',
  spaceId: 1,
  keywordIds: [],
  mechanicId,
  createdAt: '2026-01-01T00:00:00Z',
});

const mechanics: Mechanic[] = [
  { id: 7, code: 'strike', name: 'Удар', description: '', version: '1.0.0' },
  { id: 8, code: 'strike', name: 'Удар', description: '', version: '2.0.0' },
];

describe('resolveStrikeProcedure', () => {
  it('без карточки — v1', () => {
    expect(resolveStrikeProcedure([], [])).toEqual(strikeV1);
  });

  it('срез ревизии выбирает хендлер по mechanic version', () => {
    strikeProcedureRegistry.register({
      code: 'strike',
      version: '2.0.0',
      ignoreDefense: { base: 0, size: 0 },
      dodgeEfficiency: { base: 5, size: 0 },
      minBlockEfficiency: { base: 4, size: -1 },
    });
    const resolved = resolveStrikeProcedure([strikeRule(8)], mechanics);
    expect(resolved.version).toBe('2.0.0');
    expect(resolved.ignoreDefense).toEqual({ base: 0, size: 0 });
    expect(resolveStrikeProcedure([strikeRule(7)], mechanics).version).toBe('1.0.0');
  });
});

describe('rollMeleeHit', () => {
  const attack = {
    itemName: 'Меч',
    profileType: 'strike' as const,
    accuracy: { base: 3, size: 0 },
    reach: 0,
    falloff: { base: 5, size: 0 },
  };
  const overview = {
    combat: {
      melee: {
        stat: { name: 'Общее', value: { base: 3, size: -1 } },
        weapons: [{ name: 'Меч', shortName: 'Меч', value: { base: 4, size: -1 } }],
      },
      ranged: null,
    },
    defense: { shield: null },
  } as unknown as CharacterOverview;

  it('игнор: атака vs 0↓, защитник не бросает', () => {
    const rolled = hitRollService.rollMeleeHit(
      {
        attackerLabel: 'А',
        defenderLabel: 'Б',
        attack,
        attackerOverview: overview,
        defenderOverview: overview,
        reaction: 'ignore',
      },
      rngFromDice([2, 4, 1, 3]),
      [checkSimple, checkHit],
      [],
    );
    expect(rolled.defender).toBeNull();
    expect(rolled.attacker.check?.difficulty).toEqual({ base: 0, size: -1 });
    expect(rolled.attacker.spec.efficiency).toBe(3);
    expect(rolled.attacker.spec.diceCount).toBe(4);
    expect(rolled.attacker.spec.dieSize).toBe(-1);
  });

  it('уклон: размерная эффективность {4|-1}, размер на успехи', () => {
    const rolled = hitRollService.rollMeleeHit(
      {
        attackerLabel: 'А',
        defenderLabel: 'Б',
        attackerKey: 'character:3',
        defenderKey: 'character:1',
        attack,
        attackerOverview: overview,
        defenderOverview: overview,
        reaction: 'dodge',
        defenseEfficiency: { base: 4, size: -1 },
      },
      rngFromDice([2, 3, 4, 1, 2, 3, 4, 1]),
      [checkSimple, checkHit],
      [],
    );
    expect(rolled.defender).not.toBeNull();
    expect(rolled.defender?.spec.efficiency).toBe(4);
    expect(rolled.defender?.spec.dieSize).toBe(-2);
    expect(rolled.defender?.spec.poolSize).toBe(-1);
    expect(rolled.defender?.spec.efficiencySize).toBe(-1);
    expect(rolled.attacker.spec.actorKey).toBe('character:3');
    expect(rolled.defender?.spec.actorKey).toBe('character:1');
  });

  it('уклон: лучшее оружие ББ и помеха Ловкость/Восприятие', () => {
    const defender = {
      characteristics: [
        { ruleCode: 'dexterity', value: { base: 4, size: -2 } },
        { ruleCode: 'perception', value: { base: 4, size: -1 } },
      ],
      combat: {
        melee: {
          stat: { name: 'Общее', value: { base: 4, size: -1 } },
          weapons: [{ name: 'Боевая коса', shortName: 'Коса', value: { base: 4, size: 0 } }],
        },
        ranged: null,
      },
    } as unknown as CharacterOverview;
    const statRules: Rule[] = [
      { ...checkSimple, id: 7, code: 'dexterity', type: 'characteristic', name: 'Ловкость' },
      { ...checkSimple, id: null, code: 'perception', type: 'characteristic', name: 'Восприятие' },
    ];
    const rolled = hitRollService.rollMeleeHit(
      {
        attackerLabel: 'А',
        defenderLabel: 'Б',
        attack: {
          itemName: 'Кинжал',
          profileType: 'strike',
          accuracy: { base: 3, size: 0 },
          reach: 0,
          falloff: { base: 5, size: 0 },
        },
        attackerOverview: overview,
        defenderOverview: defender,
        reaction: 'dodge',
        defenseEfficiency: { base: 4, size: -1 },
      },
      rngFromDice([2, 3, 4, 1, 2, 3, 4, 1, 2, 3, 4, 1]),
      [checkSimple, checkHit, ...statRules],
      [],
    );
    expect(rolled.defender?.spec.diceCount).toBe(5);
    expect(rolled.defender?.spec.dieSize).toBe(-2);
    expect(rolled.defender?.spec.poolSize).toBe(-1);
    expect(rolled.defender?.spec.efficiencySize).toBe(-1);
    expect(rolled.defender?.spec.advantages).toEqual([]);
    expect(rolled.defender?.spec.masteryAdjustments).toEqual([
      { source_code: 'state', source_label: 'Ловкость/Восприятие', delta: -2 },
    ]);
  });

  it('throw: игнор vs 1↓, полоса дальнобойности поднимает размер', () => {
    const rolled = hitRollService.rollMeleeHit(
      {
        attackerLabel: 'А',
        defenderLabel: 'Б',
        attack: {
          itemName: 'Кинжал',
          profileType: 'throw',
          accuracy: { base: 3, size: 0 },
          reach: 2,
          falloff: { base: 2, size: 0 },
        },
        attackerOverview: {
          combat: {
            melee: {
              stat: { name: 'Общее', value: { base: 3, size: -1 } },
              weapons: [],
            },
            ranged: {
              stat: { name: 'Общее', value: { base: 3, size: -1 } },
              weapons: [{ name: 'Кинжал', shortName: 'Кинжал', value: { base: 3, size: -1 } }],
            },
          },
        } as unknown as CharacterOverview,
        defenderOverview: overview,
        reaction: 'ignore',
        distanceIpari: 3,
      },
      rngFromDice([2, 4, 1]),
      [checkSimple, checkHit],
      [],
    );
    expect(rolled.defender).toBeNull();
    expect(rolled.attacker.check?.difficulty).toEqual({ base: 1, size: 0 });
  });

  it('ДБ: cover 0 игнор vs 1↓; cover 2 vs 3↓', () => {
    const throwAttack = {
      itemName: 'Кинжал',
      profileType: 'throw' as const,
      accuracy: { base: 3, size: 0 },
      reach: 2,
      falloff: { base: 5, size: 0 },
    };
    const rangedOverview = {
      combat: {
        melee: { stat: { name: 'Общее', value: { base: 3, size: -1 } }, weapons: [] },
        ranged: {
          stat: { name: 'Общее', value: { base: 3, size: -1 } },
          weapons: [{ name: 'Кинжал', shortName: 'Кинжал', value: { base: 3, size: -1 } }],
        },
      },
    } as unknown as CharacterOverview;
    const none = hitRollService.rollMeleeHit(
      {
        attackerLabel: 'А',
        defenderLabel: 'Б',
        attack: throwAttack,
        attackerOverview: rangedOverview,
        defenderOverview: rangedOverview,
        reaction: 'ignore',
        distanceIpari: 1,
        cover: 0,
      },
      rngFromDice([2, 4, 1]),
      [checkSimple, checkHit],
      [],
    );
    expect(none.attacker.check?.difficulty).toEqual({ base: 1, size: -1 });
    expect(none.attacker.check?.ranged_hit).toEqual({
      cover: 0,
      defense_result: 0,
      reaction: 'ignore',
      range_size: 0,
      distance_ipari: 1,
    });
    const covered = hitRollService.rollMeleeHit(
      {
        attackerLabel: 'А',
        defenderLabel: 'Б',
        attack: throwAttack,
        attackerOverview: rangedOverview,
        defenderOverview: rangedOverview,
        reaction: 'ignore',
        distanceIpari: 1,
        cover: 2,
      },
      rngFromDice([2, 4, 1]),
      [checkSimple, checkHit],
      [],
    );
    expect(covered.attacker.check?.difficulty).toEqual({ base: 3, size: -1 });
    expect(covered.attacker.check?.ranged_hit?.cover).toBe(2);
  });

  it('ДБ уклон и блок: успехи в формулу, не joint', () => {
    const throwAttack = {
      itemName: 'Кинжал',
      profileType: 'throw' as const,
      accuracy: { base: 3, size: 0 },
      reach: 2,
      falloff: { base: 5, size: 0 },
    };
    const rangedOverview = {
      combat: {
        melee: { stat: { name: 'Общее', value: { base: 3, size: -1 } }, weapons: [] },
        ranged: {
          stat: { name: 'Общее', value: { base: 3, size: -1 } },
          weapons: [{ name: 'Кинжал', shortName: 'Кинжал', value: { base: 3, size: -1 } }],
        },
      },
    } as unknown as CharacterOverview;
    const zeroDodge = hitRollService.rollMeleeHit(
      {
        attackerLabel: 'А',
        defenderLabel: 'Б',
        attack: throwAttack,
        attackerOverview: rangedOverview,
        defenderOverview: rangedOverview,
        reaction: 'dodge',
        defenseEfficiency: { base: 4, size: -1 },
        distanceIpari: 1,
        cover: 2,
      },
      rngFromDice([5, 5, 5, 2, 2, 2]),
      [checkSimple, checkHit],
      [],
    );
    expect(zeroDodge.defender?.totalSuccesses).toBe(0);
    expect(zeroDodge.attacker.check?.difficulty).toEqual({ base: 3, size: -1 });
    const twoDodge = hitRollService.rollMeleeHit(
      {
        attackerLabel: 'А',
        defenderLabel: 'Б',
        attack: throwAttack,
        attackerOverview: rangedOverview,
        defenderOverview: rangedOverview,
        reaction: 'dodge',
        defenseEfficiency: { base: 4, size: -1 },
        distanceIpari: 1,
        cover: 2,
      },
      rngFromDice([1, 1, 6, 2, 2, 2]),
      [checkSimple, checkHit],
      [],
    );
    expect(twoDodge.defender?.totalSuccesses).toBe(2);
    expect(twoDodge.attacker.check?.difficulty).toEqual({ base: 4, size: -1 });
    expect(twoDodge.attacker.check?.ranged_hit).toMatchObject({
      cover: 2,
      defense_result: 2,
      reaction: 'dodge',
    });
    const block = hitRollService.rollMeleeHit(
      {
        attackerLabel: 'А',
        defenderLabel: 'Б',
        attack: throwAttack,
        attackerOverview: rangedOverview,
        defenderOverview: rangedOverview,
        reaction: 'block',
        defenseEfficiency: { base: 4, size: -1 },
        distanceIpari: 1,
        cover: 2,
      },
      rngFromDice([1, 1, 6, 2, 2, 2]),
      [checkSimple, checkHit],
      [],
    );
    expect(block.defender?.totalSuccesses).toBe(2);
    expect(block.attacker.check?.difficulty).toEqual({ base: 4, size: -1 });
  });

  it('фланг: 2 помехи защитнику; поворот снимает', () => {
    const withFlank = hitRollService.rollMeleeHit(
      {
        attackerLabel: 'А',
        defenderLabel: 'Б',
        attack,
        attackerOverview: overview,
        defenderOverview: overview,
        reaction: 'dodge',
        defenseEfficiency: { base: 4, size: -1 },
        flank: true,
      },
      rngFromDice([2, 3, 4, 1, 2, 3, 4, 1]),
      [checkSimple, checkHit],
      [],
    );
    expect(withFlank.defender?.spec.advantages).toEqual(
      expect.arrayContaining([{ source_code: 'circumstances', source_label: 'Фланговая атака', delta: -2 }]),
    );
    const turned = hitRollService.rollMeleeHit(
      {
        attackerLabel: 'А',
        defenderLabel: 'Б',
        attack,
        attackerOverview: overview,
        defenderOverview: overview,
        reaction: 'dodge',
        defenseEfficiency: { base: 4, size: -1 },
        flank: true,
        turn: true,
      },
      rngFromDice([2, 3, 4, 1, 2, 3, 4, 1]),
      [checkSimple, checkHit],
      [],
    );
    expect(turned.defender?.spec.advantages).toEqual([]);
  });

  it('Wide: один бросок attacker и по одному defense на каждую из 3 целей', () => {
    let rngCalls = 0;
    const rng: DiceRng = () => {
      rngCalls += 1;

      return 0;
    };
    const inputs = [1, 2, 3].map((target) => ({
      attackerLabel: 'А',
      defenderLabel: `Б${target}`,
      defenderKey: `character:${target}` as CombatEntityKey,
      attack,
      attackerOverview: overview,
      defenderOverview: overview,
      reaction: 'dodge' as const,
      defenseEfficiency: { base: 4, size: -1 },
    }));

    const rolled = hitRollService.rollMeleeWideHit(inputs, rng, [checkSimple, checkHit], []);

    expect(rolled.targetResults).toHaveLength(3);
    expect(rolled.targetResults.every((target) => target.defender !== null)).toBe(true);
    expect(rolled.attacker.rolls).toHaveLength(4);
    expect(rngCalls).toBe(
      rolled.attacker.rolls.length +
        rolled.targetResults.reduce((calls, target) => calls + (target.defender?.rolls.length ?? 0), 0),
    );
  });
});

describe('listBlockProfiles', () => {
  it('берёт equipped щит', () => {
    const version = {
      inventory: [{ id: 1, ruleCode: 'buckler', quantity: 1, equipped: true }],
    } as unknown as CharacterVersion;
    const rules: Rule[] = [
      {
        id: null,
        code: 'buckler',
        type: 'item',
        name: 'Баклер',
        description: '',
        spaceId: 1,
        spec: {
          category: 'equipment',
          cost_gm: 1,
          weight: { base: 1, size: 0 },
          special_rule_codes: [],
          shield: {
            min_strength: null,
            block: { efficiency: { base: 5, size: 0 }, defense: { base: 6, size: 0 }, resistances: [] },
          },
        },
        keywordIds: [],
        mechanicId: null,
        createdAt: '2026-01-01T00:00:00Z',
      },
    ];
    expect(hitRollService.listBlockProfiles(version, rules)).toEqual([
      { itemRuleCode: 'buckler', itemName: 'Баклер', efficiency: { base: 5, size: 0 } },
    ]);
  });

  it('shieldsOnly не берёт block_profile оружия', () => {
    const version = {
      inventory: [{ id: 1, ruleCode: 'sword', quantity: 1, equipped: true }],
    } as unknown as CharacterVersion;
    const rules: Rule[] = [
      {
        id: null,
        code: 'sword',
        type: 'item',
        name: 'Меч',
        description: '',
        spaceId: 1,
        spec: {
          category: 'equipment',
          cost_gm: 1,
          weight: { base: 1, size: 0 },
          special_rule_codes: [],
          weapon: {
            min_strength: null,
            durability: { base: 1, size: 0 },
            block_profile: { efficiency: { base: 4, size: -1 }, defense: { base: 4, size: 0 }, resistances: [] },
            weapon_profiles: [],
          },
        },
        keywordIds: [],
        mechanicId: null,
        createdAt: '2026-01-01T00:00:00Z',
      },
    ];
    expect(hitRollService.listBlockProfiles(version, rules).length).toBe(1);
    expect(hitRollService.listBlockProfiles(version, rules, { shieldsOnly: true })).toEqual([]);
  });
});

describe('weaponMasteryForAttack', () => {
  it('берёт тайл оружия по имени', () => {
    const overview = {
      combat: {
        melee: {
          stat: { name: 'Общее', value: { base: 3, size: -1 } },
          weapons: [{ name: 'Меч', shortName: 'Меч', value: { base: 5, size: 0 } }],
        },
        ranged: null,
      },
    } as unknown as CharacterOverview;
    expect(hitRollService.weaponMasteryForAttack(overview, { itemName: 'Меч', profileType: 'strike' })).toEqual({
      base: 5,
      size: 0,
    });
  });
});
