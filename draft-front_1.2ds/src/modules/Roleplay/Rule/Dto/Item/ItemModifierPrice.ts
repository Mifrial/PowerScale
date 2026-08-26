import type { ItemModifierPriceOverride } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierPriceOverride';

/**
 * Влияние модификатора на цену (в гм — мелкая единица: 1 гс = 10 гм, 1 гз = 100 гм).
 * Формула: cost = max(round(base*factor) + add_gm + round(realWeightGrams/100)*add_gm_per_100g, min_final_gm).
 * Все поля необязательны; модификатор без ценовых полей не меняет стоимость.
 */
export interface ItemModifierPrice {
  /** Множитель: 1.2 / 3 / 10 / 0.5 («/2») / 1/3 («/3»). null — цену множителем не меняет. */
  factor: number | null;
  /** Слагаемое в гм: «+25гс»=+250, «+50гз»=+5000. Отрицательное — уменьшение НА величину. */
  add_gm: number | null;
  /** Слагаемое на 100 грамм реального (модифицированного) веса: «+10гс на 100 грамм»=+100. */
  add_gm_per_100g: number | null;
  /** Нижний порог итоговой цены в гм: «10 гз»=1000, «100 гз»=10000. null — без порога. */
  min_final_gm: number | null;
  /**
   * Переопределение полей цены, если у предмета есть keyword.
   * Слияние по первому совпадению в порядке armor-item, shield-item, weapon.
   */
  by_keyword?: Record<string, ItemModifierPriceOverride> | null;
}
