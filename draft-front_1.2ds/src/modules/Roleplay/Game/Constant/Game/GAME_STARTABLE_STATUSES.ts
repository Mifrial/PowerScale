import type { GameStatus } from '@/modules/Roleplay/Game/Enum/GameStatus';

/**
 * Статусы, из которых можно запустить текущую сессию. Старт статус не меняет.
 * `completed` — терминальный статус кампании, строка только для чтения.
 */
export const GAME_STARTABLE_STATUSES: readonly GameStatus[] = ['draft', 'recruiting', 'in_process', 'paused'];
