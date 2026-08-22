/**
 * Статус записи добычи (ТР §3 `game_loot`): prepared — запас (только ведущий),
 * available — выдано «на разбор» (игроки видят и проявляют интерес),
 * distributed — роздано (итог зафиксирован).
 */
export type GameLootStatus = 'prepared' | 'available' | 'distributed';
