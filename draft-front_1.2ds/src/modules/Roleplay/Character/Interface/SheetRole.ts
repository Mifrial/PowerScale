import type { SheetAccessContext } from '@/modules/Roleplay/Character/Interface/SheetAccessContext';

/**
 * Инжектируемая роль видимости листа: модуль (например Game) регистрирует роль
 * `resolve`-предикатом. `fullAccess` — роль даёт полный доступ к листу
 * (например «ведущие контекста видят всё»), владелец не может это отменить.
 */
export interface SheetRole {
  name: string;
  fullAccess: boolean;
  resolve(ctx: SheetAccessContext): boolean;
}
