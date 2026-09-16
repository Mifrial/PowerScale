import type { StrikeUpgradeMode } from '@/modules/Roleplay/Rule/Dto/Ability/StrikeUpgradeMode';

/** Модификатор запуска удара: режимы одной группы на проверку увечья. */
export interface StrikeUpgrade {
  exclusive_group: string;
  modes: StrikeUpgradeMode[];
  requires_physiology?: true;
}
