import type { IChatTab } from '@/modules/Messages/Chat/Interface/IChatTab';

export const CHARACTER_CHAT_TABS: IChatTab[] = [
  {
    key: 'character_discussion',
    label: 'Обсуждения персонажей',
    icon: 'mdi-account-details',
    types: ['character_discussion'],
    sortOrder: 3,
  },
];
