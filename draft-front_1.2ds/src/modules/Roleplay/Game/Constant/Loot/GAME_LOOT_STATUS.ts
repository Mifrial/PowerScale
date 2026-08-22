import type { GameLootStatus } from '@/modules/Roleplay/Game/Enum/GameLootStatus';

/** Подписи статусов добычи (ТР §3 `game_loot.status`). */
export const GAME_LOOT_STATUS_LABEL: Record<GameLootStatus, string> = {
  prepared: 'В запасе',
  available: 'На разборе',
  distributed: 'Роздано',
};

/** Цвета статусов добычи для Vuetify. */
export const GAME_LOOT_STATUS_COLOR: Record<GameLootStatus, string> = {
  prepared: 'grey',
  available: 'info',
  distributed: 'success',
};
