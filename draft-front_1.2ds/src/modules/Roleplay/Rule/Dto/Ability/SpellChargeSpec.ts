import type { SpellChargeSpend } from '@/modules/Roleplay/Rule/Dto/Ability/SpellChargeSpend';

/** Накопление зарядов на поддерживаемом заклинании. */
export interface SpellChargeSpec {
  state_code: string;
  grant: number;
  default_cap: number;
  spend: SpellChargeSpend;
}
