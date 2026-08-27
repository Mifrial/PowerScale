import type { RuleType } from '@/modules/Roleplay/Rule/Enum/RuleType';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { MechanicPayload } from '@/modules/Roleplay/Rule/Dto/MechanicPayload';

export interface Rule {
  id: string;
  /** Семантический ключ правила (глобально уникален). Задаётся при создании и не меняется. */
  code: string;
  type: RuleType;
  name: string;
  /** Безопасный HTML после санитизации; plain text поддерживается для обратной совместимости. */
  description: string;
  spaceId: number;
  spec?: RuleSpec;
  keywordIds?: number[];
  mechanicId?: number | null;
  /** Контекст инстанции механики (см. MechanicPayload); есть только при mechanicId. */
  mechanic_payload?: MechanicPayload | null;
  createdAt: string;
  updatedAt?: string;
}
