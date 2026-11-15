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

/**
 * Контракт транспорта кодекса `lore.*`.
 *
 * Импорт и экспорт мира — серверные действия: файл собирает и разбирает сервер,
 * импорт атомарен.
 */
export interface ILoreApi {
  findWorldPage(query: LoreWorldQuery, signal?: AbortSignal): Promise<LoreWorldPage>;
  getWorld(code: string, signal?: AbortSignal): Promise<LoreWorldDetail>;
  saveWorld(data: SaveLoreWorldData, signal?: AbortSignal): Promise<LoreWorld>;
  archiveWorld(code: string, archived: boolean, expectedVersion: number, signal?: AbortSignal): Promise<LoreWorld>;
  saveTree(
    code: string,
    sections: LoreSectionInput[],
    expectedWorldVersion: number,
    signal?: AbortSignal,
  ): Promise<LoreWorldDetail>;
  findArticlePage(query: LoreArticleQuery, signal?: AbortSignal): Promise<LoreArticlePage>;
  getArticle(worldCode: string, articleCode: string, signal?: AbortSignal): Promise<LoreArticleDetail>;
  saveArticle(data: SaveLoreArticleData, signal?: AbortSignal): Promise<LoreArticleDetail>;
  archiveArticle(
    worldCode: string,
    articleCode: string,
    archived: boolean,
    expectedVersion: number,
    signal?: AbortSignal,
  ): Promise<void>;
  findReaderCandidates(query: LoreReaderCandidateQuery, signal?: AbortSignal): Promise<LoreReader[]>;
  exportWorld(worldCode: string, signal?: AbortSignal): Promise<LoreFile>;
  importWorld(payload: LoreFile, options: LoreImportOptions, signal?: AbortSignal): Promise<LoreImportReport>;
}
