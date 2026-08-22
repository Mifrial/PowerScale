import type { CharacterStatus } from '@/modules/Roleplay/Character/Enum/CharacterStatus';

export interface CharacterStatusOption {
  value: CharacterStatus;
  label: string;
}

export const CHARACTER_STATUS_OPTIONS: CharacterStatusOption[] = [
  { value: 'draft', label: 'Черновик' },
  { value: 'ready', label: 'Готов' },
  { value: 'moderation', label: 'На модерации' },
  { value: 'needs_fix', label: 'Требует исправления' },
];
