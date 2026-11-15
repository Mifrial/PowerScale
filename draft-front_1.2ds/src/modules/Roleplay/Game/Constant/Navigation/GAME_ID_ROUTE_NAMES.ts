/**
 * Маршруты, у которых `params.id` — id игры. Фолбэк для inline-чипов без контекста хоста
 * (например, чип в обсуждении игры): иначе id персонажа можно принять за id игры.
 */
export const GAME_ID_ROUTE_NAMES: ReadonlySet<string> = new Set(['GameDetail', 'GameEdit', 'NpcDetail', 'NpcEdit']);
