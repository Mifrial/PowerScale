import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';

/**
 * Провайдер правил чата: по типу чата и id резолвит правила и механики ревизии, к которой
 * относится чат (игра/персонаж) или «актуальные правила» для обычных чатов. Доноры
 * (Game/Character) регистрируют провайдеры; мессенджер запрашивает по активному чату.
 * `types: []` — провайдер по умолчанию (любой не-доменный тип чата).
 */
export interface ChatRulesResolution {
  rules: Rule[];
  mechanics: Mechanic[];
  /** Ревизия, из которой взяты правила (для резолва правила в RuleSlider). */
  spaceId?: number | null;
  rulesRevision?: number | null;
}

export interface IChatRulesProvider {
  types: readonly string[];
  /** null — провайдер не обслуживает такой чат. */
  resolve(type: string, chatId: number): Promise<ChatRulesResolution | null>;
}
