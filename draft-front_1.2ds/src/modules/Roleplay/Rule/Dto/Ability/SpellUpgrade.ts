import type { SpellChain } from '@/modules/Roleplay/Rule/Dto/Ability/SpellChain';
import type { SpellChargeCapUpgrade } from '@/modules/Roleplay/Rule/Dto/Ability/SpellChargeCapUpgrade';

/** Модификатор каста: дельта ОД, преимущества, пробивание сопротивления и цепочка. */
export interface SpellUpgrade {
  action_point_delta: number;
  charge_cap?: SpellChargeCapUpgrade;
  /**
   * Преимущество (отрицательное — помеха) на проверку сотворения того каста, к которому применили навык.
   * Не постоянный грант: только если модификатор выбран при сотворении.
   */
  check_advantage?: number;
  /** Позволяет применять модификатор к заклинанию любого пути при владении навыком пути. */
  any_path?: boolean;
  /** Каждый выбранный шаг повышает Требуемую мощь на 1 и снижает сопротивление на указанное число. */
  resistance_penetration_per_step?: number;
  /** Признаки, которые обязано иметь заклинание (все сразу). */
  keyword_codes?: string[];
  /** Источник преимущества: одинаковый у нескольких улучшений даёт одно преимущество, а не сумму. */
  check_advantage_source?: string;
  /**
   * Дальность вместо касания: `value` — предел дальности в ипари.
   * Требуемая мощь растёт на `power_per_step` пунктов за шаг, контроль — на `control_base`.
   */
  range?: { power_per_step: number; control_base: number };
  /** Состояние остаётся после срока заклинания (постоянно). */
  state_permanence?: boolean;
  chain?: SpellChain;
}
