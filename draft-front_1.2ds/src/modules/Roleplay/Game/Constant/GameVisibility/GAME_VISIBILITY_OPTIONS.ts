import type { GameVisibility } from '@/modules/Roleplay/Game/Enum/GameVisibility';

export interface GameVisibilityOption {
  value: GameVisibility;
  label: string;
}

export const GAME_VISIBILITY_OPTIONS: GameVisibilityOption[] = [
  { value: 'all', label: 'Все' },
  { value: 'friends', label: 'Друзья' },
  { value: 'players', label: 'Принятые игроки' },
  { value: 'invited', label: 'Приглашённые' },
  { value: 'whitelist', label: 'Определённый список' },
];
