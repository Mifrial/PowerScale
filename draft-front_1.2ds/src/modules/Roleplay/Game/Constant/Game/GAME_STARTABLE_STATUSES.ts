import type { GameStatus } from '@/modules/Roleplay/Game/Enum/GameStatus';

/**
 * Переходы статуса игры из чата (ТР §8 «Жизненный цикл»): «Начать сессию» → playing (идёт игра),
 * «Остановить сессию» → in_process (межсессионный период; боевые изменения уходят на модерацию).
 * Сессия — временное состояние игры, не связанное с завершением всей игры: «Завершена» (completed)
 * — терминальный статус, выставляется отдельно (например, селектором статуса в форме игры).
 */
export const GAME_STARTABLE_STATUSES: readonly GameStatus[] = ['draft', 'recruiting', 'in_process', 'paused'];
