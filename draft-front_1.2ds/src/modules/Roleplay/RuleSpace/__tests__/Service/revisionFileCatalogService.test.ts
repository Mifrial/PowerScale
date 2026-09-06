import { describe, expect, it } from 'vitest';
import { RevisionFileCatalogService } from '@/modules/Roleplay/RuleSpace/Service/RevisionFileCatalogService';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';

describe('RevisionFileCatalogService', () => {
  it('грузит оба списка параллельно', async () => {
    const keywords: Keyword[] = [{ id: 1, code: 'k', name: 'K', description: '', active: true }];
    const mechanics: Mechanic[] = [{ id: 2, code: 'm', name: 'M', description: '', version: '1' }];
    const service = new RevisionFileCatalogService(
      async () => keywords,
      async () => mechanics,
    );

    await expect(service.load()).resolves.toEqual({ keywords, mechanics });
  });

  it('пробрасывает ошибку list', async () => {
    const service = new RevisionFileCatalogService(
      async () => {
        throw new Error('каталог недоступен');
      },
      async () => [],
    );

    await expect(service.load()).rejects.toThrow('каталог недоступен');
  });
});
