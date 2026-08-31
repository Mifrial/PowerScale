import type { ActionEffect } from '@/modules/Roleplay/Rule/Dto/Ability/ActionEffect';

/** Временный эффект, который действует между действиями в рамках боевой сессии. */
export interface PendingActionEffect {
  sourceRuleCode: string;
  effect: ActionEffect;
}
