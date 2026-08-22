import type { ChatRole } from '@/modules/Messages/Chat/Dto/ChatRole';

export interface IChatType {
  type: string;
  icon: string;
  color: string;
  /** Роли, которые может нести участник чата этого типа (значения задаёт домен). */
  roles?: ChatRole[];
  /** Поддерживает ли тип видимость сообщений (меню «Всем/Роль/Выбранным»). */
  supportsVisibility?: boolean;
}
