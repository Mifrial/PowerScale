import { abortableDelay } from '@/modules/Core/Engine/Mock/abortableDelay';
import { users } from '@/modules/Core/User/Mock/mockUsers';
import type { LoreArticle } from '@/modules/Roleplay/Lore/Dto/LoreArticle';
import type { LoreArticleDetail } from '@/modules/Roleplay/Lore/Dto/LoreArticleDetail';
import type { LoreArticlePage } from '@/modules/Roleplay/Lore/Dto/LoreArticlePage';
import type { LoreArticleQuery } from '@/modules/Roleplay/Lore/Dto/LoreArticleQuery';
import type { LoreArticleSummary } from '@/modules/Roleplay/Lore/Dto/LoreArticleSummary';
import type { LoreBacklink } from '@/modules/Roleplay/Lore/Dto/LoreBacklink';
import type { LoreFile } from '@/modules/Roleplay/Lore/Dto/File/LoreFile';
import type { LoreFileArticle } from '@/modules/Roleplay/Lore/Dto/File/LoreFileArticle';
import type { LoreFileKeyword } from '@/modules/Roleplay/Lore/Dto/File/LoreFileKeyword';
import type { LoreImportCounts } from '@/modules/Roleplay/Lore/Dto/LoreImportCounts';
import type { LoreImportOptions } from '@/modules/Roleplay/Lore/Dto/LoreImportOptions';
import type { LoreImportReport } from '@/modules/Roleplay/Lore/Dto/LoreImportReport';
import type { LoreImportWarning } from '@/modules/Roleplay/Lore/Dto/LoreImportWarning';
import type { LoreReader } from '@/modules/Roleplay/Lore/Dto/LoreReader';
import type { LoreReaderCandidateQuery } from '@/modules/Roleplay/Lore/Dto/LoreReaderCandidateQuery';
import type { LoreSection } from '@/modules/Roleplay/Lore/Dto/LoreSection';
import type { LoreSectionInput } from '@/modules/Roleplay/Lore/Dto/LoreSectionInput';
import type { LoreWorld } from '@/modules/Roleplay/Lore/Dto/LoreWorld';
import type { LoreWorldDetail } from '@/modules/Roleplay/Lore/Dto/LoreWorldDetail';
import type { LoreWorldPage } from '@/modules/Roleplay/Lore/Dto/LoreWorldPage';
import type { LoreWorldQuery } from '@/modules/Roleplay/Lore/Dto/LoreWorldQuery';
import type { SaveLoreArticleData } from '@/modules/Roleplay/Lore/Dto/SaveLoreArticleData';
import type { SaveLoreWorldData } from '@/modules/Roleplay/Lore/Dto/SaveLoreWorldData';
import type { ILoreApi } from '@/modules/Roleplay/Lore/Interface/ILoreApi';
import { LoreApiError } from '@/modules/Roleplay/Lore/Service/LoreApiError';

let nextWorldId = 100;
let nextArticleId = 1000;
let nextSectionId = 500;
let nextKeywordId = 900;

/** Время фикстуры. */
function utc(year: number, month: number, day: number): number {
  return Math.floor(Date.UTC(year, month - 1, day) / 1000);
}

function clone<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T;
}

function now(): number {
  return Math.floor(Date.now() / 1000);
}

function summarize(article: LoreArticle): LoreArticleSummary {
  return {
    id: article.id,
    code: article.code,
    title: article.title,
    summary: article.summary,
    sectionCode: article.sectionCode,
    sortOrder: article.sortOrder,
    keywordIds: article.keywordIds,
    status: article.status,
    visibility: article.visibility,
    isArchived: article.isArchived,
    version: article.version,
    updatedAt: article.updatedAt,
  };
}

function extractLinks(bodyHtml: string): { type: string; code: string }[] {
  const links = new Map<string, { type: string; code: string }>();
  const patterns = [
    { type: 'lore', attribute: 'data-lore-code' },
    { type: 'rule', attribute: 'data-rule-code' },
  ];
  for (const pattern of patterns) {
    const regex = new RegExp(`${pattern.attribute}="([^"]+)"`, 'g');
    for (const match of bodyHtml.matchAll(regex)) {
      const code = match[1]?.trim() ?? '';
      if (code !== '') links.set(`${pattern.type}:${code}`, { type: pattern.type, code });
    }
  }

  return [...links.values()];
}

function bodyText(bodyHtml: string): string {
  return bodyHtml
    .replace(/<[^>]+>/g, ' ')
    .replace(/&nbsp;/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function readersOf(readerIds: number[]): LoreReader[] {
  return readerIds
    .map((readerId) => users.find((user) => user.id === readerId))
    .filter((user): user is NonNullable<typeof user> => !!user)
    .map((user) => ({ id: user.id, login: user.login, name: user.name }));
}

function detail(article: LoreArticle): LoreArticleDetail {
  return {
    ...article,
    canEdit: true,
    links: extractLinks(article.bodyHtml),
    backlinks: [],
    readers: readersOf(article.readerIds),
  };
}

function makeArticle(input: Partial<LoreArticle> & Pick<LoreArticle, 'code' | 'title'>): LoreArticle {
  return {
    id: nextArticleId++,
    code: input.code,
    title: input.title,
    summary: input.summary ?? '',
    sectionCode: input.sectionCode ?? null,
    sortOrder: input.sortOrder ?? 0,
    keywordIds: input.keywordIds ?? [],
    status: input.status ?? 'draft',
    visibility: input.visibility ?? 'public',
    isArchived: input.isArchived ?? false,
    version: input.version ?? 1,
    updatedAt: input.updatedAt ?? utc(2026, 9, 1),
    bodyHtml: input.bodyHtml ?? '<p></p>',
    readerIds: input.readerIds ?? [],
    createdBy: input.createdBy ?? 1,
    updatedBy: input.updatedBy ?? null,
    createdAt: input.createdAt ?? utc(2026, 9, 1),
  };
}

function section(
  code: string,
  name: string,
  parentCode: string | null,
  sortOrder: number,
  icon: string,
  description = '',
): LoreSection {
  return { id: nextSectionId++, code, name, description, parentCode, sortOrder, icon };
}

function world(
  code: string,
  name: string,
  description: string,
  visibility: 'public' | 'private',
  sections: LoreSection[],
): LoreWorldDetail {
  return {
    id: nextWorldId++,
    ownerId: 1,
    code,
    name,
    description,
    visibility,
    spaceCode: '',
    version: 1,
    isArchived: false,
    canEdit: true,
    updatedBy: 1,
    createdAt: utc(2026, 8, 1),
    updatedAt: utc(2026, 9, 1),
    sections,
    articles: [],
    sectionCount: sections.length,
    articleCount: 0,
  };
}

const austerlandArticles = (): LoreArticleDetail[] => [
  detail(
    makeArticle({
      code: 'fall-of-the-empire',
      title: 'Падение империи',
      summary: 'Как север потерял столицу.',
      sectionCode: 'history',
      status: 'published',
      keywordIds: [1],
      bodyHtml:
        '<h2>Причины</h2><p>См. <a data-lore-code="old-capital">старую столицу</a> и правило ' +
        '<a data-rule-code="magic-resistance">сопротивления магии</a>.</p>' +
        '<table><tbody><tr><td>Год</td><td>Событие</td></tr></tbody></table>' +
        '<h3>Итог</h3><p>Империя распалась.</p>',
    }),
  ),
  detail(
    makeArticle({
      code: 'old-capital',
      title: 'Старая столица',
      summary: 'Город, который больше не столица.',
      sectionCode: 'places',
      status: 'published',
      keywordIds: [1, 2],
      bodyHtml: '<p>Руины на берегу. См. <a data-lore-code="fall-of-the-empire">падение империи</a>.</p>',
    }),
  ),
  detail(
    makeArticle({
      code: 'gm-secret',
      title: 'Тайна севера',
      summary: 'Только для ведущего.',
      sectionCode: 'wars',
      status: 'published',
      visibility: 'secret',
      readerIds: [1],
      bodyHtml: '<p>Никому не рассказывать.</p>',
    }),
  ),
];

const diaryArticles = (): LoreArticleDetail[] => [
  detail(
    makeArticle({
      code: 'draft-idea',
      title: 'Идея мира',
      sectionCode: 'notes',
      status: 'draft',
      bodyHtml: '<p>Черновик.</p>',
    }),
  ),
];

let worlds: LoreWorldDetail[] = [];
let articlesByWorld = new Map<string, LoreArticleDetail[]>();

function rebuild(): void {
  nextWorldId = 100;
  nextArticleId = 1000;
  nextSectionId = 500;

  const austerland = world('austerland', 'Аустерленд', 'Холодный север с руинами империи.', 'public', [
    section('history', 'История', null, 0, 'mdi-history', 'Хроника севера'),
    section('wars', 'Войны', 'history', 1, 'mdi-sword'),
    section('places', 'Места', null, 2, 'mdi-map-marker'),
  ]);
  const diary = world('diary', 'Личный дневник', 'Приватные наброски.', 'private', [
    section('notes', 'Заметки', null, 0, 'mdi-note-text'),
  ]);

  articlesByWorld = new Map([
    [austerland.code, austerlandArticles()],
    [diary.code, diaryArticles()],
  ]);
  worlds = [austerland, diary];
  worlds.forEach(syncWorld);
  worlds.forEach(recomputeBacklinks);
}

function articlesOf(worldCode: string): LoreArticleDetail[] {
  return articlesByWorld.get(worldCode) ?? [];
}

function syncWorld(worldDetail: LoreWorldDetail): void {
  const articles = articlesOf(worldDetail.code);
  worldDetail.articles = articles.map(summarize);
  worldDetail.sectionCount = worldDetail.sections.length;
  worldDetail.articleCount = articles.length;
}

function recomputeBacklinks(worldDetail: LoreWorldDetail): void {
  const articles = articlesOf(worldDetail.code);
  for (const target of articles) {
    target.backlinks = articles
      .filter((source) => source !== target)
      .filter((source) => source.links.some((link) => link.type === 'lore' && link.code === target.code))
      .map<LoreBacklink>((source) => ({ articleId: source.id, code: source.code, title: source.title }));
  }
}

/** Сброс мока для тестов. */
export function resetLoreMock(): void {
  rebuild();
}

rebuild();

function worldByCode(code: string): LoreWorldDetail {
  const found = worlds.find((item) => item.code === code);
  if (!found) throw new LoreApiError('LORE_NOT_FOUND', 'Мир кодекса не найден');

  return found;
}

function articleByCode(worldCode: string, articleCode: string): LoreArticleDetail {
  const found = articlesOf(worldCode).find((item) => item.code === articleCode);
  if (!found) throw new LoreApiError('LORE_NOT_FOUND', 'Статья кодекса не найдена');

  return found;
}

function collectKeywordCodes(keywordIds: number[]): string[] {
  return keywordIds.map((keywordId) => `keyword-${keywordId}`);
}

function keywordIdByCode(code: string): number {
  const match = /^keyword-(\d+)$/.exec(code);
  if (match) return Number(match[1]);

  return nextKeywordId++;
}

function loginOf(userId: number): string {
  return users.find((user) => user.id === userId)?.login ?? `user-${userId}`;
}

function userIdByLogin(login: string): number | null {
  return users.find((user) => user.login === login)?.id ?? null;
}

function articleMatches(item: LoreArticleSummary, query: LoreArticleQuery): boolean {
  if (!query.includeArchived && item.isArchived) return false;
  if (query.sectionCode !== undefined && item.sectionCode !== query.sectionCode) return false;
  if (query.status !== undefined && item.status !== query.status) return false;
  if (query.visibility !== undefined && item.visibility !== query.visibility) return false;
  if (query.keywordIds && query.keywordIds.length > 0) {
    if (!query.keywordIds.some((keywordId) => item.keywordIds.includes(keywordId))) return false;
  }

  const needle = query.q?.trim().toLowerCase();
  if (!needle) return true;
  const full = articlesOf(query.worldCode).find((article) => article.code === item.code);

  return [item.title, item.summary, item.code, full ? bodyText(full.bodyHtml) : ''].some((part) =>
    part.toLowerCase().includes(needle),
  );
}

function buildFile(worldDetail: LoreWorldDetail): LoreFile {
  const articles = articlesOf(worldDetail.code);

  return {
    format: 'powerscale.lore',
    formatVersion: 1,
    exportedAt: now(),
    world: {
      code: worldDetail.code,
      name: worldDetail.name,
      description: worldDetail.description,
      visibility: worldDetail.visibility,
      spaceCode: worldDetail.spaceCode,
    },
    source: {
      ownerLogin: loginOf(worldDetail.ownerId),
      articleCount: articles.length,
      sectionCount: worldDetail.sections.length,
    },
    sections: worldDetail.sections.map((item) => ({
      code: item.code,
      name: item.name,
      description: item.description,
      parentCode: item.parentCode,
      sortOrder: item.sortOrder,
      icon: item.icon,
    })),
    keywords: [...new Set(articles.flatMap((item) => collectKeywordCodes(item.keywordIds)))].map<LoreFileKeyword>(
      (code) => ({ code, name: code, description: '', active: true }),
    ),
    articles: articles.map<LoreFileArticle>((item) => ({
      code: item.code,
      title: item.title,
      summary: item.summary,
      bodyHtml: item.bodyHtml,
      sectionCode: item.sectionCode,
      sortOrder: item.sortOrder,
      keywordCodes: collectKeywordCodes(item.keywordIds),
      status: item.status,
      visibility: item.visibility,
      readers: item.readerIds.map(loginOf),
      isArchived: item.isArchived,
    })),
  };
}

function emptyCounts(): LoreImportCounts {
  return { sections: 0, articles: 0 };
}

function applyFile(
  file: LoreFile,
  target: LoreWorldDetail,
  conflictPolicy: 'skip' | 'overwrite',
  dryRun: boolean,
  warnings: LoreImportWarning[],
): { created: LoreImportCounts; updated: LoreImportCounts; skipped: LoreImportCounts } {
  const created = emptyCounts();
  const updated = emptyCounts();
  const skipped = emptyCounts();

  for (const fileSection of file.sections) {
    const existing = target.sections.find((item) => item.code === fileSection.code);
    if (!existing) {
      created.sections++;
      if (!dryRun) target.sections.push({ id: nextSectionId++, ...fileSection });
      continue;
    }

    if (conflictPolicy === 'overwrite') {
      updated.sections++;
      if (!dryRun) Object.assign(existing, fileSection);
    } else {
      skipped.sections++;
    }
  }

  const articles = articlesOf(target.code);
  for (const fileArticle of file.articles) {
    const existing = articles.find((item) => item.code === fileArticle.code);
    if (existing && conflictPolicy === 'skip') {
      skipped.articles++;
      continue;
    }

    const readerIds: number[] = [];
    for (const login of fileArticle.readers) {
      const userId = userIdByLogin(login);
      if (userId === null) {
        warnings.push({
          code: 'reader-unknown',
          path: `articles[${fileArticle.code}].readers`,
          message: `Читатель ${login} не найден и пропущен`,
        });
        continue;
      }
      readerIds.push(userId);
    }

    if (existing) updated.articles++;
    else created.articles++;
    if (dryRun) continue;

    const values = makeArticle({
      code: fileArticle.code,
      title: fileArticle.title,
      summary: fileArticle.summary,
      sectionCode: fileArticle.sectionCode,
      sortOrder: fileArticle.sortOrder,
      keywordIds: fileArticle.keywordCodes.map(keywordIdByCode),
      status: fileArticle.status,
      visibility: fileArticle.visibility,
      isArchived: fileArticle.isArchived,
      bodyHtml: fileArticle.bodyHtml,
      id: existing?.id,
      version: existing ? existing.version + 1 : 1,
      updatedAt: now(),
      readerIds,
      createdBy: existing?.createdBy,
      createdAt: existing?.createdAt,
    });
    const next = detail({ ...values, id: existing?.id ?? values.id });
    if (existing) {
      articles[articles.indexOf(existing)] = next;
    } else {
      articles.push(next);
    }
  }

  if (!dryRun) syncWorld(target);

  return { created, updated, skipped };
}

export const mockLoreApi: ILoreApi = {
  async findWorldPage(query: LoreWorldQuery, signal?: AbortSignal): Promise<LoreWorldPage> {
    await abortableDelay(120, signal);
    const needle = query.q?.trim().toLowerCase() ?? '';
    const matched = worlds.filter((item) => {
      if (query.state === 'active' && item.isArchived) return false;
      if (query.state === 'archived' && !item.isArchived) return false;
      if (query.visibility !== undefined && item.visibility !== query.visibility) return false;
      if (!needle) return true;

      return [item.name, item.description, item.code].some((part) => part.toLowerCase().includes(needle));
    });

    return {
      items: matched.slice(query.offset, query.offset + query.limit).map(clone),
      total: matched.length,
    };
  },

  async getWorld(code: string, signal?: AbortSignal): Promise<LoreWorldDetail> {
    await abortableDelay(100, signal);

    return clone(worldByCode(code));
  },

  async saveWorld(data: SaveLoreWorldData, signal?: AbortSignal): Promise<LoreWorld> {
    await abortableDelay(100, signal);
    const existing = worlds.find((item) => item.code === data.world.code);
    if (existing) {
      if (data.version === undefined) {
        throw new LoreApiError('LORE_INVALID', 'Код мира уже занят');
      }
      if (data.version !== existing.version) {
        throw new LoreApiError('LORE_VERSION_CONFLICT', 'Мир изменили в другой сессии', {
          currentVersion: existing.version,
          updatedAt: existing.updatedAt,
          updatedBy: existing.updatedBy,
        });
      }

      Object.assign(existing, data.world, { version: existing.version + 1, updatedAt: now(), updatedBy: 1 });

      return clone(existing);
    }

    if (data.version !== undefined) throw new LoreApiError('LORE_NOT_FOUND', 'Мир кодекса не найден');
    const created = world(data.world.code, data.world.name, data.world.description, data.world.visibility, []);
    created.spaceCode = data.world.spaceCode;
    worlds.push(created);

    return clone(created);
  },

  async archiveWorld(
    code: string,
    archived: boolean,
    expectedVersion: number,
    signal?: AbortSignal,
  ): Promise<LoreWorld> {
    await abortableDelay(80, signal);
    const target = worldByCode(code);
    if (expectedVersion !== target.version) {
      throw new LoreApiError('LORE_VERSION_CONFLICT', 'Мир изменили в другой сессии', {
        currentVersion: target.version,
        updatedAt: target.updatedAt,
        updatedBy: target.updatedBy,
      });
    }

    target.isArchived = archived;
    target.version++;
    target.updatedAt = now();

    return clone(target);
  },

  async saveTree(
    code: string,
    sections: LoreSectionInput[],
    expectedWorldVersion: number,
    signal?: AbortSignal,
  ): Promise<LoreWorldDetail> {
    await abortableDelay(100, signal);
    const target = worldByCode(code);
    if (expectedWorldVersion !== target.version) {
      throw new LoreApiError('LORE_VERSION_CONFLICT', 'Мир изменили в другой сессии', {
        currentVersion: target.version,
        updatedAt: target.updatedAt,
        updatedBy: target.updatedBy,
      });
    }

    target.sections = sections.map((item) => ({ id: nextSectionId++, ...item }));
    target.version++;
    target.updatedAt = now();
    syncWorld(target);

    return clone(target);
  },

  async findArticlePage(query: LoreArticleQuery, signal?: AbortSignal): Promise<LoreArticlePage> {
    await abortableDelay(80, signal);
    const matched = worldByCode(query.worldCode).articles.filter((item) => articleMatches(item, query));
    const sorted = [...matched].sort((left, right) => {
      if (query.sort === 'recent') return right.updatedAt - left.updatedAt;

      return (
        (left.sectionCode ?? '').localeCompare(right.sectionCode ?? '') ||
        left.sortOrder - right.sortOrder ||
        left.title.localeCompare(right.title)
      );
    });

    return { items: sorted.slice(query.offset, query.offset + query.limit), total: sorted.length };
  },

  async getArticle(worldCode: string, articleCode: string, signal?: AbortSignal): Promise<LoreArticleDetail> {
    await abortableDelay(80, signal);
    recomputeBacklinks(worldByCode(worldCode));

    return clone(articleByCode(worldCode, articleCode));
  },

  async saveArticle(data: SaveLoreArticleData, signal?: AbortSignal): Promise<LoreArticleDetail> {
    await abortableDelay(100, signal);
    const target = worldByCode(data.worldCode);
    const existing = articlesOf(target.code).find((item) => item.code === data.article.code);
    if (existing) {
      if (data.expectedVersion === undefined) {
        throw new LoreApiError('LORE_INVALID', 'Код статьи уже занят');
      }
      if (data.expectedVersion !== existing.version) {
        throw new LoreApiError('LORE_VERSION_CONFLICT', 'Статью изменили в другой сессии', {
          currentVersion: existing.version,
          updatedAt: existing.updatedAt,
          updatedBy: existing.updatedBy,
        });
      }
    }

    const values = makeArticle({
      ...data.article,
      id: existing?.id,
      version: existing ? existing.version + 1 : 1,
      updatedAt: now(),
      readerIds: data.article.readers,
      createdBy: existing?.createdBy,
      createdAt: existing?.createdAt,
    });
    const next = detail({ ...values, id: existing?.id ?? values.id });
    if (existing) {
      articlesOf(target.code)[articlesOf(target.code).indexOf(existing)] = next;
    } else {
      articlesOf(target.code).push(next);
    }

    syncWorld(target);
    recomputeBacklinks(target);

    return clone(next);
  },

  async archiveArticle(
    worldCode: string,
    articleCode: string,
    archived: boolean,
    expectedVersion: number,
    signal?: AbortSignal,
  ): Promise<void> {
    await abortableDelay(80, signal);
    const target = articleByCode(worldCode, articleCode);
    if (expectedVersion !== target.version) {
      throw new LoreApiError('LORE_VERSION_CONFLICT', 'Статью изменили в другой сессии', {
        currentVersion: target.version,
        updatedAt: target.updatedAt,
        updatedBy: target.updatedBy,
      });
    }

    target.isArchived = archived;
    target.version++;
    target.updatedAt = now();
    syncWorld(worldByCode(worldCode));
  },

  async findReaderCandidates(query: LoreReaderCandidateQuery, signal?: AbortSignal): Promise<LoreReader[]> {
    await abortableDelay(80, signal);
    const needle = query.q?.trim().toLowerCase() ?? '';

    return users
      .filter((user) => user.active)
      .filter((user) => !needle || `${user.login} ${user.name}`.toLowerCase().includes(needle))
      .slice(0, query.limit ?? 20)
      .map((user) => ({ id: user.id, login: user.login, name: user.name }));
  },

  async exportWorld(worldCode: string, signal?: AbortSignal): Promise<LoreFile> {
    await abortableDelay(100, signal);

    return buildFile(worldByCode(worldCode));
  },

  async importWorld(payload: LoreFile, options: LoreImportOptions, signal?: AbortSignal): Promise<LoreImportReport> {
    await abortableDelay(120, signal);
    if (payload.format !== 'powerscale.lore' || payload.formatVersion !== 1) {
      throw new LoreApiError('LORE_INVALID', 'Неизвестный формат файла кодекса');
    }

    const warnings: LoreImportWarning[] = [];
    let target: LoreWorldDetail;
    if (options.mode === 'merge') {
      const targetCode = options.targetWorldCode;
      if (!targetCode) throw new LoreApiError('LORE_INVALID', 'Для слияния нужен целевой мир');
      target = worldByCode(targetCode);
    } else {
      let code = payload.world.code;
      let suffix = 2;
      while (worlds.some((item) => item.code === code)) {
        code = `${payload.world.code}-${suffix++}`;
      }
      if (code !== payload.world.code) {
        warnings.push({
          code: 'world-code-suffixed',
          path: 'world.code',
          message: `Код ${payload.world.code} занят, импорт создаст ${code}`,
        });
      }
      target = world(code, payload.world.name, payload.world.description, payload.world.visibility, []);
      target.spaceCode = payload.world.spaceCode;
      if (!options.dryRun) {
        worlds.push(target);
        articlesByWorld.set(target.code, []);
      }
    }

    const counts = applyFile(payload, target, options.conflictPolicy, options.dryRun === true, warnings);

    return {
      worldCode: target.code,
      dryRun: options.dryRun === true,
      created: counts.created,
      updated: counts.updated,
      skipped: counts.skipped,
      warnings,
    };
  },
};
