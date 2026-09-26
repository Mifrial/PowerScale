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
  chain?: SpellChain;
}
