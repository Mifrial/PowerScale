/** Участник раны (PHP ждёт kind, не type). */
export interface GamePhpWoundParty {
  kind: 'character' | 'npc';
  id: number;
}
