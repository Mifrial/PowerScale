import type { GamePhpStrikeConditions } from '@/modules/Roleplay/Game/Dto/GamePhpStrikeConditions';
import type { GamePhpOpenStrikeCoverer } from '@/modules/Roleplay/Game/Dto/GamePhpOpenStrikeCoverer';
import type { GamePhpOpenStrikeSupporter } from '@/modules/Roleplay/Game/Dto/GamePhpOpenStrikeSupporter';
import type { GamePhpStrikeAwaiting } from '@/modules/Roleplay/Game/Dto/GamePhpStrikeAwaiting';

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
  /** Объявленные поддержки строки. */
  supporters?: GamePhpOpenStrikeSupporter[];
  /** Объявленные прикрывающие строки. */
  coverers?: GamePhpOpenStrikeCoverer[];
  /** Ожидания решения по строке. */
  awaiting?: GamePhpStrikeAwaiting[];
}
