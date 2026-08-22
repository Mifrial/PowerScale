import type { GameStatus } from '@/modules/Roleplay/Game/Enum/GameStatus';

export interface GameStatusOption {
  value: GameStatus;
  label: string;
}

export const GAME_STATUS_OPTIONS: GameStatusOption[] = [
  { value: 'draft', label: 'Черновик' },
  { value: 'recruiting', label: 'Набор игроков' },
  { value: 'in_process', label: 'В процессе' },
  { value: 'paused', label: 'На паузе' },
  { value: 'playing', label: 'Идёт игра' },
  { value: 'completed', label: 'Завершена' },
];
