import { describe, expect, it } from 'vitest';
import type { DefenseLineOverview } from '@/modules/Roleplay/Character/Dto/Overview/DefenseOverview';
import type { DamageTypeHook } from '@/modules/Roleplay/Game/Dto/DamageTypeHook';
import {
  applyAttackDamage,
  defenseApCost,
  spendActionPoints,
  stackedResistance,
} from '@/modules/Roleplay/Game/Utils/applyAttackDamage';
import {
  buildAttackCalcPayload,
  formatAttackActionMessage,
  formatAttackResultMessage,
  formatAttackSrLabel,
  formatStrikeNarrativeMessage,
} from '@/modules/Roleplay/Game/Utils/attackDamageMessage';
import {
  DAMAGE_TYPE_HOOK_MECHANIC_BLUNT_KO,
  DAMAGE_TYPE_HOOK_MECHANIC_CUTTING_WOUNDS,
  DAMAGE_TYPE_HOOK_MECHANIC_EXHAUSTION_STUN,
  DAMAGE_TYPE_HOOK_MECHANIC_EXHAUSTION_WOUND,
  DAMAGE_TYPE_HOOK_MECHANIC_PAY_SR,
  DAMAGE_TYPE_HOOK_VERSION_1,
} from '@/modules/Roleplay/Rule/Constant/Damage/DAMAGE_TYPE_HOOKS';

function hook(
  mechanicCode: string,
  phase: DamageTypeHook['phase'],
  extra: Partial<DamageTypeHook> = {},
): DamageTypeHook {
  return { ruleCode: mechanicCode, mechanicCode, version: DAMAGE_TYPE_HOOK_VERSION_1, phase, ...extra };
}

function line(
  partial: Partial<DefenseLineOverview> & Pick<DefenseLineOverview, 'kind' | 'value'>,
): DefenseLineOverview {
  return {
    valueLabel: String(partial.value),
    durability: 0,
    sourceCode: null,
    sourceLabel: null,
    damageTypeLabel: null,
    damageTypeDative: null,
    damageTypeCode: null,
    ...partial,
  };
}

describe('applyAttackDamage', () => {
  it('урон = (оружие − сопротивление) × оставшееся РУ', () => {
    const result = applyAttackDamage({
      weaponDamage: { base: 4, size: 0 },
      sr: 3,
      damageTypeCode: 'blunt',
      defense: {
        armor: [
          {
            itemRuleId: 'a',
            itemName: 'Доспех',
            href: '',
            lines: [
              line({ kind: 'resistance', value: 1, damageTypeCode: 'blunt', durability: 1, sourceCode: 'armor' }),
            ],
            tiers: [],
          },
        ],
        constantDefense: 0,
        tiers: [],
        shield: null,
      },
      endurance: 3,
      hooks: [],
    });
    expect(result.hpDamage).toBe(9);
    expect(result.exhaustion).toBe(3);
  });

  it('тик DOT: SR 1, сопротивление режет урон в ноль', () => {
    const result = applyAttackDamage({
      weaponDamage: { base: 3, size: 0 },
      sr: 1,
      damageTypeCode: 'fire',
      defense: {
        armor: [
          {
            itemRuleId: 'a',
            itemName: 'Плащ',
            href: '',
            lines: [
              line({ kind: 'resistance', value: 10, durability: 5, damageTypeCode: 'fire', sourceCode: 'armor' }),
            ],
            tiers: [],
          },
        ],
        constantDefense: 0,
        tiers: [],
        shield: null,
      },
      endurance: 3,
      hooks: [],
    });
    expect(result.hpDamage).toBe(0);
    expect(result.exhaustion).toBe(0);
  });

  it('РУ атаки игнорирует слои с надёжностью ≤ РУ, РУ не тратятся', () => {
    const lines = [
      line({ kind: 'resistance', value: 5, durability: 2, sourceCode: 'armor', damageTypeCode: 'piercing' }),
      line({ kind: 'resistance', value: 1, durability: 3, sourceCode: 'mail', damageTypeCode: 'piercing' }),
    ];
    expect(stackedResistance(lines, 'piercing', 0)).toBe(6);
    expect(stackedResistance(lines, 'piercing', 4)).toBe(0);
    const result = applyAttackDamage({
      weaponDamage: { base: 4, size: 0 },
      sr: 4,
      damageTypeCode: 'piercing',
      defense: {
        armor: [{ itemRuleId: 'a', itemName: 'A', href: '', lines, tiers: [] }],
        constantDefense: 0,
        tiers: [],
        shield: null,
      },
      endurance: 5,
      hooks: [hook(DAMAGE_TYPE_HOOK_MECHANIC_PAY_SR, 'attack')],
    });
    expect(result.remainingSr).toBe(4);
    expect(result.resistance).toBe(0);
    expect(result.hpDamage).toBe(16);
    expect(result.layers).toEqual([
      expect.objectContaining({ ignored: true, reason: 'sr', value: 5, durability: 2 }),
      expect.objectContaining({ ignored: true, reason: 'sr', value: 1, durability: 3 }),
    ]);
  });

  it('защита надёжности 6 игнорируется при 8 РУ колющего', () => {
    const lines = [line({ kind: 'defense', value: 3, durability: 6, sourceCode: 'armor' })];
    const result = applyAttackDamage({
      weaponDamage: { base: 1, size: 0 },
      sr: 8,
      damageTypeCode: 'piercing',
      defense: {
        armor: [{ itemRuleId: 'quilted', itemName: 'Стёганый доспех', href: '', lines, tiers: [] }],
        constantDefense: 3,
        tiers: [],
        shield: null,
      },
      endurance: 4,
      hooks: [hook(DAMAGE_TYPE_HOOK_MECHANIC_PAY_SR, 'attack')],
    });
    expect(result.resistance).toBe(0);
    expect(result.hpDamage).toBe(8);
    expect(result.layers[0]).toMatchObject({
      itemName: 'Стёганый доспех',
      kind: 'defense',
      value: 3,
      durability: 6,
      ignored: true,
      reason: 'sr',
    });
  });

  it('без хука атаки слои по РУ не режутся', () => {
    const lines = [line({ kind: 'resistance', value: 5, durability: 2, sourceCode: 'armor', damageTypeCode: 'blunt' })];
    const result = applyAttackDamage({
      weaponDamage: { base: 6, size: 0 },
      sr: 4,
      damageTypeCode: 'blunt',
      defense: {
        armor: [{ itemRuleId: 'a', itemName: 'A', href: '', lines, tiers: [] }],
        constantDefense: 0,
        tiers: [],
        shield: null,
      },
      endurance: 5,
      hooks: [],
    });
    expect(result.remainingSr).toBe(4);
    expect(result.resistance).toBe(5);
    expect(result.hpDamage).toBe(4);
  });

  it('режущий пишет рану, не HP; дробящий KO при РУ≥6', () => {
    const cutting = applyAttackDamage({
      weaponDamage: { base: 3, size: 0 },
      sr: 2,
      damageTypeCode: 'cutting',
      defense: null,
      endurance: 4,
      hooks: [hook(DAMAGE_TYPE_HOOK_MECHANIC_CUTTING_WOUNDS, 'apply')],
    });
    expect(cutting.hpDamage).toBe(0);
    expect(cutting.cuttingWound).toBe(6);

    const blunt = applyAttackDamage({
      weaponDamage: { base: 2, size: 0 },
      sr: 6,
      damageTypeCode: 'blunt',
      defense: null,
      endurance: 10,
      hooks: [
        hook(DAMAGE_TYPE_HOOK_MECHANIC_EXHAUSTION_STUN, 'apply'),
        hook(DAMAGE_TYPE_HOOK_MECHANIC_BLUNT_KO, 'apply'),
      ],
    });
    expect(blunt.knockout).toBe(true);
    expect(blunt.hpDamage).toBe(12);
  });

  it('истощение → стан и рана × множитель', () => {
    const result = applyAttackDamage({
      weaponDamage: { base: 6, size: 0 },
      sr: 1,
      damageTypeCode: 'slashing',
      defense: null,
      endurance: 3,
      hooks: [
        hook(DAMAGE_TYPE_HOOK_MECHANIC_EXHAUSTION_STUN, 'apply'),
        hook(DAMAGE_TYPE_HOOK_MECHANIC_EXHAUSTION_WOUND, 'apply', { woundMultiplier: 2 }),
      ],
    });
    expect(result.exhaustion).toBe(2);
    expect(result.stun).toBe(2);
    expect(result.wound).toBe(4);
  });

  it('ОД защиты и списание ресурса', () => {
    expect(defenseApCost('ignore')).toBe(0);
    expect(defenseApCost('dodge')).toBe(1);
    expect(defenseApCost('block')).toBe(2);
    expect(spendActionPoints({ base: 5, size: 0 }, 3)).toEqual({ base: 2, size: 0 });
    expect(spendActionPoints({ base: 1, size: 0 }, 3).base).toBe(0);
  });

  it('защита складывается в сопротивление, если тип не игнорирует защиту', () => {
    const lines = [
      line({ kind: 'defense', value: 2, durability: 1, sourceCode: 'armor' }),
      line({ kind: 'resistance', value: 1, durability: 1, sourceCode: 'mail', damageTypeCode: 'blunt' }),
    ];
    expect(stackedResistance(lines, 'blunt', 0, true)).toBe(3);
    expect(stackedResistance(lines, 'blunt', 0, false)).toBe(1);
    const withDefense = applyAttackDamage({
      weaponDamage: { base: 6, size: 0 },
      sr: 1,
      damageTypeCode: 'blunt',
      defense: {
        armor: [{ itemRuleId: 'a', itemName: 'A', href: '', lines, tiers: [] }],
        constantDefense: 2,
        tiers: [],
        shield: null,
      },
      endurance: 4,
      hooks: [],
    });
    expect(withDefense.resistance).toBe(3);
    const ignored = applyAttackDamage({
      weaponDamage: { base: 6, size: 0 },
      sr: 1,
      damageTypeCode: 'blunt',
      defense: {
        armor: [{ itemRuleId: 'a', itemName: 'A', href: '', lines, tiers: [] }],
        constantDefense: 2,
        tiers: [],
        shield: null,
      },
      endurance: 4,
      hooks: [],
      defenseIgnored: true,
    });
    expect(ignored.resistance).toBe(1);
  });

  it('сообщения атаки и подпись РУ', () => {
    const action = { ruleId: 'rule-900', code: 'simple-melee-attack', name: 'Простая атака (ближний бой)', odCost: 3 };
    expect(
      formatAttackActionMessage({
        attackerKey: 'character:1',
        attackerName: 'Гарик',
        action,
        attackerAp: 3,
        rules: [
          {
            id: 'rule-900',
            code: 'simple-melee-attack',
            type: 'ability',
            name: 'Простая атака (ближний бой)',
            description: '',
            spaceId: 1,
            keywordIds: [],
            mechanicId: null,
            createdAt: '2026-01-01T00:00:00Z',
          },
        ],
      }),
    ).toContain('совершает действие [[rule:simple-melee-attack]] за 3ОД');
    expect(
      formatStrikeNarrativeMessage({
        attackerKey: 'character:1',
        attackerName: 'Гарик',
        defenderKey: 'npc:2',
        defenderName: 'Бородач',
        weaponRuleId: 'item-scythe',
        weaponName: 'Боевая коса',
        damageTypeCode: 'slashing',
        reaction: 'dodge',
        reactionAction: { ruleId: 'rule-902', code: 'dodge', name: 'Уклонение', odCost: 1 },
        reactionAp: 1,
        rules: [
          {
            id: 'item-scythe',
            code: 'scythe',
            type: 'item',
            name: 'Боевая коса',
            description: '',
            spaceId: 1,
            keywordIds: [],
            mechanicId: null,
            createdAt: '2026-01-01T00:00:00Z',
          },
          {
            id: 'dt',
            code: 'slashing',
            type: 'damage_type',
            name: 'Рубящий',
            description: '',
            spaceId: 1,
            keywordIds: [],
            mechanicId: null,
            createdAt: '2026-01-01T00:00:00Z',
          },
          {
            id: 'rule-902',
            code: 'dodge',
            type: 'ability',
            name: 'Уклонение',
            description: '',
            spaceId: 1,
            keywordIds: [],
            mechanicId: null,
            createdAt: '2026-01-01T00:00:00Z',
          },
        ],
      }),
    ).toContain('наносит рубящий удар оружием [[rule:scythe]]');
    expect(
      formatStrikeNarrativeMessage({
        attackerKey: 'character:1',
        attackerName: 'Гарик',
        defenderKey: 'npc:2',
        defenderName: 'Бородач',
        weaponRuleId: 'item-dagger',
        weaponName: 'Кинжал',
        damageTypeCode: 'piercing',
        profileType: 'throw',
        flank: true,
        turn: true,
        reaction: 'dodge',
        reactionAction: { ruleId: 'rule-902', code: 'dodge', name: 'Уклонение', odCost: 1 },
        reactionAp: 2,
        rules: [
          {
            id: 'item-dagger',
            code: 'dagger',
            type: 'item',
            name: 'Кинжал',
            description: '',
            spaceId: 1,
            keywordIds: [],
            mechanicId: null,
            createdAt: '2026-01-01T00:00:00Z',
          },
          {
            id: 'dt-p',
            code: 'piercing',
            type: 'damage_type',
            name: 'Колющий',
            description: '',
            spaceId: 1,
            keywordIds: [],
            mechanicId: null,
            createdAt: '2026-01-01T00:00:00Z',
          },
          {
            id: 'rule-902',
            code: 'dodge',
            type: 'ability',
            name: 'Уклонение',
            description: '',
            spaceId: 1,
            keywordIds: [],
            mechanicId: null,
            createdAt: '2026-01-01T00:00:00Z',
          },
        ],
      }),
    ).toBe(
      '[[character:1,Гарик]] наносит с фланга колющий бросок оружием [[rule:dagger]] по [[npc:2,Бородач]]. Тот пытается совершить [[rule:dodge]] с Поворотом за 2ОД!',
    );
    expect(
      formatAttackResultMessage({
        attackerKey: 'character:1',
        attackerName: 'Гарик',
        defenderKey: 'npc:2',
        defenderName: 'Бородач',
        remainingSr: 2,
        exhaustion: 4,
      }),
    ).toContain('попадает по [[npc:2,Бородач]] с 2 РУ и наносит 4 истощения');
    expect(
      formatAttackResultMessage({
        attackerKey: 'character:1',
        attackerName: 'Гарик',
        defenderKey: 'npc:2',
        defenderName: 'Бородач',
        remainingSr: 2,
        exhaustion: 2,
        wound: 4,
      }),
    ).toContain('попадает по [[npc:2,Бородач]] с 2 РУ и наносит 2 истощения и 4 рану');
    expect(formatAttackSrLabel(8)).toBe('8');
    const calc = buildAttackCalcPayload({
      weaponDamage: { base: 4, size: 1 },
      damageTypeCode: 'blunt',
      rules: [
        {
          id: 'dt',
          code: 'blunt',
          type: 'damage_type',
          name: 'Дробящий',
          description: '',
          spaceId: 1,
          keywordIds: [],
          mechanicId: null,
          createdAt: '2026-01-01T00:00:00Z',
        },
      ],
      sr: 4,
      defenseIgnored: false,
      result: {
        remainingSr: 4,
        resistance: 0,
        raw: 16,
        hpDamage: 16,
        exhaustion: 4,
        stun: 4,
        wound: null,
        knockout: false,
        cuttingWound: null,
        layers: [],
      },
    });
    expect(calc.damage).toEqual({ base: 4, size: 1 });
    expect(calc.damageTypeName).toBe('дробящий');
    expect(calc.raw).toBe(16);
  });
});
