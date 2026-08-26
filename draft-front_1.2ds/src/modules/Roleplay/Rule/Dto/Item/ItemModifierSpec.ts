import type { ItemModifierApplies } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierApplies';
import type { ItemModifierEffect } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierEffect';
import type { ItemModifierPrice } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierPrice';

/**
 * Модификатор предмета — правило типа `item_modifier` (R29).
 */
export interface ItemModifierSpec {
  /** Код правила типа `item_modifier_type`. */
  type_code: string;
  applies: ItemModifierApplies;
  price: ItemModifierPrice;
  effects: ItemModifierEffect[];
  /**
   * Множитель цены других модификаторов (весь стек, не только «после»).
   * `increasing_only` — только если шаг увеличивает стоимость.
   */
  price_scale?: { type_code: string; factor: number; increasing_only?: boolean } | null;
}
