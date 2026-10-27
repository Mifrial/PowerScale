import type { GamePhpStrikeConditions } from '@/modules/Roleplay/Game/Dto/GamePhpStrikeConditions';

/** Цель открытого широкого удара в проекции боя. */
export interface GamePhpOpenWideStrikeTarget {
  type: 'character' | 'npc' | string;
  id: number;
  answered: boolean;
  /** Условия, которые атакующий задал этой цели. */
  conditions?: GamePhpStrikeConditions;
  reaction?: string;
  blockItemInventoryId?: number | null;
  blockItemProfileIndex?: number | null;
  blockItemRuleCode?: string | null;
}
