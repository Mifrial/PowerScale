/** Открытый удар PHP из карточки боя. */
export interface GamePhpOpenStrike {
  attackerKind: 'character' | 'npc' | string;
  attackerId: number;
  defenderKind: 'character' | 'npc' | string;
  defenderId: number;
  actionRuleCode: string;
  itemInventoryId: number;
  itemRuleCode: string;
  profileType: string;
  profileIndex: number;
}
