/** Цель открытого широкого удара в проекции боя. */
export interface GamePhpOpenWideStrikeTarget {
  type: 'character' | 'npc' | string;
  id: number;
  answered: boolean;
  reaction?: string;
  blockItemInventoryId?: number | null;
  blockItemProfileIndex?: number | null;
  blockItemRuleCode?: string | null;
}
