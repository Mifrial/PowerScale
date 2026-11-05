import type { GamePhpOpenStrikeCoverer } from '@/modules/Roleplay/Game/Dto/GamePhpOpenStrikeCoverer';
import type { GamePhpStrikeAwaiting } from '@/modules/Roleplay/Game/Dto/GamePhpStrikeAwaiting';
import type { GamePhpStrikeConditions } from '@/modules/Roleplay/Game/Dto/GamePhpStrikeConditions';
import type { GamePhpOpenStrikeSupporter } from '@/modules/Roleplay/Game/Dto/GamePhpOpenStrikeSupporter';

/** Открытый удар PHP из карточки боя. */
export interface GamePhpOpenStrike {
  strikeId?: number;
  attackerKind: 'character' | 'npc' | string;
  attackerId: number;
  defenderKind: 'character' | 'npc' | string;
  defenderId: number;
  actionRuleCode: string;
  itemInventoryId: number;
  itemRuleCode: string;
  profileType: string;
  profileIndex: number;
  conditions?: GamePhpStrikeConditions;
  /** Уже объявленные реакции: один участник реагирует один раз. */
  supporters?: GamePhpOpenStrikeSupporter[];
  /** Уже заявленные прикрывающие. */
  coverers?: GamePhpOpenStrikeCoverer[];
  /** Ожидающие решения (например, выбор фактической цели). */
  awaiting?: GamePhpStrikeAwaiting[];
}
