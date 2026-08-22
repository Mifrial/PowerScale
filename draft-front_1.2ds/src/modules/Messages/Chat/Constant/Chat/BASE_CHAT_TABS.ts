import type { IChatTab } from '@/modules/Messages/Chat/Interface/IChatTab';

export const BASE_CHAT_TABS: IChatTab[] = [
  { key: 'personal', label: 'Сообщения', icon: 'mdi-account', types: ['private', 'group'], sortOrder: 0 },
];
