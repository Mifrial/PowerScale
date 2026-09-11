/** Скидка стоимости изучения заклинаний в рамках пути. */
export interface MagicPathStudyCost {
  /** Доля, которую вычитают из базовой цены. 0.5 → cost - floor(cost * 0.5): 3→2. */
  discount_fraction: number;
  /** Базовая цена, для которой две покупки стоят как одна скидочная (1 ОР → два за одно очко). */
  pair_base_cost: number | null;
}
