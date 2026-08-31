import { describe, expect, it } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import { combatCardModelService } from '@/modules/Roleplay/Game/Service/Instance/combatCardModelService';

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
    role: 'player',
    membershipStatus: 'active',
    approvedCharacterVersion: versions[1],
    reviewState: 'clean',
    returnedAt: null,
    returnReason: null,
    returnMessageId: null,
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
    membershipStatus: 'submitted',
    approvedCharacterVersion: versions[3],
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
  id: number | null,
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
  stateRule(56, 'exhaustion', 'Истощение', 'number', 'sum'),
  stateRule(60, 'wound', 'Рана', 'number', 'independent'),
  stateRule(61, 'burning', 'Горение', 'dimensional', 'sum'),
  stateRule(63, 'stunned', 'Оглушение', 'number', 'sum'),
  stateRule(65, 'poisoning', 'Отравление', 'flag', 'independent'),
];

describe('combatCardModel: резолюция entity', () => {
  it('parseCombatEntityKey разбирает префиксы character/npc', () => {
    expect(combatCardModelService.parseCombatEntityKey('character:3')).toEqual({ kind: 'character', id: 3 });
    expect(combatCardModelService.parseCombatEntityKey('npc:5')).toEqual({ kind: 'npc', id: 5 });
  });

  it('combatEntityName находит имя по членству/НПС', () => {
    expect(combatCardModelService.combatEntityName('character:3', memberships, npcs)).toBe('Гаррик');
    expect(combatCardModelService.combatEntityName('npc:5', memberships, npcs)).toBe('Гоблин-страж');
    expect(combatCardModelService.combatEntityName('npc:99', memberships, npcs)).toBe('');
  });
});

describe('combatCardModel: права (CD-6)', () => {
  it('ГМ правит любого (персонажи и НПС)', () => {
    expect(combatCardModelService.combatCardCanEdit('character:3', true, 1, memberships)).toBe(true);
    expect(combatCardModelService.combatCardCanEdit('npc:5', true, 1, memberships)).toBe(true);
  });

  it('игрок правит только своего approved-персонажа', () => {
    expect(combatCardModelService.combatCardCanEdit('character:1', false, 1, memberships)).toBe(true);
    expect(combatCardModelService.combatCardCanEdit('character:3', false, 1, memberships)).toBe(false);
    expect(combatCardModelService.combatCardCanEdit('character:3', false, 2, memberships)).toBe(false);
  });

  it('НПС игрок не правит, без пользователя — не правит никто', () => {
    expect(combatCardModelService.combatCardCanEdit('npc:5', false, 1, memberships)).toBe(false);
    expect(combatCardModelService.combatCardCanEdit('character:1', false, null, memberships)).toBe(false);
  });
});

describe('combatActionPoints', () => {
  it('читает текущие ОД и лимит с ресурса action-points', () => {
    const rules = [{ id: 18, code: 'action-points', type: 'resource' }] as Rule[];
    expect(combatCardModelService.combatActionPoints(versions[1], rules)).toEqual({ current: 4, max: 4 });
  });
});

describe('combatCardModel: версия + оверлей', () => {
  it('собирает effectiveVersion = версия + оверлей (при изменениях)', () => {
    const model = combatCardModelService.combatCardModel('character:1', memberships, npcs, true, 1, {
      gameId: 2,
      entityKey: 'character:1',
      kind: 'character',
      resources: [{ ruleCode: 'action-points', current: { base: 1, size: 0 } }],
      states: [{ stateRuleCode: 'exhaustion', value: 5 }],
      updatedAt: '2026-08-19T12:00:00',
    });
    expect(model.kind).toBe('character');
    expect(model.entityId).toBe(1);
    expect(model.name).toBe('Торвин');
    expect(model.canEdit).toBe(true);
    expect(model.effectiveVersion?.states).toEqual([{ stateRuleCode: 'exhaustion', value: 5 }]);
  });

  it('пустая запись оверлея (updatedAt === "") — версия как есть', () => {
    const model = combatCardModelService.combatCardModel('character:1', memberships, npcs, true, 1, {
      gameId: 2,
      entityKey: 'character:1',
      kind: 'character',
      resources: [],
      states: [],
      updatedAt: '',
    });
    expect(model.effectiveVersion?.states).toEqual(versions[1].states);
  });

  it('без листа (approvedCharacterVersion null) — effectiveVersion null', () => {
    const noVersion = [
      membership({ gameId: 1, characterId: 4, characterName: 'Морган', approvedCharacterVersion: null }),
    ];
    const model = combatCardModelService.combatCardModel('character:4', noVersion, npcs, true, 1, null);
    expect(model.version).toBeNull();
    expect(model.effectiveVersion).toBeNull();
  });

  it('ресурсы оверлея видны поверх overlay.sheet (списание ОД после правки экипировки)', () => {
    const sheet = JSON.parse(JSON.stringify(versions[1])) as (typeof versions)[1];
    sheet.resources = sheet.resources.map((resource) =>
      resource.ruleCode === 'action-points' ? { ...resource, current: { base: 3, size: 0 } } : resource,
    );
    const model = combatCardModelService.combatCardModel('character:1', memberships, npcs, true, 1, {
      gameId: 2,
      entityKey: 'character:1',
      kind: 'character',
      sheet,
      resources: [{ ruleCode: 'action-points', current: { base: 1, size: 0 } }],
      states: sheet.states,
      updatedAt: '2026-08-23T12:00:00',
    });
    expect(
      model.effectiveVersion?.resources.find((resource) => resource.ruleCode === 'action-points')?.current,
    ).toEqual({
      base: 1,
      size: 0,
    });
  });
});

describe('resolveQuickRollRecords (CD-8)', () => {
  const records = [
    { ruleCode: 'rule-6-and-1', name: 'Сила', value: { base: 4, size: 0 }, valueLabel: '4' },
    { ruleCode: 'advantages', name: 'Ловкость', value: { base: 2, size: 0 }, valueLabel: '2' },
  ];

  it('сохраняет порядок записей и резолвит по ruleCode', () => {
    expect(
      combatCardModelService
        .resolveQuickRollRecords(['advantages', 'rule-6-and-1'], records)
        .map((item) => item.ruleCode),
    ).toEqual(['advantages', 'rule-6-and-1']);
  });

  it('отбрасывает неизвестные ruleCode (не характеристика ревизии)', () => {
    expect(
      combatCardModelService
        .resolveQuickRollRecords(['advantages', 'rule-99', 'rule-6-and-1'], records)
        .map((item) => item.ruleCode),
    ).toEqual(['advantages', 'rule-6-and-1']);
  });

  it('пустой список — пустой результат', () => {
    expect(combatCardModelService.resolveQuickRollRecords([], records)).toEqual([]);
  });
});

describe('quickRollRecords (CD-8): кандидаты включают боевые характеристики', () => {
  const characteristic = (
    ruleCode: string,
    name: string,
    shortName: string | null,
    value: number,
  ): CharacterOverview => ({
    characteristics: [
      {
        ruleCode,
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
            ruleCode: 'rule-melee',
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
              ruleCode: 'combat:melee:Меч',
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
            ruleCode: 'rule-ranged',
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
              ruleCode: 'combat:ranged:Кинжал',
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

    const records = combatCardModelService.quickRollRecords(overview);
    expect(records.map((record) => record.ruleCode)).toEqual([
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
    const rows = combatCardModelService.combatStateRows(versions[1].states, STATE_RULES);
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
    const rows = combatCardModelService.combatStateRows([{ stateRuleCode: 'rule-unknown', value: 2 }], STATE_RULES);
    expect(rows[0].name).toBe('rule-unknown');
    expect(rows[0].valueType).toBe('flag');
    expect(rows[0].summary).toBeNull();
  });
});

describe('combatExhaustion', () => {
  it('суммирует записи истощения по коду правила', () => {
    expect(
      combatCardModelService.combatExhaustion(
        [
          { stateRuleCode: 'exhaustion', value: 2 },
          { stateRuleCode: 'exhaustion', value: 1 },
          { stateRuleCode: 'wound', value: 4 },
        ],
        STATE_RULES,
      ),
    ).toBe(3);
  });

  it('null при отсутствии правила истощения в ревизии', () => {
    const rules = [stateRule(60, 'wound', 'Рана', 'number', 'independent')];
    expect(combatCardModelService.combatExhaustion([{ stateRuleCode: 'exhaustion', value: 2 }], rules)).toBeNull();
  });

  it('null при отсутствии записей истощения', () => {
    expect(combatCardModelService.combatExhaustion([{ stateRuleCode: 'wound', value: 4 }], STATE_RULES)).toBeNull();
  });

  it('null при нулевом/отрицательном итоге (нет истощения)', () => {
    expect(
      combatCardModelService.combatExhaustion([{ stateRuleCode: 'exhaustion', value: 0 }], STATE_RULES),
    ).toBeNull();
    expect(
      combatCardModelService.combatExhaustion([{ stateRuleCode: 'exhaustion', value: -1 }], STATE_RULES),
    ).toBeNull();
  });
});

describe('combatMaim', () => {
  const maimRules = [...STATE_RULES, stateRule(606, 'maim', 'Увечье', 'number', 'independent')];

  it('суммирует силу всех записей увечья', () => {
    expect(
      combatCardModelService.combatMaim(
        [
          { stateRuleCode: 'maim', value: 2 },
          { stateRuleCode: 'maim', value: 1 },
          { stateRuleCode: 'exhaustion', value: 4 },
        ],
        maimRules,
      ),
    ).toBe(3);
  });

  it('null при отсутствии правила увечья в ревизии', () => {
    expect(combatCardModelService.combatMaim([{ stateRuleCode: 'maim', value: 2 }], STATE_RULES)).toBeNull();
  });

  it('null при отсутствии записей или нулевом итоге', () => {
    expect(combatCardModelService.combatMaim([{ stateRuleCode: 'exhaustion', value: 4 }], maimRules)).toBeNull();
    expect(combatCardModelService.combatMaim([{ stateRuleCode: 'maim', value: 0 }], maimRules)).toBeNull();
  });
});

describe('maimTotalDurationLabel', () => {
  it('полный срок = интервал −1 × сила', () => {
    expect(
      combatCardModelService.maimTotalDurationLabel({
        stateRuleCode: 'maim',
        value: 2,
        maim: { permanent: false, healTotal: 4, healUnit: 'days' },
      }),
    ).toBe('8 дн.');
  });

  it('постоянное — пост.', () => {
    expect(
      combatCardModelService.maimTotalDurationLabel({
        stateRuleCode: 'maim',
        value: 3,
        maim: { permanent: true },
      }),
    ).toBe('пост.');
  });
});
describe('combatStatePicker', () => {
  it('statePickerOptions — все state-правила ревизии', () => {
    const options = combatCardModelService.statePickerOptions(STATE_RULES);
    expect(options.map((option) => option.code)).toEqual(['exhaustion', 'wound', 'burning', 'stunned', 'poisoning']);
  });

  it('defaultStateEntry — значение по умолчанию по типу', () => {
    expect(
      combatCardModelService.defaultStateEntry({
        ruleCode: 'r',
        code: 'wound',
        name: 'Рана',
        iconCode: null,
        valueType: 'number',
        aggregation: 'independent',
      }),
    ).toEqual({ value: 1 });
    expect(
      combatCardModelService.defaultStateEntry({
        ruleCode: 'r',
        code: 'burning',
        name: 'Горение',
        iconCode: null,
        valueType: 'dimensional',
        aggregation: 'sum',
      }),
    ).toEqual({ dimensionalValue: { base: 1, size: 0 } });
    expect(
      combatCardModelService.defaultStateEntry({
        ruleCode: 'r',
        code: 'poisoning',
        name: 'Отравление',
        iconCode: null,
        valueType: 'flag',
        aggregation: 'independent',
      }),
    ).toEqual({ poison: { poisonRuleCode: null, strength: { base: 1, size: 0 } } });
    expect(
      combatCardModelService.defaultStateEntry(
        {
          ruleCode: 'r',
          code: 'poisoning',
          name: 'Отравление',
          iconCode: null,
          valueType: 'flag',
          aggregation: 'independent',
        },
        [
          {
            id: null,
            code: 'poison-scorpion',
            type: 'poison',
            name: 'Яд скорпиона',
            description: '',
            spaceId: 1,
            spec: {
              damage_type_code: 'poison-1',
              default_strength: { base: 3, size: 1 },
              default_periodicity: { kind: 'literal', value: 2, step: 'turn' },
            },
            keywordIds: [],
            mechanicId: null,
            createdAt: '2026-01-01T00:00:00Z',
          },
        ],
      ),
    ).toMatchObject({
      poison: { poisonRuleCode: 'poison-scorpion', damage_type_code: 'poison-1', strength: { base: 3, size: 1 } },
    });
  });
});
