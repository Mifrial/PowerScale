import type { LoreArticleInput } from '@/modules/Roleplay/Lore/Dto/LoreArticleInput';
import type { LoreDraft } from '@/modules/Roleplay/Lore/Dto/LoreDraft';

const STORAGE_PREFIX = 'powerscale.lore.draft.';

/** Локальный черновик статьи: ключ мира и кода статьи, без сервера. */
export function useLoreDraft(worldCode: string) {
  function key(articleCode: string): string {
    return `${STORAGE_PREFIX}${worldCode}.${articleCode}`;
  }

  function load(articleCode: string): LoreDraft | null {
    try {
      const raw = localStorage.getItem(key(articleCode));
      if (!raw) return null;
      const parsed = JSON.parse(raw) as LoreDraft;
      if (!parsed.article || typeof parsed.article.title !== 'string') return null;

      return parsed;
    } catch {
      return null;
    }
  }

  function save(articleCode: string, article: LoreArticleInput, expectedVersion?: number): void {
    const draft: LoreDraft = {
      article,
      expectedVersion,
      savedAt: Math.floor(Date.now() / 1000),
    };
    try {
      localStorage.setItem(key(articleCode), JSON.stringify(draft));
    } catch {
      // Квота localStorage не должна ломать редактирование.
    }
  }

  function clear(articleCode: string): void {
    try {
      localStorage.removeItem(key(articleCode));
    } catch {
      // Нет доступа к хранилищу — черновика и не было.
    }
  }

  return { load, save, clear };
}
