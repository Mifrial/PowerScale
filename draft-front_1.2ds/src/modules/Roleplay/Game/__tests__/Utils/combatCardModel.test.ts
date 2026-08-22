import { describe, expect, it } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import {
  combatCardCanEdit,
  combatCardModel,
  combatEntityName,
  combatExhaustion,
  combatStateRows,
  defaultStateEntry,
  parseCombatEntityKey,
  quickRollRecords,
  resolveQuickRollRecords,
  statePickerOptions,
} from '@/modules/Roleplay/Game/Utils/combatCardModel';
import type { StateAggregation, StateValueType } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import { versions } from '@/modules/Roleplay/Character/Mock/mockCharacters';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';

function membership(partial: Partial<GameCharacterMembership>): GameCharacterMembership {
  return {
    gameId: 2,
    characterId: 1,
    characterName: 'Торвин',
    characterOwnerId: 1,
    characterOwnerName: 'Иван',
    characterStatus: 'ready',
    role: 'player',
    membershipStatus: 'approved',
    activeVersion: versions[1],
    latestVersion: versions[1],
    pendingVersion: null,
    overlay: null,
    visibility: [],
    osBonus: 0,
    orBonus: 0,
    olBonus: 0,
    updatedAt: '2026-08-19T10:00:00Z',
    ...partial,
  };
}

const memberships: GameCharacterMembership[] = [
  membership({ gameId: 2, characterId: 1, characterName: 'Торвин', characterOwnerId: 1 }),
  membership({
    gameId: 1,
    characterId: 3,
    characterName: 'Гаррик',
    characterOwnerId: 2,
    membershipStatus: 'pending',
    activeVersion: versions[3],
  }),
];

const npcs: GameNpc[] = [
  {
    id: 5,
    gameId: 2,
    name: 'Гоблин-страж',
    shortDescription: null,
    fullDescription: null,
    tags: [],
    version: versions[5],
    status: 'active',
    proposedBy: null,
    visibility: [],
    updatedAt: '2026-08-19T10:00:00Z',
  },
];

function stateRule(
  id: string,
  code: string,
  name: string,
  valueType: StateValueType,
  aggregation: StateAggregation,
): Rule {
  return {
    id,
    code,
    type: 'state',
    name,
    description: '',
    spaceId: 1,
    keywordIds: [],
    mechanicId: null,
    mechanic_payload: null,
    spec: { icon_code: 'mdi-star', value_type: valueType, aggregation },
    createdAt: '2026-08-06T10:00:00Z',
  };
}

const STATE_RULES: Rule[] = [
  stateRule('rule-56', 'exhaustion', 'Истощение', 'number', 'sum'),
  stateRule('rule-60', 'wound', 'Рана', 'number', 'independent'),
  stateRule('rule-61', 'burning', 'Горение', 'dimensional', 'sum'),
  stateRule('rule-63', 'stunned', 'Оглушение', 'number', 'sum'),
  stateRule('rule-65', 'poisoning', 'Отравление', 'flag', 'independent'),
];

describe('combatCardModel: резолюция entity', () => {
  it('parseCombatEntityKey разбирает префиксы character/npc', () => {
    expect(parseCombatEntityKey('character:3')).toEqual({ kind: 'character', id: 3 });
    expect(parseCombatEntityKey('npc:5')).toEqual({ kind: 'npc', id: 5 });
  });

  it('combatEntityName находит имя по членству/НПС', () => {
    expect(combatEntityName('character:3', memberships, npcs)).toBe('Гаррик');
    expect(combatEntityName('npc:5', memberships, npcs)).toBe('Гоблин-страж');
    expect(combatEntityName('npc:99', memberships, npcs)).toBe('');
  });
});

describe('combatCardModel: права (CD-6)', () => {
  it('ГМ правит любого (персонажи и НПС)', () => {
    expect(combatCardCanEdit('character:3', true, 1, memberships)).toBe(true);
    expect(combatCardCanEdit('npc:5', true, 1, memberships)).toBe(true);
  });

  it('игрок правит только своего approved-персонажа', () => {
    expect(combatCardCanEdit('character:1', false, 1, memberships)).toBe(true);
    expect(combatCardCanEdit('character:3', false, 1, memberships)).toBe(false);
    expect(combatCardCanEdit('character:3', false, 2, memberships)).toBe(false);
  });

  it('НПС игрок не правит, без пользователя — не правит никто', () => {
    expect(combatCardCanEdit('npc:5', false, 1, memberships)).toBe(false);
    expect(combatCardCanEdit('character:1', false, null, memberships)).toBe(false);
  });
});

describe('combatCardModel: версия + оверлей', () => {
  it('собирает effectiveVersion = версия + оверлей (при изменениях)', () => {
    const model = combatCardModel('character:1', memberships, npcs, true, 1, {
      gameId: 2,
      entityKey: 'character:1',
      kind: 'character',
      resources: [{ ruleId: 'rule-18', current: { base: 1, size: 0 } }],
      states: [{ stateRuleId: 'rule-56', value: 5 }],
      updatedAt: '2026-08-19T12:00:00',
    });
    expect(model.kind).toBe('character');
    expect(model.entityId).toBe(1);
    expect(model.name).toBe('Торвин');
    expect(model.canEdit).toBe(true);
    expect(model.effectiveVersion?.states).toEqual([{ stateRuleId: 'rule-56', value: 5 }]);
  });

  it('пустая запись оверлея (updatedAt === "") — версия как есть', () => {
    const model = combatCardModel('character:1', memberships, npcs, true, 1, {
      gameId: 2,
      entityKey: 'character:1',
      kind: 'character',
      resources: [],
      states: [],
      updatedAt: '',
    });
    expect(model.effectiveVersion?.states).toEqual(versions[1].states);
  });

  it('без листа (activeVersion null) — effectiveVersion null', () => {
    const noVersion = [membership({ gameId: 1, characterId: 4, characterName: 'Морган', activeVersion: null })];
    const model = combatCardModel('character:4', noVersion, npcs, true, 1, null);
    expect(model.version).toBeNull();
    expect(model.effectiveVersion).toBeNull();
  });
});

describe('resolveQuickRollRecords (CD-8)', () => {
  const records = [
    { ruleId: 'rule-1', name: 'Сила', value: { base: 4, size: 0 }, valueLabel: '4' },
    { ruleId: 'rule-2', name: 'Ловкость', value: { base: 2, size: 0 }, valueLabel: '2' },
  ];

  it('сохраняет порядок записей и резолвит по ruleId', () => {
    expect(resolveQuickRollRecords(['rule-2', 'rule-1'], records).map((item) => item.ruleId)).toEqual([
      'rule-2',
      'rule-1',
    ]);
  });

  it('отбрасывает неизвестные ruleId (не характеристика ревизии)', () => {
    expect(resolveQuickRollRecords(['rule-2', 'rule-99', 'rule-1'], records).map((item) => item.ruleId)).toEqual([
      'rule-2',
      'rule-1',
    ]);
  });

  it('пустой список — пустой результат', () => {
    expect(resolveQuickRollRecords([], records)).toEqual([]);
  });
});

describe('quickRollRecords (CD-8): кандидаты включают боевые характеристики', () => {
  const characteristic = (
    ruleId: string,
    name: string,
    shortName: string | null,
    value: number,
  ): CharacterOverview => ({
    characteristics: [
      {
        ruleId,
        name,
        shortName,
        base: { base: value, size: 0 },
        baseLabel: String(value),
        value: { base: value, size: 0 },
        valueLabel: String(value),
        delta: 0,
        href: null,
        isResolved: true,
        group: 'primary',
        subtitle: null,
        modifiers: [],
        conditionalModifiers: [],
        derived: null,
      },
    ],
    combat: null,
    resources: [],
    abilities: [],
    misc: [],
    inventory: [],
    defense: null,
    attacks: [],
    states: [],
  });

  it('собирает характеристики, статы секций (имя броска «Ближний/Дальний бой») и оружия (с префиксом ББ:/ДБ:)', () => {
    const overview: CharacterOverview = {
      ...characteristic('rule-1', 'Сила', 'Сил', 4),
      combat: {
        melee: {
          stat: {
            ruleId: 'rule-melee',
            name: 'Ближний бой',
            shortName: null,
            base: { base: 3, size: 0 },
            baseLabel: '3',
            value: { base: 5, size: 0 },
            valueLabel: '5',
            delta: 2,
            href: null,
            isResolved: true,
            group: 'combat',
            subtitle: null,
            modifiers: [],
            conditionalModifiers: [],
            derived: null,
          },
          weapons: [
            {
              ruleId: 'combat:melee:Меч',
              name: 'Меч',
              shortName: 'Меч',
              base: { base: 2, size: 0 },
              baseLabel: '2',
              value: { base: 2, size: 0 },
              valueLabel: '2',
              delta: 0,
              href: null,
              isResolved: true,
              group: 'combat',
              subtitle: null,
              modifiers: [],
              conditionalModifiers: [],
              derived: null,
            },
          ],
        },
        ranged: {
          stat: {
            ruleId: 'rule-ranged',
            name: 'Дальний бой',
            shortName: null,
            base: { base: 2, size: 0 },
            baseLabel: '2',
            value: { base: 3, size: 0 },
            valueLabel: '3',
            delta: 1,
            href: null,
            isResolved: true,
            group: 'combat',
            subtitle: null,
            modifiers: [],
            conditionalModifiers: [],
            derived: null,
          },
          weapons: [
            {
              ruleId: 'combat:ranged:Кинжал',
              name: 'Кинжал',
              shortName: 'Кинжал',
              base: { base: 1, size: 0 },
              baseLabel: '1',
              value: { base: 1, size: 0 },
              valueLabel: '1',
              delta: 0,
              href: null,
              isResolved: true,
              group: 'combat',
              subtitle: null,
              modifiers: [],
              conditionalModifiers: [],
              derived: null,
            },
          ],
        },
      },
    };

    const records = quickRollRecords(overview);
    expect(records.map((record) => record.ruleId)).toEqual([
      'rule-1',
      'rule-melee',
      'combat:melee:Меч',
      'rule-ranged',
      'combat:ranged:Кинжал',
    ]);
    expect(records[1].name).toBe('Ближний бой');
    expect(records[1].valueLabel).toBe('5');
    expect(records[2].name).toBe('ББ: Меч');
    expect(records[4].name).toBe('ДБ: Кинжал');
  });
});

describe('combatStateRows', () => {
  it('группирует записи по правилу с индексами и сводками', () => {
    const rows = combatStateRows(versions[1].states, STATE_RULES);
    const exhaustion = rows.find((row) => row.code === 'exhaustion');
    const wound = rows.find((row) => row.code === 'wound');
    const burning = rows.find((row) => row.code === 'burning');
    const poisoning = rows.find((row) => row.code === 'poisoning');

    expect(exhaustion?.summary).toBe('2');
    expect(exhaustion?.indices).toEqual([0]);
    expect(wound?.summary).toBe('4, 1');
    expect(wound?.indices).toEqual([1, 2]);
    expect(burning?.summary).toBe('3↑');
    expect(poisoning?.summary).toBe('Отравление, Отравление');
    expect(poisoning?.poison).toBe(true);
  });

  it('неизвестное правило — фолбэк-строка', () => {
    const rows = combatStateRows([{ stateRuleId: 'rule-unknown', value: 2 }], STATE_RULES);
    expect(rows[0].name).toBe('rule-unknown');
    expect(rows[0].valueType).toBe('flag');
    expect(rows[0].summary).toBeNull();
  });
});

describe('combatExhaustion', () => {
  it('суммирует записи истощения по коду правила', () => {
    expect(
      combatExhaustion(
        [
          { stateRuleId: 'rule-56', value: 2 },
          { stateRuleId: 'rule-56', value: 1 },
          { stateRuleId: 'rule-60', value: 4 },
        ],
        STATE_RULES,
      ),
    ).toBe(3);
  });

  it('null при отсутствии правила истощения в ревизии', () => {
    const rules = [stateRule('rule-60', 'wound', 'Рана', 'number', 'independent')];
    expect(combatExhaustion([{ stateRuleId: 'rule-56', value: 2 }], rules)).toBeNull();
  });

  it('null при отсутствии записей истощения', () => {
    expect(combatExhaustion([{ stateRuleId: 'rule-60', value: 4 }], STATE_RULES)).toBeNull();
  });

  it('null при нулевом/отрицательном итоге (нет истощения)', () => {
    expect(combatExhaustion([{ stateRuleId: 'rule-56', value: 0 }], STATE_RULES)).toBeNull();
    expect(combatExhaustion([{ stateRuleId: 'rule-56', value: -1 }], STATE_RULES)).toBeNull();
  });
});

describe('combatStatePicker', () => {
  it('statePickerOptions — все state-правила ревизии', () => {
    const options = statePickerOptions(STATE_RULES);
    expect(options.map((option) => option.code)).toEqual(['exhaustion', 'wound', 'burning', 'stunned', 'poisoning']);
  });

  it('defaultStateEntry — значение по умолчанию по типу', () => {
    expect(
      defaultStateEntry({
        ruleId: 'r',
        code: 'wound',
        name: 'Рана',
        iconCode: null,
        valueType: 'number',
        aggregation: 'independent',
      }),
    ).toEqual({ value: 1 });
    expect(
      defaultStateEntry({
        ruleId: 'r',
        code: 'burning',
        name: 'Горение',
        iconCode: null,
        valueType: 'dimensional',
        aggregation: 'sum',
      }),
    ).toEqual({ dimensionalValue: { base: 1, size: 0 } });
    expect(
      defaultStateEntry({
        ruleId: 'r',
        code: 'weakness',
        name: 'Слабость',
        iconCode: null,
        valueType: 'flag',
        aggregation: 'max',
      }),
    ).toEqual({});
  });
});
