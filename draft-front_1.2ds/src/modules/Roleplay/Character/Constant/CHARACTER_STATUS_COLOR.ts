import type { CharacterStatus } from '@/modules/Roleplay/Character/Enum/CharacterStatus';

export const CHARACTER_STATUS_COLOR: Record<CharacterStatus, string> = {
  draft: 'grey',
  ready: 'success',
  moderation: 'info',
  needs_fix: 'error',
};
