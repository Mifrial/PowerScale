/** Ключ игровой сущности в боевом контексте: персонаж игрока или НПС игры. */
export type CombatEntityKey = `character:${number}` | `npc:${number}`;
