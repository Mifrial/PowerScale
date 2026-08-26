import type { ChatRulesContext } from '@/modules/Messages/Chat/Dto/ChatRulesContext';

/**
 * Провайдер правил чата: донор (Game/Character) резолвит opaque-контекст по типу и id чата.
 * `types: []` — провайдер по умолчанию (любой не-доменный тип чата).
 */
export interface IChatRulesProvider {
  types: readonly string[];
  /** null — провайдер не обслуживает такой чат. */
  resolve(type: string, chatId: number): Promise<ChatRulesContext | null>;
}
