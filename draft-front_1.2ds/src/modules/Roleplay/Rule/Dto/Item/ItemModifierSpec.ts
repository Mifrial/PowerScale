/**
 * Модификатор предмета — правило типа `item_modifier` (R29).
 *
 * Применимость определяется только по признакам предмета (`rule.keywordIds` → коды keywords).
 * Подтип предмета (оружие/щит/доспех) — тоже признак: `weapon`, `shield-item`, `armor-item`.
 * Пустые `keyword_all` + `keyword_any` означают «применимо всему».
 */
export interface ItemModifierApplies {
  /** AND: предмет обязан иметь ВСЕ эти признаки. Пусто — не ограничивает. */
  keyword_all: string[];
  /** OR: предмет обязан иметь хотя бы один. Пусто — не ограничивает. */
  keyword_any: string[];
  /** AND-запрет: предмет НЕ должен иметь ни одного из них. */
  keyword_none: string[];
}

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

/** Частичный override цены для `ItemModifierPrice.by_keyword`. */
export interface ItemModifierPriceOverride {
  factor?: number | null;
  add_gm?: number | null;
  add_gm_per_100g?: number | null;
  min_final_gm?: number | null;
}

import type { ItemModifierOp } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierOp';

/** Эффект модификатора: текст для UI + структурные ops на спек предмета. */
export interface ItemModifierEffect {
  /** Метка (например «Оружие» / «Щит» / «Доспех»), когда одно правило варьирует эффекты по типам. */
  label?: string | null;
  text: string;
  ops?: ItemModifierOp[];
}

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
