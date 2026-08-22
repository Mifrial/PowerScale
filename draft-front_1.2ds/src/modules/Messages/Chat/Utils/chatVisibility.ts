import type { Chat } from '@/modules/Messages/Chat/Dto/Chat';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { ChatRole } from '@/modules/Messages/Chat/Dto/ChatRole';
import { CHAT_PERMISSION_SEE_ALL } from '@/modules/Messages/Chat/Constant/Chat/CHAT_PERMISSION_SEE_ALL';

/**
 * Видимость сообщения для зрителя (generic, без доменной семантики): отправитель всегда
 * видит своё; роль зрителя с правом `chat.see_all` видит всё (включая скрытое);
 * `forUsers`/`forRole` ограничивают остальных. Роли для типа чата берёт вызывающий
 * (getChatTypes по chat.type) — Chat не знает значений ролей.
 */
export function isMessageVisible(
  message: ChatMessage,
  chat: Pick<Chat, 'members'>,
  viewerId: number,
  roles: ChatRole[],
): boolean {
  if (message.userId === viewerId) return true;
  const visibility = message.visibility;
  if (!visibility || visibility.all !== false) return true;

  const viewerRole = chat.members?.find((member) => member.userId === viewerId)?.role;
  const seesAll = roles.some((role) => role.code === viewerRole && role.permissions.includes(CHAT_PERMISSION_SEE_ALL));
  if (seesAll) return true;
  if (visibility.forUsers?.includes(viewerId)) return true;

  const forRoles = typeof visibility.forRole === 'string' ? [visibility.forRole] : (visibility.forRole ?? []);
  if (viewerRole && forRoles.includes(viewerRole)) return true;

  return false;
}
