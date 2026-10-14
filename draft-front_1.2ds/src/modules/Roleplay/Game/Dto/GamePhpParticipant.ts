/** Сторона PHP-команды боя. */
export interface GamePhpParticipant {
  type: 'character' | 'npc';
  id: number;
}
