import type { ItemModifierApplies } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierApplies';
import type { ItemModifierOp } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierOp';

/**
 * Одна операция модификатора: ветка стата, условие по признакам и явный источник.
 * Пустой `when` меняет стат всегда. Нет `source_code` — источник уникален.
 */
export type ItemModifierOperation = ItemModifierOp & {
  when?: ItemModifierApplies;
  source_code?: string | null;
};
