import type { IChatTab } from '@/modules/Messages/Chat/Interface/IChatTab';

export const GAME_CHAT_TABS: IChatTab[] = [
  { key: 'game', label: 'Игровые', icon: 'mdi-dice-d6', types: ['game'], sortOrder: 1 },
  { key: 'game_discussion', label: 'Обсуждения игр', icon: 'mdi-forum', types: ['game_discussion'], sortOrder: 2 },
];
