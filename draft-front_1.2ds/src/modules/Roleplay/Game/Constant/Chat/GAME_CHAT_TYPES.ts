import type { IChatType } from '@/modules/Messages/Chat/Interface/IChatType';
import { CHAT_PERMISSION_SEE_ALL } from '@/modules/Messages/Chat/Constant/Chat/CHAT_PERMISSION_SEE_ALL';

/** Роли игровых чатов (значения задаёт Game): 'gm' видит все сообщения (chat.see_all). */
const GAME_CHAT_ROLES = [
  { code: 'gm', label: 'Ведущий', permissions: [CHAT_PERMISSION_SEE_ALL] },
  { code: 'player', label: 'Игрок', permissions: [] },
];

export const GAME_CHAT_TYPES: IChatType[] = [
  { type: 'game', icon: 'mdi-dice-d6', color: 'warning', roles: GAME_CHAT_ROLES, supportsVisibility: true },
  { type: 'game_discussion', icon: 'mdi-forum', color: 'info', roles: GAME_CHAT_ROLES, supportsVisibility: true },
];
