import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/** Уже заявленный прикрывающий открытого удара. */
export interface GamePhpOpenStrikeCoverer {
  actor: { type: string; id: number };
  ruleCode: string;
  reaction: string;
  blockItemInventoryId?: number | null;
  blockItemProfileIndex?: number | null;
  blockItemRuleCode?: string | null;
  key?: CombatEntityKey;
}
