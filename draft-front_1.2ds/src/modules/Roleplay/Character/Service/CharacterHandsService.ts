import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import type { ItemHandsSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemHandsSpec';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { DEFAULT_CHARACTER_HAND_COUNT } from '@/modules/Roleplay/Character/Constant/Inventory/DEFAULT_CHARACTER_HAND_COUNT';
import { GRIP_STRENGTH_BONUS } from '@/modules/Roleplay/Character/Constant/Inventory/GRIP_STRENGTH_BONUS';

/**
 * Слоты рук экипировки: занятость покоя, занятость действия, бонус удержания, лимит экипа.
 */
export class CharacterHandsService {
  constructor(
    private readonly handCount = DEFAULT_CHARACTER_HAND_COUNT,
    private readonly gripBonusTable = GRIP_STRENGTH_BONUS,
  ) {}

  characterHandCount(): number {
    return this.handCount;
  }

  occupyHandsSpec(spec: ItemSpec | undefined): ItemHandsSpec | null {
    if (!spec || spec.innate) return null;
    if (spec.occupy_hands) {
      const min = Math.max(0, Math.floor(spec.occupy_hands.min));
      const max = Math.max(min, Math.floor(spec.occupy_hands.max));
      const action =
        spec.occupy_hands.action === undefined || spec.occupy_hands.action === null
          ? undefined
          : Math.max(min, Math.floor(spec.occupy_hands.action));
      if (min === 0 && max === 0) return null;

      return action === undefined ? { min, max } : { min, max, action };
    }
    if (spec.weapon || spec.shield) return { min: 1, max: 1 };

    return null;
  }

  occupyHandsLabel(spec: ItemSpec | undefined): string | null {
    const occupy = this.occupyHandsSpec(spec);
    if (!occupy) return null;
    const range = occupy.min === occupy.max ? String(occupy.min) : `${occupy.min}–${occupy.max}`;
    if (occupy.action !== undefined && occupy.action !== occupy.min) {
      return `${range}, действие ${occupy.action}`;
    }

    return range;
  }

  restOccupy(item: InventoryItem, spec: ItemSpec | undefined): number {
    const occupy = this.occupyHandsSpec(spec);
    if (!occupy) return 0;

    return this.clampOccupy(item.occupyHands ?? occupy.min, occupy);
  }

  actionOccupy(item: InventoryItem, spec: ItemSpec | undefined): number {
    const occupy = this.occupyHandsSpec(spec);
    if (!occupy) return 0;
    const rest = this.restOccupy(item, spec);

    return Math.max(rest, occupy.action ?? rest);
  }

  gripBonus(hands: number): number {
    if (hands >= 4) return this.gripBonusTable.fourHands;
    if (hands >= 2) return this.gripBonusTable.twoHands;

    return 0;
  }

  restOccupiedTotal(inventory: readonly InventoryItem[], rules: Rule[], exceptItemId?: number): number {
    let total = 0;
    for (const item of inventory) {
      if (!item.equipped || Number(item.id) === Number(exceptItemId)) continue;
      total += this.restOccupy(item, this.specOf(item, rules));
    }

    return total;
  }

  remainingHands(inventory: readonly InventoryItem[], rules: Rule[], exceptItemId?: number): number {
    return Math.max(0, this.handCount - this.restOccupiedTotal(inventory, rules, exceptItemId));
  }

  canSetEquipped(inventory: readonly InventoryItem[], itemId: number, equipped: boolean, rules: Rule[]): boolean {
    const target = inventory.find((item) => item.id === itemId);
    if (!target) return false;
    const spec = this.specOf(target, rules);
    if (spec?.innate) return false;
    if (!equipped) return true;
    const occupy = this.occupyHandsSpec(spec);
    const need = occupy ? this.clampOccupy(target.occupyHands ?? occupy.min, occupy) : 0;

    return need <= this.remainingHands(inventory, rules, itemId);
  }

  withSetEquipped(
    inventory: readonly InventoryItem[],
    itemId: number,
    equipped: boolean,
    rules: Rule[],
  ): InventoryItem[] {
    if (!this.canSetEquipped(inventory, itemId, equipped, rules)) return inventory as InventoryItem[];
    const target = inventory.find((item) => item.id === itemId);
    if (!target || target.equipped === equipped) return inventory as InventoryItem[];
    const spec = this.specOf(target, rules);
    const occupy = this.occupyHandsSpec(spec);

    return inventory.map((item) => {
      if (item.id !== itemId) return item;
      if (!equipped) return { ...item, equipped: false };
      const occupyHands = occupy ? this.clampOccupy(item.occupyHands ?? occupy.min, occupy) : item.occupyHands;

      return occupyHands === undefined ? { ...item, equipped: true } : { ...item, equipped: true, occupyHands };
    });
  }

  withOccupyHands(
    inventory: readonly InventoryItem[],
    itemId: number,
    nextHands: number,
    rules: Rule[],
  ): InventoryItem[] {
    const target = inventory.find((item) => item.id === itemId);
    if (!target) return inventory as InventoryItem[];
    const spec = this.specOf(target, rules);
    const occupy = this.occupyHandsSpec(spec);
    if (!occupy || occupy.max <= occupy.min) return inventory as InventoryItem[];
    const remaining = this.remainingHands(inventory, rules, itemId);
    const wanted = this.clampOccupy(nextHands, occupy);
    const occupyHands = target.equipped ? this.clampOccupy(Math.min(wanted, remaining), occupy) : wanted;
    if (occupyHands === this.restOccupy(target, spec)) return inventory as InventoryItem[];

    return inventory.map((item) => (item.id === itemId ? { ...item, occupyHands } : item));
  }

  occupyHandsForAttack(
    inventory: readonly InventoryItem[],
    itemRuleCode: string,
    profileType: 'strike' | 'throw' | 'shoot',
    rules: Rule[],
  ): number {
    const item = inventory.find((entry) => entry.equipped && entry.ruleCode === itemRuleCode);
    if (!item) return 0;
    const spec = this.specOf(item, rules);

    return profileType === 'shoot' ? this.actionOccupy(item, spec) : this.restOccupy(item, spec);
  }

  private clampOccupy(value: number, occupy: ItemHandsSpec): number {
    return Math.min(occupy.max, Math.max(occupy.min, Math.floor(value)));
  }

  private specOf(item: InventoryItem, rules: Rule[]): ItemSpec | undefined {
    if (!item.ruleCode) return undefined;
    const rule = rules.find((entry) => entry.code === item.ruleCode);

    return rule?.type === 'item' ? (rule.spec as ItemSpec | undefined) : undefined;
  }
}
