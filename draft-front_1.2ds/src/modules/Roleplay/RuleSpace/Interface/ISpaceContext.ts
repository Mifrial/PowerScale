import type { Space } from '@/modules/Roleplay/Space/Dto/Space';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/** Контракт hosting-контекста пространства, который Space предоставляет страницам-детям
 *  (включая страницы Rule-модуля). Доступ — через `spaceContextKey` из `Space/init`. */
export interface ISpaceContext {
  space: Space | null;
  spaceId: number | null;
  effectiveRules: Rule[];
  ctx: string | undefined;
  isDraftContext: boolean;
  isRevisionContext: boolean;
  loading: boolean;
  error: string | null;
  retry: () => void;
}
