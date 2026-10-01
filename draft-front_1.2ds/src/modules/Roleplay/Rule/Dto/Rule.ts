import type { RuleType } from '@/modules/Roleplay/Rule/Enum/RuleType';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { RuleMechanicRef } from '@/modules/Roleplay/Rule/Dto/RuleMechanicRef';

export interface Rule {
  /** Ключ строки каталога; `null` — черновик / импорт, ещё не в БД. */
  id: number | null;
  /** Семантический ключ правила (глобально уникален). Задаётся при создании и не меняется. */
  code: string;
  type: RuleType;
  name: string;
  /** Безопасный HTML после санитизации; plain text поддерживается для обратной совместимости. */
  description: string;
  spaceId: number;
  /** Явная секция правила в каталоге текущей ревизии. */
  catalogSection?: string | null;
  /** Порядок правила внутри секции каталога. */
  catalogSortOrder?: number;
  spec?: RuleSpec;
  keywordIds?: number[];
  mechanics: RuleMechanicRef[];
  contentStatus?: string;
  /** Комментарий разработки; не игровой текст. */
  contentNote?: string;
  active?: boolean;
  createdAt: number;
}
