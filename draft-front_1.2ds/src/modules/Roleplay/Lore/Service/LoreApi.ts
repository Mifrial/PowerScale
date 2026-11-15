import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { LoreArticleDetail } from '@/modules/Roleplay/Lore/Dto/LoreArticleDetail';
import type { LoreArticlePage } from '@/modules/Roleplay/Lore/Dto/LoreArticlePage';
import type { LoreArticleQuery } from '@/modules/Roleplay/Lore/Dto/LoreArticleQuery';
import type { LoreFile } from '@/modules/Roleplay/Lore/Dto/File/LoreFile';
import type { LoreImportOptions } from '@/modules/Roleplay/Lore/Dto/LoreImportOptions';
import type { LoreImportReport } from '@/modules/Roleplay/Lore/Dto/LoreImportReport';
import type { LoreReader } from '@/modules/Roleplay/Lore/Dto/LoreReader';
import type { LoreReaderCandidateQuery } from '@/modules/Roleplay/Lore/Dto/LoreReaderCandidateQuery';
import type { LoreSectionInput } from '@/modules/Roleplay/Lore/Dto/LoreSectionInput';
import type { LoreWorld } from '@/modules/Roleplay/Lore/Dto/LoreWorld';
import type { LoreWorldDetail } from '@/modules/Roleplay/Lore/Dto/LoreWorldDetail';
import type { LoreWorldPage } from '@/modules/Roleplay/Lore/Dto/LoreWorldPage';
import type { LoreWorldQuery } from '@/modules/Roleplay/Lore/Dto/LoreWorldQuery';
import type { SaveLoreArticleData } from '@/modules/Roleplay/Lore/Dto/SaveLoreArticleData';
import type { SaveLoreWorldData } from '@/modules/Roleplay/Lore/Dto/SaveLoreWorldData';
import type { ILoreApi } from '@/modules/Roleplay/Lore/Interface/ILoreApi';
import { LoreApiError } from '@/modules/Roleplay/Lore/Service/LoreApiError';

/** HTTP-клиент кодекса: миры, дерево, статьи и перенос. */
export class LoreApi implements ILoreApi {
  constructor(private readonly engine: Engine) {}

  async findWorldPage(query: LoreWorldQuery, signal?: AbortSignal): Promise<LoreWorldPage> {
    const payload: Record<string, unknown> = { limit: query.limit, offset: query.offset };
    if (query.q !== undefined) payload.q = query.q;
    if (query.state !== undefined) payload.state = query.state;
    if (query.visibility !== undefined) payload.visibility = query.visibility;

    return this.requireData<LoreWorldPage>('lore.findWorldPage', payload, signal);
  }

  async getWorld(code: string, signal?: AbortSignal): Promise<LoreWorldDetail> {
    return this.requireData<LoreWorldDetail>('lore.getWorld', { code }, signal);
  }

  async saveWorld(data: SaveLoreWorldData, signal?: AbortSignal): Promise<LoreWorld> {
    const payload: Record<string, unknown> = { world: data.world };
    if (data.version !== undefined) payload.version = data.version;

    return this.requireData<LoreWorld>('lore.saveWorld', payload, signal);
  }

  async archiveWorld(
    code: string,
    archived: boolean,
    expectedVersion: number,
    signal?: AbortSignal,
  ): Promise<LoreWorld> {
    return this.requireData<LoreWorld>('lore.archiveWorld', { code, archived, expectedVersion }, signal);
  }

  async saveTree(
    code: string,
    sections: LoreSectionInput[],
    expectedWorldVersion: number,
    signal?: AbortSignal,
  ): Promise<LoreWorldDetail> {
    return this.requireData<LoreWorldDetail>('lore.saveTree', { code, sections, expectedWorldVersion }, signal);
  }

  async findArticlePage(query: LoreArticleQuery, signal?: AbortSignal): Promise<LoreArticlePage> {
    const payload: Record<string, unknown> = {
      worldCode: query.worldCode,
      limit: query.limit,
      offset: query.offset,
    };
    if (query.q !== undefined) payload.q = query.q;
    if (query.sectionCode !== undefined) payload.sectionCode = query.sectionCode;
    if (query.keywordIds !== undefined) payload.keywordIds = query.keywordIds;
    if (query.status !== undefined) payload.status = query.status;
    if (query.visibility !== undefined) payload.visibility = query.visibility;
    if (query.sort !== undefined) payload.sort = query.sort;
    if (query.includeArchived !== undefined) payload.includeArchived = query.includeArchived;

    return this.requireData<LoreArticlePage>('lore.findArticlePage', payload, signal);
  }

  async getArticle(worldCode: string, articleCode: string, signal?: AbortSignal): Promise<LoreArticleDetail> {
    return this.requireData<LoreArticleDetail>('lore.getArticle', { worldCode, articleCode }, signal);
  }

  async saveArticle(data: SaveLoreArticleData, signal?: AbortSignal): Promise<LoreArticleDetail> {
    const payload: Record<string, unknown> = { worldCode: data.worldCode, article: data.article };
    if (data.expectedVersion !== undefined) payload.expectedVersion = data.expectedVersion;

    return this.requireData<LoreArticleDetail>('lore.saveArticle', payload, signal);
  }

  async archiveArticle(
    worldCode: string,
    articleCode: string,
    archived: boolean,
    expectedVersion: number,
    signal?: AbortSignal,
  ): Promise<void> {
    await this.requireSuccess('lore.archiveArticle', { worldCode, articleCode, archived, expectedVersion }, signal);
  }

  async findReaderCandidates(query: LoreReaderCandidateQuery, signal?: AbortSignal): Promise<LoreReader[]> {
    const payload: Record<string, unknown> = { worldCode: query.worldCode };
    if (query.q !== undefined) payload.q = query.q;
    if (query.limit !== undefined) payload.limit = query.limit;
    const page = await this.requireData<{ items: LoreReader[] }>('lore.findReaderCandidates', payload, signal);

    return page.items;
  }

  async exportWorld(worldCode: string, signal?: AbortSignal): Promise<LoreFile> {
    return this.requireData<LoreFile>('lore.exportWorld', { worldCode }, signal);
  }

  async importWorld(payload: LoreFile, options: LoreImportOptions, signal?: AbortSignal): Promise<LoreImportReport> {
    const body: Record<string, unknown> = {
      payload,
      mode: options.mode,
      conflictPolicy: options.conflictPolicy,
      dryRun: options.dryRun === true,
    };
    if (options.targetWorldCode !== undefined) body.targetWorldCode = options.targetWorldCode;

    return this.requireData<LoreImportReport>('lore.importWorld', body, signal);
  }

  private async requireData<T>(action: string, payload: unknown, signal?: AbortSignal): Promise<T> {
    const response = await this.engine.runAction<T>(action, payload, signal);
    if (response.error) {
      throw LoreApiError.fromActionError(response.error);
    }

    if (response.data === null || response.data === undefined) {
      throw new LoreApiError('LORE_EMPTY_RESPONSE', 'Сервер не вернул данные кодекса');
    }

    return response.data;
  }

  private async requireSuccess(action: string, payload: unknown, signal?: AbortSignal): Promise<void> {
    const response = await this.engine.runAction(action, payload, signal);
    if (response.error) {
      throw LoreApiError.fromActionError(response.error);
    }
  }
}
