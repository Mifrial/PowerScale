import type { GameJoinPolicy } from '@/modules/Roleplay/Game/Enum/GameJoinPolicy';

export interface GameJoinPolicyOption {
  value: GameJoinPolicy;
  label: string;
}

export const GAME_JOIN_POLICY_OPTIONS: GameJoinPolicyOption[] = [
  { value: 'anyone', label: 'Все желающие' },
  { value: 'friends', label: 'Друзья' },
  { value: 'invite_only', label: 'Только по приглашению' },
  { value: 'whitelist', label: 'Определённый список' },
];
