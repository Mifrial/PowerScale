import type { InlineTokenScope } from '@/modules/Messages/Inline/Dto/InlineTokenScope';
import type { ITokenOption } from '@/modules/Messages/Inline/Interface/ITokenOption';

export interface ITokenSource {
  type: string;
  label: string;
  icon: string;
  /** Заголовок области поиска (например, «Мир»); виден, если задан loadScopes. */
  scopeLabel?: string;
  /** Доступные области источника; без них поиск идёт без выбора области. */
  loadScopes?: () => Promise<InlineTokenScope[]>;
  search(query: string, scope?: string): Promise<ITokenOption[]>;
}
