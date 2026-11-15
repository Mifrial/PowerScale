import type { InlineTokenScope } from '@/modules/Messages/Inline/Dto/InlineTokenScope';
import type { ITokenOption } from '@/modules/Messages/Inline/Interface/ITokenOption';
import type { ITokenSource } from '@/modules/Messages/Inline/Interface/ITokenSource';
import { LORE_INLINE_SOURCE } from '@/modules/Roleplay/Lore/Constant/Inline/LORE_INLINE_SOURCE';
import type { ILoreApi } from '@/modules/Roleplay/Lore/Interface/ILoreApi';
import type { LoreInlineValueService } from '@/modules/Roleplay/Lore/Service/Inline/LoreInlineValueService';

/** Источник «Вставить ссылку» для статей кодекса: мир как область, затем поиск статей. */
export class LoreInlineTokenSourceService {
  constructor(
    private readonly getApi: () => ILoreApi,
    private readonly valueService: LoreInlineValueService,
  ) {}

  build(): ITokenSource {
    return {
      type: LORE_INLINE_SOURCE.type,
      label: LORE_INLINE_SOURCE.label,
      icon: LORE_INLINE_SOURCE.icon,
      scopeLabel: LORE_INLINE_SOURCE.scopeLabel,
      loadScopes: () => this.loadScopes(),
      search: (query, scope) => this.search(query, scope),
    };
  }

  async loadScopes(): Promise<InlineTokenScope[]> {
    const page = await this.getApi().findWorldPage({
      limit: LORE_INLINE_SOURCE.worldLimit,
      offset: 0,
      state: 'active',
    });

    return page.items.map((world) => ({ value: world.code, label: world.name }));
  }

  async search(query: string, scope?: string): Promise<ITokenOption[]> {
    if (scope === undefined || scope === '') return [];
    const page = await this.getApi().findArticlePage({
      worldCode: scope,
      limit: LORE_INLINE_SOURCE.searchLimit,
      offset: 0,
      q: query.trim() || undefined,
      status: 'published',
    });

    return page.items.map((article) => ({
      value: this.valueService.build(scope, article.code),
      label: article.title,
    }));
  }
}
