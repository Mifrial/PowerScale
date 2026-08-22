import type { GameLootStatus } from '@/modules/Roleplay/Game/Enum/GameLootStatus';
import type { GameLootRecipientType } from '@/modules/Roleplay/Game/Enum/GameLootRecipientType';

/**
 * Доля в раздаче добычи. Для предмета — один получатель (amount null); для денег —
 * несколько долей с суммами (сумма долей = moneyAmount, остаток уходит «вникуда»).
 * При раздаче деньги/предметы записываются в лист получателя (персонажа/НПС игры).
 */
export interface GameLootDistribution {
  type: GameLootRecipientType;
  /** Персонаж игры (для type 'character'). */
  characterId?: number;
  characterName?: string;
  /** НПС игры (для type 'npc'). */
  npcId?: number;
  npcName?: string;
  /** Доля денег в гм; null — предмет без суммы. */
  amount?: number | null;
}

/**
 * Запись добычи (ТР §3 `game_loot` + `game_loot_interest`). Лут — предмет
 * (`itemRuleId` из правил ревизии + `quantity`) ИЛИ деньги (`moneyAmount` гм).
 * `group` — свободный тег-группа для группировки подготовленной добычи.
 */
export interface GameLoot {
  id: number;
  gameId: number;
  group: string | null;
  itemRuleId: string | null;
  quantity: number;
  moneyAmount: number | null;
  notes: string | null;
  status: GameLootStatus;
  /** Проявившие интерес игроки (game_loot_interest). */
  interestedUserIds: number[];
  /** Итог раздачи: предмет — один получатель; деньги — доли (остаток «вникуда»). */
  distribution: GameLootDistribution[];
  createdAt: string;
  updatedAt: string;
}
