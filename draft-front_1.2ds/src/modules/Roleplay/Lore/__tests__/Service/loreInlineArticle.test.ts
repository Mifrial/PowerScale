import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mockLoreApi } from '@/modules/Roleplay/Lore/Mock/mockLoreApi';
import { LoreInlineArticleService } from '@/modules/Roleplay/Lore/Service/Inline/LoreInlineArticleService';
import { LoreInlineValueService } from '@/modules/Roleplay/Lore/Service/Inline/LoreInlineValueService';

const valueService = new LoreInlineValueService();
let service: LoreInlineArticleService;

describe('LoreInlineArticleService', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    service = new LoreInlineArticleService(() => mockLoreApi, valueService);
  });

  it('resolves an article once and keeps its title for describe', async () => {
    const spy = vi.spyOn(mockLoreApi, 'getArticle');
    const first = await service.resolve('austerland', 'old-capital');
    const second = await service.resolve('austerland', 'old-capital');

    expect(first?.title).toBe('Старая столица');
    expect(second).toBe(first);
    expect(spy).toHaveBeenCalledTimes(1);
    expect(service.peek('austerland', 'old-capital')).toBe('Старая столица');
  });

  it('returns null for an inaccessible or missing article', async () => {
    await expect(service.resolve('austerland', 'missing-article')).resolves.toBeNull();
  });

  it('does not cache network errors', async () => {
    const spy = vi.spyOn(mockLoreApi, 'getArticle').mockRejectedValueOnce(new Error('network'));

    await expect(service.resolve('austerland', 'fall-of-the-empire')).rejects.toThrow('network');
    const retried = await service.resolve('austerland', 'fall-of-the-empire');

    expect(retried?.title).toBe('Падение империи');
    expect(spy).toHaveBeenCalledTimes(2);
  });
});
