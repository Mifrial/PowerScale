import { beforeEach, describe, expect, it } from 'vitest';
import { LoreApiError } from '@/modules/Roleplay/Lore/Service/LoreApiError';
import { mockLoreApi, resetLoreMock } from '@/modules/Roleplay/Lore/Mock/mockLoreApi';

describe('mockLoreApi', () => {
  beforeEach(() => {
    resetLoreMock();
  });

  it('отдаёт миры, дерево и статьи', async () => {
    const page = await mockLoreApi.findWorldPage({ limit: 10, offset: 0, state: 'active' });

    expect(page.total).toBe(2);
    const world = await mockLoreApi.getWorld('austerland');
    expect(world.sections).toHaveLength(3);
    expect(world.articles.map((item) => item.code)).toContain('fall-of-the-empire');
  });

  it('считает ссылки и обратные ссылки', async () => {
    const article = await mockLoreApi.getArticle('austerland', 'old-capital');

    expect(article.links.map((link) => link.code)).toContain('fall-of-the-empire');
    expect(article.backlinks.map((link) => link.code)).toEqual(['fall-of-the-empire']);
  });

  it('отвергает устаревшую версию статьи', async () => {
    const article = await mockLoreApi.getArticle('austerland', 'old-capital');

    await expect(
      mockLoreApi.saveArticle({
        worldCode: 'austerland',
        expectedVersion: article.version + 5,
        article: {
          code: article.code,
          title: 'Другое имя',
          summary: '',
          bodyHtml: '<p></p>',
          sectionCode: article.sectionCode,
          sortOrder: 0,
          keywordIds: [],
          status: 'published',
          visibility: 'public',
          readers: [],
        },
      }),
    ).rejects.toBeInstanceOf(LoreApiError);
  });

  it('экспортирует логины читателей и коды признаков', async () => {
    const file = await mockLoreApi.exportWorld('austerland');

    expect(file.format).toBe('powerscale.lore');
    const secret = file.articles.find((item) => item.code === 'gm-secret');
    expect(secret?.readers).toEqual(['ivan_p']);
    const fall = file.articles.find((item) => item.code === 'fall-of-the-empire');
    expect(fall?.keywordCodes).toEqual(['keyword-1']);
    expect(fall).not.toHaveProperty('id');
  });

  it('импортирует файл в новый мир и предупреждает о неизвестном логине', async () => {
    const file = await mockLoreApi.exportWorld('austerland');
    file.articles[0] = { ...file.articles[0]!, readers: ['ghost_login'] };

    const dryRun = await mockLoreApi.importWorld(file, { mode: 'new', conflictPolicy: 'skip', dryRun: true });
    expect(dryRun.worldCode).toBe('austerland-2');
    expect(dryRun.created.articles).toBe(file.articles.length);
    expect(dryRun.warnings.map((warning) => warning.code)).toContain('reader-unknown');

    const imported = await mockLoreApi.importWorld(file, { mode: 'new', conflictPolicy: 'skip' });
    const copy = await mockLoreApi.getWorld(imported.worldCode);
    expect(copy.sections).toHaveLength(file.sections.length);
    expect(copy.articles).toHaveLength(file.articles.length);
  });

  it('сливает файл в существующий мир по политике конфликтов', async () => {
    const file = await mockLoreApi.exportWorld('austerland');
    file.articles[0] = { ...file.articles[0]!, title: 'Новое имя' };

    const skipped = await mockLoreApi.importWorld(file, {
      mode: 'merge',
      targetWorldCode: 'austerland',
      conflictPolicy: 'skip',
    });
    expect(skipped.skipped.articles).toBe(file.articles.length);

    const overwritten = await mockLoreApi.importWorld(file, {
      mode: 'merge',
      targetWorldCode: 'austerland',
      conflictPolicy: 'overwrite',
    });
    expect(overwritten.updated.articles).toBe(file.articles.length);
    const article = await mockLoreApi.getArticle('austerland', file.articles[0]!.code);
    expect(article.title).toBe('Новое имя');
  });

  it('сохраняет дерево и отвергает устаревшую версию мира', async () => {
    const world = await mockLoreApi.getWorld('austerland');
    const sections = world.sections.map((section) => ({
      code: section.code,
      name: section.name,
      description: section.description,
      parentCode: section.parentCode,
      sortOrder: section.sortOrder,
      icon: section.icon,
    }));

    const saved = await mockLoreApi.saveTree('austerland', sections, world.version);
    expect(saved.version).toBe(world.version + 1);
    await expect(mockLoreApi.saveTree('austerland', sections, world.version)).rejects.toBeInstanceOf(LoreApiError);
  });
});
