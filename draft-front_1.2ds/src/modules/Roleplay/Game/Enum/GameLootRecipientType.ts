/**
 * Получатель доли добычи: 'character' — персонаж игры, 'npc' — НПС игры,
 * 'nowhere' — «вникуда» (доля ушла неучтённо, например остаток при делёжке).
 */
export type GameLootRecipientType = 'character' | 'npc' | 'nowhere';
