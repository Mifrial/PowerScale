import type { IChatType } from '@/modules/Messages/Chat/Interface/IChatType';

/** Роли группового чата: admin — модерация (задел: право «кика», НЕ chat.see_all); member — участник. */
const GROUP_ROLES = [
  { code: 'admin', label: 'Админ', permissions: [] },
  { code: 'member', label: 'Участник', permissions: [] },
];

export const BASE_CHAT_TYPES: IChatType[] = [
  { type: 'private', icon: 'mdi-account', color: 'primary' },
  { type: 'group', icon: 'mdi-account-group', color: 'success', roles: GROUP_ROLES, supportsVisibility: true },
];
