import type { GameRosterNpc } from '@/modules/Roleplay/Game/Dto/GameRosterNpc';

export interface GameNpcListResult {
  items: GameRosterNpc[];
  nextCursor: string | null;
}
