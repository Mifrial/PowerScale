import type { LoreArticleDetail } from '@/modules/Roleplay/Lore/Dto/LoreArticleDetail';
import type { ILoreApi } from '@/modules/Roleplay/Lore/Interface/ILoreApi';
import { LoreApiError } from '@/modules/Roleplay/Lore/Service/LoreApiError';
import type { LoreInlineValueService } from '@/modules/Roleplay/Lore/Service/Inline/LoreInlineValueService';

/**
 * Сессионный кэш резолва статьи по inline-ссылке: подпись чипа и защита
 * чата с несколькими ссылками от повторных запросов.
 */
export class LoreInlineArticleService {
  private readonly cache = new Map<string, Promise<LoreArticleDetail | null>>();
  private readonly titles = new Map<string, string>();

  constructor(
    private readonly getApi: () => ILoreApi,
    private readonly valueService: LoreInlineValueService,
  ) {}

  async resolve(worldCode: string, articleCode: string): Promise<LoreArticleDetail | null> {
    const key = this.valueService.build(worldCode, articleCode);
    const cached = this.cache.get(key);
    if (cached !== undefined) return cached;

    const request = this.load(worldCode, articleCode, key);
    this.cache.set(key, request);

    return request;
  }

  peek(worldCode: string, articleCode: string): string | null {
    return this.titles.get(this.valueService.build(worldCode, articleCode)) ?? null;
  }

  private async load(worldCode: string, articleCode: string, key: string): Promise<LoreArticleDetail | null> {
    try {
      const article = await this.getApi().getArticle(worldCode, articleCode);
      this.titles.set(key, article.title);

      return article;
    } catch (error) {
      if (error instanceof LoreApiError && (error.code === 'LORE_NOT_FOUND' || error.code === 'LORE_DENIED')) {
        return null;
      }
      this.cache.delete(key);

      throw error;
    }
  }
}
