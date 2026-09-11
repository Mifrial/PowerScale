import type { SpellChain } from '@/modules/Roleplay/Rule/Dto/Ability/SpellChain';
import type { SpellChargeCapUpgrade } from '@/modules/Roleplay/Rule/Dto/Ability/SpellChargeCapUpgrade';

/** Модификатор каста: дельта ОД, опционально преимущество на проверку сотворения и цепочка. */
export interface SpellUpgrade {
  action_point_delta: number;
  charge_cap?: SpellChargeCapUpgrade;
  /**
   * Преимущество (отрицательное — помеха) на проверку сотворения того каста, к которому применили навык.
   * Не постоянный грант: только если модификатор выбран при сотворении.
   */
  check_advantage?: number;
  chain?: SpellChain;
}
