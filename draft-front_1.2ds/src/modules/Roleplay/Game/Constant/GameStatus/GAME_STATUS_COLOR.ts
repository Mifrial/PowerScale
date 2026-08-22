import type { GameStatus } from '@/modules/Roleplay/Game/Enum/GameStatus';

export const GAME_STATUS_COLOR: Record<GameStatus, string> = {
  draft: 'grey',
  recruiting: 'info',
  in_process: 'primary',
  paused: 'warning',
  playing: 'success',
  completed: 'secondary',
};
