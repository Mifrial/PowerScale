import { describe, it, expect } from 'vitest';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { InventoryBaseline } from '@/modules/Roleplay/Character/Dto/Editor/CharacterDraftEntry';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import { CharacterBuildService } from '@/modules/Roleplay/Character/Service/CharacterBuildService';

const service = new CharacterBuildService();

function itemRule(id: string, name: string, cost_gm: number | null, extra?: Partial<ItemSpec>): Rule {
  return {
    id,
    code: id,
    type: 'item',
    name,
    description: '',
    spaceId: 1,
    keywordIds: [],
    mechanicId: null,
    createdAt: '2026-01-01T00:00:00Z',
    spec: {
      category: 'equipment',
      cost_gm,
      weight: null,
      special_rule_codes: [],
      ...extra,
    },
  };
}

const dagger = itemRule('rule-404', 'Кинжал', 30);
const sword = itemRule('rule-407', 'Фехтовальный меч', 150);
const crystal = itemRule('rule-500', 'Кристалл', null);
const innate = itemRule('rule-501', 'Врождённое', 999, { innate: true });

const rules: Rule[] = [dagger, sword, crystal, innate];

function build(money: number): CharacterBuild {
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
    money,
    ageYears: null,
    olTotal: 0,
  };
}

const baselineOf = (inventory: CharacterBuild['inventory'], money: number): InventoryBaseline => ({
  inventory: inventory.map((item) => ({ ...item })),
  money,
});

describe('CharacterBuildService · инвентарь', () => {
  describe('buyItem (R1/R5/R6)', () => {
    it('создаёт строку инвентаря и списывает деньги', () => {
      const next = service.buyItem(build(100), dagger.id, 1, rules);

      expect(next.money).toBe(70);
      expect(next.inventory).toEqual([{ id: 1, ruleId: dagger.id, quantity: 1, equipped: false, modifierRuleIds: [] }]);
    });

    it('учитывает количество: цена × qty, quantity суммируется', () => {
      const current = build(100);
      const once = service.buyItem(current, dagger.id, 2, rules);

      expect(once.money).toBe(40);
      expect(once.inventory[0]?.quantity).toBe(2);

      const twice = service.buyItem(once, dagger.id, 1, rules);
      expect(twice.money).toBe(10);
      expect(twice.inventory).toHaveLength(1);
      expect(twice.inventory[0]?.quantity).toBe(3);
      expect(twice.inventory[0]?.id).toBe(1);
    });

    it('не трогает другие строки инвентаря', () => {
      const current = { ...build(1000), inventory: [{ id: 5, ruleId: sword.id, quantity: 1, equipped: true }] };
      const next = service.buyItem(current, dagger.id, 1, rules);

      expect(next.inventory).toEqual([
        { id: 5, ruleId: sword.id, quantity: 1, equipped: true },
        { id: 6, ruleId: dagger.id, quantity: 1, equipped: false, modifierRuleIds: [] },
      ]);
      expect(next.money).toBe(970);
    });

    it('покупка сверх лимита разрешена: остаток уходит в минус (R6)', () => {
      const next = service.buyItem(build(20), sword.id, 1, rules);

      expect(next.money).toBe(-130);
      expect(next.inventory[0]?.quantity).toBe(1);
    });

    it('no-op при нулевом количестве', () => {
      const current = build(100);
      expect(service.buyItem(current, dagger.id, 0, rules)).toBe(current);
    });

    it('no-op для предмета без цены (R5)', () => {
      const current = build(100);
      expect(service.buyItem(current, crystal.id, 1, rules)).toBe(current);
    });

    it('no-op для innate предмета (R5)', () => {
      const current = build(100);
      expect(service.buyItem(current, innate.id, 1, rules)).toBe(current);
    });
  });

  describe('cancelItemPurchase (R2)', () => {
    it('отменяет только сверх базовой линии: деньги возвращаются', () => {
      const current = { ...build(40), inventory: [{ id: 1, ruleId: dagger.id, quantity: 2, equipped: false }] };
      const baseline = baselineOf([], 100);

      const next = service.cancelItemPurchase(current, baseline, dagger.id, 1, rules);

      expect(next.money).toBe(70);
      expect(next.inventory).toEqual([{ id: 1, ruleId: dagger.id, quantity: 1, equipped: false }]);
    });

    it('не опускает количество ниже базовой линии', () => {
      const current = { ...build(70), inventory: [{ id: 1, ruleId: dagger.id, quantity: 2, equipped: false }] };
      const baseline = baselineOf([{ id: 1, ruleId: dagger.id, quantity: 1, equipped: false }], 100);

      const next = service.cancelItemPurchase(current, baseline, dagger.id, 5, rules);

      expect(next.money).toBe(100);
      expect(next.inventory[0]?.quantity).toBe(1);
    });

    it('no-op на базовой линии (изначальные предметы не продаются)', () => {
      const current = { ...build(100), inventory: [{ id: 1, ruleId: dagger.id, quantity: 1, equipped: true }] };
      const baseline = baselineOf([{ id: 1, ruleId: dagger.id, quantity: 1, equipped: true }], 100);

      const next = service.cancelItemPurchase(current, baseline, dagger.id, 1, rules);

      expect(next).toBe(current);
    });

    it('удаляет добавленную строку при полной отмене', () => {
      const current = { ...build(70), inventory: [{ id: 1, ruleId: dagger.id, quantity: 1, equipped: false }] };
      const baseline = baselineOf([], 100);

      const next = service.cancelItemPurchase(current, baseline, dagger.id, 1, rules);

      expect(next.money).toBe(100);
      expect(next.inventory).toEqual([]);
    });

    it('no-op для неизвестного предмета и без цены', () => {
      const current = build(100);
      const baseline = baselineOf([], 100);
      const crystalOwned = {
        ...current,
        inventory: [{ id: 1, ruleId: crystal.id, quantity: 1, equipped: false }],
      };

      expect(service.cancelItemPurchase(current, baseline, dagger.id, 1, rules)).toBe(current);
      expect(service.cancelItemPurchase(crystalOwned, baseline, crystal.id, 1, rules)).toBe(crystalOwned);
    });

    it('no-op без базовой линии', () => {
      const current = { ...build(70), inventory: [{ id: 1, ruleId: dagger.id, quantity: 1, equipped: false }] };

      expect(service.cancelItemPurchase(current, null, dagger.id, 1, rules)).toBe(current);
    });
  });

  describe('toggleItemEquipped (R3)', () => {
    it('переключает флаг на строке по id, остальные не трогает', () => {
      const current = {
        ...build(100),
        inventory: [
          { id: 1, ruleId: dagger.id, quantity: 1, equipped: false },
          { id: 2, ruleId: sword.id, quantity: 1, equipped: true },
        ],
      };

      const next = service.toggleItemEquipped(current, 1);

      expect(next.inventory[0]?.equipped).toBe(true);
      expect(next.inventory[1]?.equipped).toBe(true);
      expect(next.money).toBe(100);
    });
  });

  describe('resetInventory (R2)', () => {
    it('восстанавливает инвентарь и деньги из базовой линии', () => {
      const current = {
        ...build(40),
        inventory: [
          { id: 1, ruleId: dagger.id, quantity: 2, equipped: true },
          { id: 2, ruleId: sword.id, quantity: 1, equipped: false },
        ],
      };
      const baseline = baselineOf([{ id: 1, ruleId: dagger.id, quantity: 1, equipped: true }], 100);

      const next = service.resetInventory(current, baseline);

      expect(next.money).toBe(100);
      expect(next.inventory).toEqual([{ id: 1, ruleId: dagger.id, quantity: 1, equipped: true }]);
    });

    it('no-op без базовой линии', () => {
      const current = build(100);
      expect(service.resetInventory(current, null)).toBe(current);
    });
  });

  describe('модификаторы (R29)', () => {
    const instancedDagger = itemRule('rule-inst-dagger', 'Кинжал', 30, { weapon: {} as ItemSpec['weapon'] });
    const coatingType: Rule = {
      id: 'rule-type-coating',
      code: 'coating',
      type: 'item_modifier_type',
      name: 'Покрытие',
      description: '',
      spaceId: 1,
      keywordIds: [],
      mechanicId: null,
      createdAt: '2026-01-01T00:00:00Z',
      spec: { exclusive: true },
    };
    const craftType: Rule = {
      id: 'rule-type-craft',
      code: 'craft-quality',
      type: 'item_modifier_type',
      name: 'Качество изделия',
      description: '',
      spaceId: 1,
      keywordIds: [],
      mechanicId: null,
      createdAt: '2026-01-01T00:00:00Z',
      spec: { exclusive: true },
    };
    const silvered: Rule = {
      id: 'rule-800',
      code: 'silvered',
      type: 'item_modifier',
      name: 'Посеребрённое',
      description: '',
      spaceId: 1,
      keywordIds: [],
      mechanicId: null,
      createdAt: '2026-01-01T00:00:00Z',
      spec: {
        type_code: 'coating',
        applies: { keyword_all: [], keyword_any: [], keyword_none: [] },
        price: { factor: 2, add_gm: null, add_gm_per_100g: null, min_final_gm: null },
        effects: [{ text: 'серебро' }],
      },
    };
    const poorly: Rule = {
      id: 'rule-poor',
      code: 'poorly-made',
      type: 'item_modifier',
      name: 'Плохо сделан',
      description: '',
      spaceId: 1,
      keywordIds: [],
      mechanicId: null,
      createdAt: '2026-01-01T00:00:00Z',
      spec: {
        type_code: 'craft-quality',
        applies: { keyword_all: [], keyword_any: [], keyword_none: [] },
        price: { factor: 0.5, add_gm: null, add_gm_per_100g: null, min_final_gm: null },
        effects: [{ text: 'плохо' }],
      },
    };
    const sturdy: Rule = {
      id: 'rule-sturdy',
      code: 'sturdily-made',
      type: 'item_modifier',
      name: 'Крепко сделан',
      description: '',
      spaceId: 1,
      keywordIds: [],
      mechanicId: null,
      createdAt: '2026-01-01T00:00:00Z',
      spec: {
        type_code: 'craft-quality',
        applies: { keyword_all: [], keyword_any: [], keyword_none: [] },
        price: { factor: 1.2, add_gm: null, add_gm_per_100g: null, min_final_gm: null },
        effects: [{ text: 'крепко' }],
      },
    };
    const ferrule: Rule = {
      id: 'rule-ferrule',
      code: 'steel-ferrule',
      type: 'item_modifier',
      name: 'Стальная оковка',
      description: '',
      spaceId: 1,
      keywordIds: [],
      mechanicId: null,
      createdAt: '2026-01-01T00:00:00Z',
      spec: {
        type_code: 'ferrule',
        applies: { keyword_all: ['weapon', 'staff'], keyword_any: [], keyword_none: [] },
        price: { factor: null, add_gm: 250, add_gm_per_100g: null, min_final_gm: null },
        effects: [{ text: 'оковка' }],
      },
    };
    const withMods = [...rules, instancedDagger, coatingType, craftType, silvered, poorly, sturdy, ferrule];

    it('покупка снаряжения — отдельные строки qty 1 без модификаторов', () => {
      const once = service.buyItem(build(1000), instancedDagger.id, 1, withMods);
      const both = service.buyItem(once, instancedDagger.id, 1, withMods, [], [silvered.id]);

      expect(both.inventory).toHaveLength(2);
      expect(both.inventory.every((item) => item.quantity === 1 && (item.modifierRuleIds ?? []).length === 0)).toBe(
        true,
      );
      expect(both.money).toBe(1000 - 30 - 30);
    });

    it('applyItemModifiers меняет ту же строку и не трогает второй экземпляр', () => {
      const current = service.buyItem(build(1000), instancedDagger.id, 2, withMods);
      const firstId = current.inventory[0]!.id;
      const secondId = current.inventory[1]!.id;
      const next = service.applyItemModifiers(current, firstId, [silvered.id], withMods);

      expect(next.inventory).toHaveLength(2);
      expect(next.inventory.find((item) => item.id === firstId)?.modifierRuleIds).toEqual([silvered.id]);
      expect(next.inventory.find((item) => item.id === secondId)?.modifierRuleIds).toEqual([]);
      expect(next.money).toBe(1000 - 60 - 30);
    });

    it('exclusive-тип вытесняет предыдущий модификатор того же типа', () => {
      const current = service.buyItem(build(1000), instancedDagger.id, 1, withMods);
      const itemId = current.inventory[0]!.id;
      const poor = service.applyItemModifiers(current, itemId, [poorly.id], withMods);
      const replaced = service.applyItemModifiers(poor, itemId, [poorly.id, sturdy.id], withMods);

      expect(replaced.inventory[0]?.modifierRuleIds).toEqual([sturdy.id]);
      expect(replaced.money).toBe(1000 - Math.round(30 * 1.2));
    });

    it('оковка не применяется к кинжалу без признака посох', () => {
      const current = service.buyItem(build(1000), instancedDagger.id, 1, withMods);
      const next = service.applyItemModifiers(current, current.inventory[0]!.id, [ferrule.id], withMods);

      expect(next).toBe(current);
    });

    it('отмена экземпляра возвращает текущую цену с модификаторами', () => {
      let current = service.buyItem(build(1000), instancedDagger.id, 2, withMods);
      current = service.applyItemModifiers(current, current.inventory[1]!.id, [silvered.id], withMods);
      const baseline = baselineOf([], 1000);
      const next = service.cancelItemPurchase(current, baseline, instancedDagger.id, 1, withMods);

      expect(next.inventory).toHaveLength(1);
      expect(next.inventory[0]?.modifierRuleIds).toEqual([]);
      expect(next.money).toBe(1000 - 30);
    });
  });
});
