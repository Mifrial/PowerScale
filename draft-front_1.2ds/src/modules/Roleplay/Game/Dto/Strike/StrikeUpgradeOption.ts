import type { StrikeUpgradeMode } from '@/modules/Roleplay/Rule/Dto/Ability/StrikeUpgradeMode';

/** Режим `strike_upgrade`, который можно отметить на этом ударе. */
export interface StrikeUpgradeOption {
  optionId: string;
  ruleCode: string;
  name: string;
  exclusiveGroup: string;
  mode: StrikeUpgradeMode;
}
