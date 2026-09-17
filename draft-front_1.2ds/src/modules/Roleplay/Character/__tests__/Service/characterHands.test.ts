import { describe, it, expect } from 'vitest';
import { CharacterHandsService } from '@/modules/Roleplay/Character/Service/CharacterHandsService';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';

const service = new CharacterHandsService();

const itemRule = (code: string, extra: Partial<ItemSpec>): Rule => ({
  id: null,
  code,
  type: 'item',
  name: code,
  description: '',
  spaceId: 1,
  keywordIds: [],
  mechanicId: null,
  createdAt: 1767225600,
  spec: {
    category: 'equipment',
    cost_gm: 1,
    weight: null,
    special_rule_codes: [],
    ...extra,
  },
});

const emptyWeapon: ItemSpec['weapon'] = {
  min_strength: null,
  block_profile: null,
  weapon_profiles: [],
};

const rules: Rule[] = [
  itemRule('dagger', { weapon: emptyWeapon }),
  itemRule('staff', { occupy_hands: { min: 1, max: 2 }, weapon: emptyWeapon }),
  itemRule('bow', { occupy_hands: { min: 1, max: 2, action: 2 }, weapon: emptyWeapon }),
  itemRule('great', { occupy_hands: { min: 2, max: 2 }, weapon: emptyWeapon }),
  itemRule('plate', { armor: { defense_slots: [], resistance_slots: [], characteristic_limits: [] } }),
];

const row = (id: number, ruleCode: string, equipped: boolean, occupyHands?: number): InventoryItem => ({
  id,
  ruleCode,
  quantity: 1,
  equipped,
  occupyHands,
});

describe('CharacterHandsService', () => {
  it('оружие без спеки слотов занимает 1, доспех — 0', () => {
    expect(service.occupyHandsSpec(rules[0]?.spec as ItemSpec)).toEqual({ min: 1, max: 1 });
    expect(service.occupyHandsSpec(rules[4]?.spec as ItemSpec)).toBeNull();
    expect(service.occupyHandsLabel(rules[2]?.spec as ItemSpec)).toBe('1–2, действие 2');
  });

  it('бонус удержания: 1→0, 2 и 3→+2, 4→+3', () => {
    expect(service.gripBonus(1)).toBe(0);
    expect(service.gripBonus(2)).toBe(2);
    expect(service.gripBonus(3)).toBe(2);
    expect(service.gripBonus(4)).toBe(3);
  });

  it('лук: покой 1, действие 2', () => {
    const item = row(1, 'bow', true);
    const spec = rules[2]?.spec as ItemSpec;
    expect(service.restOccupy(item, spec)).toBe(1);
    expect(service.actionOccupy(item, spec)).toBe(2);
  });

  it('occupyHandsForAttack берёт экипированный экземпляр', () => {
    const inventory = [row(1, 'staff', true, 2)];
    expect(service.occupyHandsForAttack(inventory, 'staff', 'strike', rules)).toBe(2);
    expect(service.occupyHandsForAttack(inventory, 'staff', 'shoot', rules)).toBe(2);
  });

  it('второй двуручник не экипируется при двух руках', () => {
    const inventory = [row(1, 'great', true, 2), row(2, 'great', false)];
    expect(service.canSetEquipped(inventory, 2, true, rules)).toBe(false);
    expect(service.withSetEquipped(inventory, 2, true, rules)).toBe(inventory);
  });

  it('экип ставит occupyHands в минимум', () => {
    const inventory = [row(1, 'staff', false)];
    const next = service.withSetEquipped(inventory, 1, true, rules);
    expect(next[0]?.equipped).toBe(true);
    expect(next[0]?.occupyHands).toBe(1);
  });

  it('увеличивает занятость с минимума до двух рук', () => {
    const inventory = [row(1, 'staff', true, 1)];
    const next = service.withOccupyHands(inventory, 1, 2, rules);
    expect(next[0]?.occupyHands).toBe(2);
  });

  it('не даёт занять больше свободных слотов', () => {
    const inventory = [row(1, 'staff', true, 1), row(2, 'dagger', true, 1)];
    const next = service.withOccupyHands(inventory, 1, 2, rules);
    expect(next[0]?.occupyHands).toBe(1);
  });
});
