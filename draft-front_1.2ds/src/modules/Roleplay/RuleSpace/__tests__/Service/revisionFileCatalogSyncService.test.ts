import { describe, expect, it, vi } from 'vitest';
import { RevisionFileCatalogSyncService } from '@/modules/Roleplay/RuleSpace/Service/RevisionFileCatalogSyncService';
import type { IKeywordApi } from '@/modules/Roleplay/Rule/Interface/IKeywordApi';
import type { IMechanicApi } from '@/modules/Roleplay/Rule/Interface/IMechanicApi';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { RevisionFile } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFile';
import {
  REVISION_FILE_FORMAT,
  REVISION_FILE_FORMAT_VERSION,
} from '@/modules/Roleplay/RuleSpace/Constant/REVISION_FILE_FORMAT';
import { revisionFileService } from '@/modules/Roleplay/RuleSpace/Service/Instance/revisionFileService';
import { revisionFileImportService } from '@/modules/Roleplay/RuleSpace/Service/Instance/revisionFileImportService';
import { REVISION_FILE_PROBLEM_CODE } from '@/modules/Roleplay/RuleSpace/Constant/REVISION_FILE_PROBLEM_CODE';
import { RevisionFileProblemError } from '@/modules/Roleplay/RuleSpace/Service/RevisionFileProblemError';

const FIXED = 1767225600;

function fileOf(overrides: Partial<RevisionFile> = {}): RevisionFile {
  return {
    format: REVISION_FILE_FORMAT,
    formatVersion: REVISION_FILE_FORMAT_VERSION,
    exportedAt: FIXED,
    source: { spaceCode: 's', spaceName: 'S', revision: 1, publishedAt: FIXED },
    keywords: [{ code: 'k1', name: 'K', description: 'd', active: true }],
    mechanics: [{ code: 'm1', name: 'M', description: 'md', version: '1' }],
    sections: [],
    rules: [
      {
        code: 'r1',
        type: 'simple',
        name: 'R',
        description: '',
        spec: {},
        keywordCodes: ['k1'],
        mechanic: { code: 'm1', version: '1' },
        mechanicPayload: [],
        contentStatus: 'needs_work',
        active: true,
      },
    ],
    ...overrides,
  };
}

describe('RevisionFileCatalogSyncService', () => {
  it('нет keyword → create; повтор без изменений не update', async () => {
    const created: Keyword[] = [];
    const keywords: Keyword[] = [];
    const mechanics: Mechanic[] = [];
    const createKeyword = vi.fn(async (data) => {
      const row: Keyword = {
        id: 9,
        code: data.code,
        name: data.name,
        description: data.description ?? '',
        active: true,
      };
      keywords.push(row);
      created.push(row);

      return row;
    });
    const updateKeyword = vi.fn();
    const service = new RevisionFileCatalogSyncService(
      () =>
        ({
          createKeyword,
          updateKeyword,
          deactivate: vi.fn(),
          getKeywords: async () => keywords,
          getKeyword: async () => keywords[0],
        }) as IKeywordApi,
      () =>
        ({
          getMechanics: async () => mechanics,
          getMechanic: vi.fn(),
          createMechanic: vi.fn(),
          updateMechanic: vi.fn(),
        }) as IMechanicApi,
    );
    const snapshot = fileOf({ mechanics: [] });
    snapshot.rules[0].mechanic = null;
    const first = service.plan(snapshot, keywords, []);
    expect(first.keywordCreates.map((item) => item.code)).toEqual(['k1']);
    await service.apply(first);
    const second = service.plan(snapshot, keywords, []);
    expect(second.keywordCreates).toEqual([]);
    expect(second.keywordUpdates).toEqual([]);
    expect(service.isDirty(second)).toBe(false);
    expect(updateKeyword).not.toHaveBeenCalled();
  });

  it('другое имя → update, не create', () => {
    const live: Keyword[] = [{ id: 1, code: 'k1', name: 'Старое', description: 'd', active: true }];
    const service = new RevisionFileCatalogSyncService(
      () => ({}) as IKeywordApi,
      () => ({}) as IMechanicApi,
    );
    const plan = service.plan(fileOf({ mechanics: [] }), live, []);
    expect(plan.keywordCreates).toEqual([]);
    expect(plan.keywordUpdates).toEqual([{ id: 1, name: 'K', description: 'd' }]);
  });

  it('live inactive + файл active → cannotReactivate, overlay идёт', () => {
    const liveKw: Keyword[] = [{ id: 1, code: 'k1', name: 'K', description: 'd', active: false }];
    const liveMech: Mechanic[] = [{ id: 2, code: 'm1', name: 'M', description: 'md', version: '1' }];
    const service = new RevisionFileCatalogSyncService(
      () => ({}) as IKeywordApi,
      () => ({}) as IMechanicApi,
    );
    const parsed = fileOf();
    const plan = service.plan(parsed, liveKw, liveMech);
    expect(plan.cannotReactivate).toEqual(['k1']);
    expect(plan.keywordDeactivates).toEqual([]);
    const merged = service.merge(parsed, liveKw, liveMech);
    const preview = revisionFileImportService.prepare(parsed, merged, {
      spaceId: 1,
      latest: revisionFileImportService.emptySlice(),
      removeMissing: false,
      draftRules: [],
      draftRemovedCodes: [],
      draftSections: null,
    });
    expect(preview.diff.added.map((rule) => rule.code)).toEqual(['r1']);
    expect(preview.rules[0]?.keywordIds).toEqual([1]);
  });

  it('файл active false + live true → deactivate', () => {
    const live: Keyword[] = [{ id: 3, code: 'k1', name: 'K', description: 'd', active: true }];
    const service = new RevisionFileCatalogSyncService(
      () => ({}) as IKeywordApi,
      () => ({}) as IMechanicApi,
    );
    const plan = service.plan(
      fileOf({ keywords: [{ code: 'k1', name: 'K', description: 'd', active: false }], mechanics: [] }),
      live,
      [],
    );
    expect(plan.keywordDeactivates).toEqual([3]);
  });

  it('нет mechanic version → create; совпало → нет HTTP', async () => {
    const mechanics: Mechanic[] = [];
    const createMechanic = vi.fn(async (data) => {
      const row: Mechanic = {
        id: 4,
        code: data.code,
        name: data.name,
        version: data.version,
        description: data.description ?? '',
      };
      mechanics.push(row);

      return row;
    });
    const updateMechanic = vi.fn();
    const service = new RevisionFileCatalogSyncService(
      () => ({}) as IKeywordApi,
      () =>
        ({
          getMechanics: async () => mechanics,
          getMechanic: vi.fn(),
          createMechanic,
          updateMechanic,
        }) as IMechanicApi,
    );
    const snapshot = fileOf({ keywords: [] });
    snapshot.rules[0].keywordCodes = [];
    const first = service.plan(snapshot, [], mechanics);
    expect(first.mechanicCreates).toHaveLength(1);
    await service.apply(first);
    expect(createMechanic).toHaveBeenCalledOnce();
    const second = service.plan(snapshot, [], mechanics);
    expect(service.isDirty(second)).toBe(false);
    expect(updateMechanic).not.toHaveBeenCalled();
  });

  it('ссылка без снимка → UNRESOLVED, не выдумывать keyword', () => {
    const parsed = fileOf({ keywords: [] });
    expect(() => revisionFileService.materializeRules(parsed, 1, [], [])).toThrow(RevisionFileProblemError);
    try {
      revisionFileService.materializeRules(parsed, 1, [], []);
    } catch (error) {
      expect(RevisionFileProblemError.is(error)).toBe(true);
      if (RevisionFileProblemError.is(error)) expect(error.code).toBe(REVISION_FILE_PROBLEM_CODE.unresolved);
    }
  });

  it('apply create даёт id, повторный prepare кладёт его в правило', async () => {
    const keywords: Keyword[] = [];
    const createKeyword = vi.fn(async (data) => {
      const row: Keyword = {
        id: 77,
        code: data.code,
        name: data.name,
        description: data.description ?? '',
        active: true,
      };
      keywords.push(row);

      return row;
    });
    const service = new RevisionFileCatalogSyncService(
      () =>
        ({
          createKeyword,
          updateKeyword: vi.fn(),
          deactivate: vi.fn(),
          getKeywords: async () => keywords,
          getKeyword: async () => keywords[0],
        }) as IKeywordApi,
      () =>
        ({
          getMechanics: async () => [],
          getMechanic: vi.fn(),
          createMechanic: vi.fn(),
          updateMechanic: vi.fn(),
        }) as IMechanicApi,
    );
    const snapshot = fileOf({ mechanics: [] });
    snapshot.rules[0].mechanic = null;
    const merged = service.merge(snapshot, [], []);
    expect(merged.keywords[0]?.id).toBeLessThan(0);
    await service.apply(service.plan(snapshot, [], []));
    const preview = revisionFileImportService.prepare(
      snapshot,
      { keywords, mechanics: [] },
      {
        spaceId: 1,
        latest: revisionFileImportService.emptySlice(),
        removeMissing: false,
        draftRules: [],
        draftRemovedCodes: [],
        draftSections: null,
      },
    );
    expect(preview.rules[0]?.keywordIds).toEqual([77]);
  });

  it('throw create → apply бросает', async () => {
    const service = new RevisionFileCatalogSyncService(
      () =>
        ({
          createKeyword: async () => {
            throw new Error('нет права');
          },
          updateKeyword: vi.fn(),
          deactivate: vi.fn(),
          getKeywords: async () => [],
          getKeyword: async () => {
            throw new Error('x');
          },
        }) as IKeywordApi,
      () =>
        ({
          getMechanics: async () => [],
          getMechanic: vi.fn(),
          createMechanic: vi.fn(),
          updateMechanic: vi.fn(),
        }) as IMechanicApi,
    );
    await expect(service.apply(service.plan(fileOf({ mechanics: [] }), [], []))).rejects.toThrow('нет права');
  });
});
