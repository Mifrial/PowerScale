import type { SpellUpgrade } from '@/modules/Roleplay/Rule/Dto/Ability/SpellUpgrade';

/** Применимый к этому касту `spell_upgrade`. */
export interface SpellCastUpgradeOption {
  ruleCode: string;
  name: string;
  upgrade: SpellUpgrade;
}
