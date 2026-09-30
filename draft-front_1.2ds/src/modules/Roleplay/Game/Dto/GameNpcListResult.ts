import type { GameNpcSummary } from '@/modules/Roleplay/Game/Dto/GameNpcSummary';

export interface GameNpcListResult {
  items: GameNpcSummary[];
  nextCursor: string | null;
}
