import type { IChatType } from '@/modules/Messages/Chat/Interface/IChatType';
import { CHAT_PERMISSION_SEE_ALL } from '@/modules/Messages/Chat/Constant/Chat/CHAT_PERMISSION_SEE_ALL';

/** Роли обсуждения персонажа: владелец видит все сообщения (chat.see_all), участник — по видимости. */
const CHARACTER_DISCUSSION_ROLES = [
  { code: 'owner', label: 'Владелец', permissions: [CHAT_PERMISSION_SEE_ALL] },
  { code: 'member', label: 'Участник', permissions: [] },
];

export const CHARACTER_CHAT_TYPES: IChatType[] = [
  {
    type: 'character_discussion',
    icon: 'mdi-account-details',
    color: 'secondary',
    roles: CHARACTER_DISCUSSION_ROLES,
    supportsVisibility: true,
  },
];
