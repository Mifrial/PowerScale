import { describe, expect, it } from 'vitest';
import { RevisionFileImportService } from '@/modules/Roleplay/RuleSpace/Service/RevisionFileImportService';
import { revisionFileService } from '@/modules/Roleplay/RuleSpace/Service/Instance/revisionFileService';
import { abilitySectionTreeService } from '@/modules/Roleplay/RuleSpace/Service/Instance/abilitySectionTreeService';
import {
  REVISION_FILE_FORMAT,
  REVISION_FILE_FORMAT_VERSION,
} from '@/modules/Roleplay/RuleSpace/Constant/REVISION_FILE_FORMAT';
import { REVISION_FILE_PROBLEM_CODE } from '@/modules/Roleplay/RuleSpace/Constant/REVISION_FILE_PROBLEM_CODE';
import { RevisionFileProblemError } from '@/modules/Roleplay/RuleSpace/Service/RevisionFileProblemError';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RevisionFile } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFile';
import type { SpaceRevision } from '@/modules/Roleplay/RuleSpace/Dto/SpaceRevision';
import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import type { RuleExternal } from '@/modules/Roleplay/RuleSpace/Dto/RuleExternal';

const FIXED_UNIX = 1767225600;

const keywords: Keyword[] = [{ id: 1, code: 'common', name: 'Общая', description: '', active: true }];

function external(code: string, overrides: Partial<RuleExternal> = {}): RuleExternal {
  return {
    code,
    type: 'simple',
    name: code,
    description: `Описание ${code}`,
    spec: {},
    keywordCodes: [],
    mechanic: null,
    mechanicPayload: [],
    contentStatus: 'needs_work',
    contentNote: '',
    active: true,
    ...overrides,
  };
}

function sampleFile(overrides: Partial<RevisionFile> = {}): RevisionFile {
  return {
    format: REVISION_FILE_FORMAT,
    formatVersion: REVISION_FILE_FORMAT_VERSION,
    exportedAt: FIXED_UNIX,
    source: { spaceCode: 'src', spaceName: 'Источник', revision: 3, publishedAt: FIXED_UNIX },
    keywords: [],
    mechanics: [],
    sections: [],
    rules: [external('a')],
    ...overrides,
  };
}

function publishedRule(code: string, overrides: Partial<Rule> = {}): Rule {
  return {
    id: 10,
    code,
    type: 'simple',
    name: code,
    description: `Описание ${code}`,
    spaceId: 1,
    createdAt: FIXED_UNIX,
    active: true,
    contentStatus: 'needs_work',
    ...overrides,
  };
}

function latestSlice(rules: Rule[], sections: AbilitySection[] = []): SpaceRevision<Rule> {
  return {
    revision: 4,
    publishedAt: FIXED_UNIX,
    spaceCode: 'dst',
    spaceName: 'Цель',
    rules,
    sections,
  };
}

function makeService(): RevisionFileImportService {
  return new RevisionFileImportService(
    revisionFileService,
    abilitySectionTreeService,
    async () => [{ revision: 4, publishedAt: FIXED_UNIX, ruleCount: 1 }],
    async () => latestSlice([publishedRule('old')]),
    (type) => type === 'simple' || type === 'ability',
    (status) => status === 'needs_work' || status === 'ready',
  );
}

describe('RevisionFileImportService', () => {
  const service = makeService();
  const catalogs = { keywords, mechanics: [] };

  it('loadLatest берёт последнюю мету, не «текущий» номер', async () => {
    const loaded = await service.loadLatest(1);
    expect(loaded.rules.map((rule) => rule.code)).toEqual(['old']);
    expect(loaded.revision).toBe(4);
  });

  it('файл = latest → empty overlay', () => {
    const latest = latestSlice([publishedRule('a')]);
    const preview = service.prepare(sampleFile(), catalogs, {
      spaceId: 1,
      latest,
      removeMissing: false,
      draftRules: [],
      draftRemovedCodes: [],
      draftSections: null,
    });
    expect(revisionFileService.isEmptyDiff(preview.diff)).toBe(true);
    expect(preview.hasTargetDraft).toBe(false);
    expect(service.planApply(preview, 'prefer_file').isNoOp).toBe(true);
  });

  it('tombstone в файле → changed, не removedCodes', () => {
    const latest = latestSlice([publishedRule('a', { active: true })]);
    const preview = service.prepare(sampleFile({ rules: [external('a', { active: false })] }), catalogs, {
      spaceId: 1,
      latest,
      removeMissing: true,
      draftRules: [],
      draftRemovedCodes: [],
      draftSections: null,
    });
    expect(preview.diff.changed.map((rule) => rule.code)).toEqual(['a']);
    expect(preview.diff.changed[0]?.active).toBe(false);
    expect(preview.diff.removedCodes).toEqual([]);
  });

  it('removeMissing: нет в файле → removedCodes; tombstone в файле — нет', () => {
    const latest = latestSlice([publishedRule('keep'), publishedRule('gone')]);
    const withGone = service.prepare(
      sampleFile({ rules: [external('keep'), external('gone', { active: false })] }),
      catalogs,
      {
        spaceId: 1,
        latest,
        removeMissing: true,
        draftRules: [],
        draftRemovedCodes: [],
        draftSections: null,
      },
    );
    expect(withGone.diff.removedCodes).toEqual([]);
    const missing = service.prepare(sampleFile({ rules: [external('keep')] }), catalogs, {
      spaceId: 1,
      latest,
      removeMissing: true,
      draftRules: [],
      draftRemovedCodes: [],
      draftSections: null,
    });
    expect(missing.diff.removedCodes).toEqual(['gone']);
  });

  it('только секции отличаются → не empty, plan save секций без saveRules', () => {
    const sections: AbilitySection[] = [{ code: 'root', name: 'Корень', parentCode: null, sortOrder: 0 }];
    const latest = latestSlice([publishedRule('a')], []);
    const preview = service.prepare(sampleFile({ sections }), catalogs, {
      spaceId: 1,
      latest,
      removeMissing: false,
      draftRules: [],
      draftRemovedCodes: [],
      draftSections: null,
    });
    expect(preview.diff.sectionsDiffer).toBe(true);
    expect(revisionFileService.isEmptyDiff(preview.diff)).toBe(false);
    const plan = service.planApply(preview, 'prefer_file');
    expect(plan.saveRules).toEqual([]);
    expect(plan.sectionAction).toBe('save');
    expect(plan.isNoOp).toBe(false);
  });

  it('цикл секций — ошибка format', () => {
    const sections: AbilitySection[] = [
      { code: 'a', name: 'A', parentCode: 'b', sortOrder: 0 },
      { code: 'b', name: 'B', parentCode: 'a', sortOrder: 1 },
    ];
    expect(() =>
      service.prepare(sampleFile({ sections }), catalogs, {
        spaceId: 1,
        latest: latestSlice([publishedRule('a')]),
        removeMissing: false,
        draftRules: [],
        draftRemovedCodes: [],
        draftSections: null,
      }),
    ).toThrow(RevisionFileProblemError);
    try {
      service.prepare(sampleFile({ sections }), catalogs, {
        spaceId: 1,
        latest: latestSlice([publishedRule('a')]),
        removeMissing: false,
        draftRules: [],
        draftRemovedCodes: [],
        draftSections: null,
      });
    } catch (error) {
      expect(RevisionFileProblemError.is(error)).toBe(true);
      if (RevisionFileProblemError.is(error)) {
        expect(error.code).toBe(REVISION_FILE_PROBLEM_CODE.format);
        expect(error.path).toBe('/sections');
      }
    }
  });

  it('placement на чужой code секции — ошибка', () => {
    expect(() =>
      service.prepare(sampleFile({ rules: [external('a', { catalogSection: 'nope' })] }), catalogs, {
        spaceId: 1,
        latest: latestSlice([publishedRule('a')]),
        removeMissing: false,
        draftRules: [],
        draftRemovedCodes: [],
        draftSections: null,
      }),
    ).toThrow(RevisionFileProblemError);
  });

  it('неизвестный type — warning, правило в added', () => {
    const preview = service.prepare(sampleFile({ rules: [external('x', { type: 'spell' })] }), catalogs, {
      spaceId: 1,
      latest: latestSlice([]),
      removeMissing: false,
      draftRules: [],
      draftRemovedCodes: [],
      draftSections: null,
    });
    expect(preview.warnings.some((item) => item.message.includes('spell'))).toBe(true);
    expect(preview.diff.added.map((rule) => rule.code)).toEqual(['x']);
  });

  it('нет keyword в list — UNRESOLVED', () => {
    expect(() =>
      service.prepare(sampleFile({ rules: [external('a', { keywordCodes: ['missing'] })] }), catalogs, {
        spaceId: 1,
        latest: latestSlice([]),
        removeMissing: false,
        draftRules: [],
        draftRemovedCodes: [],
        draftSections: null,
      }),
    ).toThrow(RevisionFileProblemError);
  });

  it('приоритет черновика: occupied changed не в saveRules, незанятый added — да', () => {
    const latest = latestSlice([publishedRule('a', { name: 'Старое' })]);
    const preview = service.prepare(
      sampleFile({
        rules: [external('a', { name: 'Из файла' }), external('b')],
      }),
      catalogs,
      {
        spaceId: 1,
        latest,
        removeMissing: false,
        draftRules: [publishedRule('a', { name: 'Локально', id: null })],
        draftRemovedCodes: [],
        draftSections: null,
      },
    );
    expect(preview.hasTargetDraft).toBe(true);
    const plan = service.planApply(preview, 'prefer_draft');
    expect(plan.saveRules.map((rule) => rule.code)).toEqual(['b']);
  });

  it('приоритет выгрузки: unchanged occupied → clearDraftCodes; секции = latest → discard', () => {
    const latest = latestSlice([publishedRule('a')], []);
    const preview = service.prepare(sampleFile(), catalogs, {
      spaceId: 1,
      latest,
      removeMissing: false,
      draftRules: [publishedRule('a', { name: 'Локально', id: null })],
      draftRemovedCodes: [],
      draftSections: [{ code: 'local', name: 'Локально', parentCode: null, sortOrder: 0 }],
    });
    const plan = service.planApply(preview, 'prefer_file');
    expect(plan.saveRules).toEqual([]);
    expect(plan.clearDraftCodes).toEqual(['a']);
    expect(plan.sectionAction).toBe('discard');
    expect(plan.isNoOp).toBe(false);
  });
});
