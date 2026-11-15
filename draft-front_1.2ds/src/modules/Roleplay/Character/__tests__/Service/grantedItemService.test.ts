import { describe, expect, it } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import { GrantedItemService } from '@/modules/Roleplay/Character/Service/GrantedItemService';

const coreRule: Rule = {
  mechanics: [],
  id: null,
  code: 'magic-core',
  type: 'item',
  name: 'Магическое ядро',
  description: '',
  spaceId: 1,
  spec: { category: 'equipment', cost_gm: null, weight: null, innate: true, special_rule_codes: [] },
  createdAt: 1,
};

const capacityRule: Rule = {
  mechanics: [],
  id: null,
  code: 'magic-core-capacity',
  type: 'ability',
  name: 'Ядро X',
  description: '',
  spaceId: 1,
  spec: {
    type: 'trait',
    zones: { os: { kind: 'array', levels_cost: [1] } },
    requirements: [],
    grants: [
      {
        level: 1,
        grants: [{ type: 'item', item_code: 'magic-core', quantity: 1, parameters: { power: 'x' } }],
      },
    ],
    parameters: [
      {
        code: 'x',
        label: 'X',
        resolution: 'purchase',
        kind: 'dimensional',
        default: { base: 3, size: 0 },
      },
    ],
    parent_ability_code: null,
  },
  createdAt: 1,
};

const crystalRule: Rule = {
  mechanics: [],
  id: null,
  code: 'magic-crystal',
  type: 'item',
  name: 'Кристалл',
  description: '',
  spaceId: 1,
  spec: {
    category: 'equipment',
    cost_gm: null,
    weight: null,
    innate: true,
    special_rule_codes: [],
    storage: {
      power_parameter: 'max_power',
      volume_parameter: 'volume',
      recorded_parameter: 'recorded_spells',
      volume_base: 20,
      volume_increment: 10,
      min_volume_power_delta: 3,
    },
  },
  createdAt: 1,
};

const crystalCapacityRule: Rule = {
  mechanics: [],
  id: null,
  code: 'magic-crystal-capacity',
  type: 'ability',
  name: 'Кристалл X',
  description: '',
  spaceId: 1,
  spec: {
    type: 'trait',
    zones: { os: { kind: 'array', levels_cost: [1] } },
    requirements: [],
    grants: [
      {
        level: 1,
        grants: [
          {
            type: 'item',
            item_code: 'magic-crystal',
            quantity: 1,
            parameters: { max_power: 'x' },
            payload: { volume: 'volume' },
          },
        ],
      },
    ],
    parameters: [
      { code: 'x', label: 'Мощь', resolution: 'purchase', kind: 'dimensional', default: { base: 4, size: 0 } },
      { code: 'volume', label: 'Объём', resolution: 'purchase', kind: 'scalar', default: 20 },
    ],
    parent_ability_code: null,
  },
  createdAt: 1,
};

const yappiRule: Rule = {
  mechanics: [],
  id: null,
  code: 'yappi',
  type: 'race',
  name: 'Яппи',
  description: '',
  spaceId: 1,
  spec: {
    parent_race_code: null,
    cost_os: 0,
    characteristics: [],
    abilities: [
      {
        ability_code: 'magic-crystal-capacity',
        automatic: true,
        parameters: { x: { base: 4, size: -1 } },
      },
      {
        ability_code: 'magic-crystal-capacity',
        automatic: false,
        parameters: { x: { base: 5, size: 0 } },
      },
    ],
  },
  createdAt: 1,
};

function build(abilities: CharacterAbility[], inventory: InventoryItem[] = [], raceRuleCode: string | null = null) {
  return { abilities, inventory, raceRuleCode } as unknown as {
    abilities: CharacterAbility[];
    inventory: InventoryItem[];
    raceRuleCode: string | null;
  };
}

describe('GrantedItemService', () => {
  it('создаёт ядро с мощью из параметра способности', () => {
    const result = new GrantedItemService().apply(
      build([{ ruleCode: 'magic-core-capacity', level: 1, parameters: { x: { base: 4, size: 0 } } }]),
      [capacityRule, coreRule],
    );

    expect(result.inventory).toEqual([
      {
        id: 1,
        ruleCode: 'magic-core',
        quantity: 1,
        equipped: true,
        parameters: { power: { base: 4, size: 0 } },
        grantedBy: { ruleCode: 'magic-core-capacity' },
      },
    ]);
  });

  it('обновляет мощь при смене параметра', () => {
    const service = new GrantedItemService();
    const first = service.apply(
      build([{ ruleCode: 'magic-core-capacity', level: 1, parameters: { x: { base: 4, size: 0 } } }]),
      [capacityRule, coreRule],
    );
    const second = service.apply(
      build([{ ruleCode: 'magic-core-capacity', level: 1, parameters: { x: { base: 3, size: -1 } } }], first.inventory),
      [capacityRule, coreRule],
    );

    expect(second.inventory[0].parameters).toEqual({ power: { base: 3, size: -1 } });
    expect(second.inventory[0].id).toBe(first.inventory[0].id);
  });

  it('подхватывает уже добавленное ядро без ссылки на донора', () => {
    const result = new GrantedItemService().apply(
      build(
        [{ ruleCode: 'magic-core-capacity', level: 1, parameters: { x: { base: 4, size: 0 } } }],
        [
          {
            id: 5,
            ruleCode: 'magic-core',
            quantity: 1,
            equipped: false,
          },
        ],
      ),
      [capacityRule, coreRule],
    );

    expect(result.inventory).toHaveLength(1);
    expect(result.inventory[0].id).toBe(5);
    expect(result.inventory[0].equipped).toBe(true);
    expect(result.inventory[0].parameters).toEqual({ power: { base: 4, size: 0 } });
    expect(result.inventory[0].grantedBy).toEqual({ ruleCode: 'magic-core-capacity' });
  });

  it('пишет Объём кристалла в payload и сохраняет записи', () => {
    const service = new GrantedItemService();
    const rules = [crystalCapacityRule, crystalRule];
    const first = service.apply(
      build([
        {
          ruleCode: 'magic-crystal-capacity',
          level: 1,
          parameters: { x: { base: 4, size: 0 }, volume: { base: 30, size: 0 } },
        },
      ]),
      rules,
    );

    expect(first.inventory[0].parameters).toEqual({ max_power: { base: 4, size: 0 } });
    expect(first.inventory[0].payload).toEqual({ volume: 30 });

    const second = service.apply(
      build(
        [
          {
            ruleCode: 'magic-crystal-capacity',
            level: 1,
            parameters: { x: { base: 4, size: 0 }, volume: { base: 40, size: 0 } },
          },
        ],
        first.inventory.map((item) => ({ ...item, payload: { volume: 30, recorded_spells: [{ ruleCode: 'spark' }] } })),
      ),
      rules,
    );

    expect(second.inventory[0].payload).toEqual({
      volume: 40,
      recorded_spells: [{ ruleCode: 'spark' }],
    });
  });

  it('материализует врождённый кристалл automatic-ссылки расы с мощью 4↓', () => {
    const result = new GrantedItemService().apply(build([], [], 'yappi'), [
      crystalCapacityRule,
      crystalRule,
      yappiRule,
    ]);

    expect(result.inventory).toEqual([
      {
        id: 1,
        ruleCode: 'magic-crystal',
        quantity: 1,
        equipped: true,
        parameters: { max_power: { base: 4, size: -1 } },
        grantedBy: { ruleCode: 'magic-crystal-capacity' },
        payload: { volume: 20 },
      },
    ]);
  });

  it('покупка только Объёма сохраняет automatic-мощь расы', () => {
    const result = new GrantedItemService().apply(
      build(
        [
          {
            ruleCode: 'magic-crystal-capacity',
            level: 1,
            parameters: { volume: { base: 30, size: 0 } },
          },
        ],
        [],
        'yappi',
      ),
      [crystalCapacityRule, crystalRule, yappiRule],
    );

    expect(result.inventory[0].parameters).toEqual({ max_power: { base: 4, size: -1 } });
    expect(result.inventory[0].payload).toEqual({ volume: 30 });
  });

  it('без ссылки расы automatic-кристалл не появляется', () => {
    const result = new GrantedItemService().apply(build([], []), [crystalCapacityRule, crystalRule, yappiRule]);

    expect(result.inventory).toEqual([]);
  });

  it('удаляет ядро вместе со способностью', () => {
    const service = new GrantedItemService();
    const first = service.apply(
      build([{ ruleCode: 'magic-core-capacity', level: 1, parameters: { x: { base: 4, size: 0 } } }]),
      [capacityRule, coreRule],
    );
    const empty = service.apply(build([], first.inventory), [capacityRule, coreRule]);

    expect(empty.inventory).toEqual([]);
  });
});
