import { describe, expect, it } from 'vitest';
import { reactive } from 'vue';
import { revisionFileService } from '@/modules/Roleplay/RuleSpace/Service/Instance/revisionFileService';
import { RevisionFileService } from '@/modules/Roleplay/RuleSpace/Service/RevisionFileService';
import { RevisionFileProblemError } from '@/modules/Roleplay/RuleSpace/Service/RevisionFileProblemError';
import { generateRevisionRules } from '@/modules/Roleplay/RuleSpace/Mock/mockSpaces';
import { keywords as mockKeywords } from '@/modules/Roleplay/Rule/Mock/mockKeywords';
import { fetchMechanics } from '@/modules/Roleplay/Rule/Mock/mockMechanics';
import {
  REVISION_FILE_FORMAT,
  REVISION_FILE_FORMAT_VERSION,
} from '@/modules/Roleplay/RuleSpace/Constant/REVISION_FILE_FORMAT';
import { REVISION_FILE_PROBLEM_CODE } from '@/modules/Roleplay/RuleSpace/Constant/REVISION_FILE_PROBLEM_CODE';
import { ruleDiffService } from '@/modules/Roleplay/Rule/init';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RevisionFile } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFile';
import type { SpaceRevision } from '@/modules/Roleplay/RuleSpace/Dto/SpaceRevision';

const FIXED_UNIX = 1767225600;

function expectProblem(run: () => unknown, code: string, path: string, stage: 'syntax' | 'format'): void {
  try {
    run();
    throw new Error('ожидали ошибку файла ревизии');
  } catch (error) {
    expect(RevisionFileProblemError.is(error)).toBe(true);
    if (!RevisionFileProblemError.is(error)) return;
    expect(error.code).toBe(code);
    expect(error.path).toBe(path);
    expect(error.stage).toBe(stage);
  }
}

function sampleFile(overrides: Partial<RevisionFile> = {}): RevisionFile {
  return {
    format: REVISION_FILE_FORMAT,
    formatVersion: REVISION_FILE_FORMAT_VERSION,
    exportedAt: FIXED_UNIX,
    source: { spaceCode: 'src', spaceName: 'Источник', revision: 3, publishedAt: FIXED_UNIX },
    keywords: [{ code: 'common', name: 'Общая', description: '', active: true }],
    mechanics: [],
    sections: [],
    rules: [
      {
        code: 'a',
        type: 'simple',
        name: 'А',
        description: 'Описание А',
        spec: { extra: true },
        keywordCodes: ['common'],
        mechanic: null,
        mechanicPayload: [],
        contentStatus: 'needs_work',
        active: true,
      },
    ],
    ...overrides,
  };
}

function rule(code: string, name: string, overrides: Partial<Rule> = {}): Rule {
  return {
    id: null,
    code,
    type: 'simple',
    name,
    description: `Описание ${name}`,
    spaceId: 1,
    createdAt: FIXED_UNIX,
    ...overrides,
  };
}

function collectStrings(value: unknown, acc: string[]): void {
  if (typeof value === 'string') acc.push(value);
  else if (Array.isArray(value)) value.forEach((item) => collectStrings(item, acc));
  else if (typeof value === 'object' && value !== null) {
    Object.values(value).forEach((item) => collectStrings(item, acc));
  }
}

describe('RevisionFileService', () => {
  it('parse отклоняет мусор, чужой format и legacy version', () => {
    expectProblem(() => revisionFileService.parse('{'), REVISION_FILE_PROBLEM_CODE.notJson, '', 'syntax');
    expectProblem(
      () => revisionFileService.parse(JSON.stringify({ format: 'other', formatVersion: 3 })),
      REVISION_FILE_PROBLEM_CODE.format,
      '/format',
      'format',
    );
    expectProblem(
      () =>
        revisionFileService.parse(
          JSON.stringify({
            format: REVISION_FILE_FORMAT,
            formatVersion: 2,
            exportedAt: FIXED_UNIX,
            source: { spaceCode: 'a', spaceName: 'A', revision: 1, publishedAt: 1 },
            keywords: [],
            mechanics: [],
            sections: [],
            rules: [sampleFile().rules[0]],
          }),
        ),
      REVISION_FILE_PROBLEM_CODE.unsupportedVersion,
      '/formatVersion',
      'format',
    );
  });

  it('serialize/parse круг без внутренних id', () => {
    const file = revisionFileService.serialize(sampleFile());
    const parsed = revisionFileService.parse(JSON.stringify(file));
    expect(parsed.rules).toHaveLength(1);
    expect(parsed.exportedAt).toBe(FIXED_UNIX);
    expect(parsed.rules[0]?.spec).toEqual({ extra: true });
    const raw = JSON.parse(JSON.stringify(file)) as { rules: Record<string, unknown>[] };
    expect(raw.rules[0]?.id).toBeUndefined();
    expect(raw.rules[0]?.spaceId).toBeUndefined();
    expect(raw.rules[0]?.keywordIds).toBeUndefined();
    expect(raw.rules[0]?.mechanicId).toBeUndefined();
    expect(raw.rules[0]?.createdAt).toBeUndefined();
  });

  it('запрещает keywordIds и handlerVersion', () => {
    const withIds = sampleFile();
    expectProblem(
      () =>
        revisionFileService.parse(
          JSON.stringify({
            ...withIds,
            rules: [{ ...withIds.rules[0], keywordIds: [20] }],
          }),
        ),
      REVISION_FILE_PROBLEM_CODE.forbiddenField,
      '/rules/0/keywordIds',
      'format',
    );
    expectProblem(
      () =>
        revisionFileService.parse(
          JSON.stringify({
            ...withIds,
            rules: [{ ...withIds.rules[0], mechanic: { code: 'roll', version: '1', handlerVersion: '1' } }],
          }),
        ),
      REVISION_FILE_PROBLEM_CODE.forbiddenField,
      '/rules/0/mechanic/handlerVersion',
      'format',
    );
  });

  it('mechanic null и непустой payload, дубль code, extra корень, пустой rules', () => {
    const base = sampleFile();
    expectProblem(
      () =>
        revisionFileService.parse(
          JSON.stringify({
            ...base,
            rules: [{ ...base.rules[0], mechanic: null, mechanicPayload: { type: 'roll' } }],
          }),
        ),
      REVISION_FILE_PROBLEM_CODE.format,
      '/rules/0/mechanicPayload',
      'format',
    );
    expectProblem(
      () =>
        revisionFileService.parse(
          JSON.stringify({
            ...base,
            rules: [base.rules[0], { ...base.rules[0] }],
          }),
        ),
      REVISION_FILE_PROBLEM_CODE.duplicate,
      '/rules/1/code',
      'format',
    );
    expectProblem(
      () => revisionFileService.parse(JSON.stringify({ ...base, extra: true })),
      REVISION_FILE_PROBLEM_CODE.forbiddenField,
      '/extra',
      'format',
    );
    expectProblem(
      () => revisionFileService.parse(JSON.stringify({ ...base, rules: [] })),
      REVISION_FILE_PROBLEM_CODE.format,
      '/rules',
      'format',
    );
  });

  it('assemble резолвит id в коды и сортирует; нет в карте — unresolved', () => {
    const service = new RevisionFileService(ruleDiffService, () => FIXED_UNIX);
    const keywords: Keyword[] = [
      { id: 20, code: 'z-tag', name: 'Z', description: '', active: true },
      { id: 7, code: 'a-tag', name: 'A', description: '', active: false },
    ];
    const mechanics: Mechanic[] = [
      { id: 4, code: 'purchase_surcharge', name: 'Доплата', description: '', version: '1.0.0' },
    ];
    const revision: SpaceRevision<Rule> = {
      revision: 2,
      publishedAt: FIXED_UNIX,
      spaceCode: 'world',
      spaceName: 'Мир',
      sections: [
        { code: 'b', name: 'B', parentCode: null, sortOrder: 1 },
        { code: 'a', name: 'A', parentCode: null, sortOrder: 1 },
      ],
      rules: [
        rule('beta', 'Бета', { keywordIds: [20], id: 9, spaceId: 3 }),
        rule('alpha', 'Альфа', {
          keywordIds: [7, 20],
          mechanicId: 4,
          mechanicPayload: { type: 'purchase_surcharge', filter: {}, free_count: 2, surcharge: 1 },
        }),
      ],
    };
    const file = service.assemble(revision, keywords, mechanics);
    expect(file.formatVersion).toBe(3);
    expect(file.exportedAt).toBe(FIXED_UNIX);
    expect(file.rules.map((item) => item.code)).toEqual(['alpha', 'beta']);
    expect(file.rules[0]?.keywordCodes).toEqual(['a-tag', 'z-tag']);
    expect(file.rules[0]?.mechanic).toEqual({ code: 'purchase_surcharge', version: '1.0.0' });
    expect(file.keywords.map((item) => item.code)).toEqual(['a-tag', 'z-tag']);
    expect(file.sections.map((item) => item.code)).toEqual(['a', 'b']);
    const dumped = JSON.stringify(file);
    expect(dumped).not.toContain('"keywordIds"');
    expect(dumped).not.toContain('"mechanicId"');

    const back = service.materializeRules(file, 8, keywords, mechanics);
    expect(back[0]?.keywordIds).toEqual([7, 20]);
    expect(back[0]?.mechanicId).toBe(4);
    expect(back[0]?.spaceId).toBe(8);
    expect(back[0]?.id).toBeNull();

    expectProblem(
      () => service.assemble({ ...revision, rules: [rule('x', 'X', { keywordIds: [99] })] }, keywords, mechanics),
      REVISION_FILE_PROBLEM_CODE.unresolved,
      '/rules/0',
      'format',
    );
  });

  it('assemble включает tombstone и только used-снимки', () => {
    const service = new RevisionFileService(ruleDiffService, () => FIXED_UNIX);
    const keywords: Keyword[] = [
      { id: 1, code: 'used', name: 'Used', description: '', active: false },
      { id: 2, code: 'spare', name: 'Spare', description: '', active: true },
    ];
    const mechanics: Mechanic[] = [
      { id: 8, code: 'roll', name: 'Roll', description: '', version: '1.0.0' },
      { id: 9, code: 'other', name: 'Other', description: '', version: '1.0.0' },
    ];
    const file = service.assemble(
      {
        revision: 4,
        publishedAt: FIXED_UNIX,
        spaceCode: 'src',
        spaceName: 'Источник',
        sections: [{ code: 'root', name: 'Корень', parentCode: null, sortOrder: 0 }],
        rules: [
          rule('gone', 'Удалено', {
            id: 15,
            spaceId: 4,
            active: false,
            keywordIds: [1],
            catalogSection: 'root',
            catalogSortOrder: 2,
          }),
          rule('live', 'Живое', { mechanicId: 8, mechanicPayload: null }),
        ],
      },
      keywords,
      mechanics,
    );
    expect(file.rules.find((item) => item.code === 'gone')?.active).toBe(false);
    expect(file.keywords.map((item) => item.code)).toEqual(['used']);
    expect(file.keywords[0]?.active).toBe(false);
    expect(file.mechanics.map((item) => item.code)).toEqual(['roll']);
    expect(file.sections).toHaveLength(1);
    expect(Object.keys(file.rules[0] ?? {})).not.toContain('id');
    expect(Object.keys(file.keywords[0] ?? {})).not.toContain('id');
  });

  it('assemble мок-среза без внутренних ключей проходит round-trip', async () => {
    const service = new RevisionFileService(ruleDiffService, () => FIXED_UNIX);
    const mechanics = await fetchMechanics();
    const file = service.assemble(
      {
        revision: 12,
        publishedAt: FIXED_UNIX,
        spaceCode: 'actual',
        spaceName: 'Актуальные',
        sections: [],
        rules: generateRevisionRules(2, 12),
      },
      mockKeywords,
      mechanics,
    );
    const dumped = JSON.stringify(file);
    expect(dumped).not.toContain('"keywordIds"');
    expect(dumped).not.toContain('"mechanicId"');
    expect(dumped).not.toContain('"spaceId"');
    const parsed = service.parse(dumped);
    expect(parsed.rules.map((item) => item.code)).toEqual(file.rules.map((item) => item.code));
    for (const item of parsed.rules) {
      for (const keywordCode of item.keywordCodes) {
        expect(parsed.keywords.some((keyword) => keyword.code === keywordCode)).toBe(true);
      }
      if (item.mechanic) {
        expect(
          parsed.mechanics.some(
            (mechanic) => mechanic.code === item.mechanic?.code && mechanic.version === item.mechanic.version,
          ),
        ).toBe(true);
      }
    }
  });

  it('diff пропускает равный payload с другими id', () => {
    const published = [rule('a', 'А', { id: 2, spaceId: 2 })];
    const fileRules = [rule('a', 'А', { id: 9, spaceId: 9 }), rule('b', 'Б')];
    const diff = revisionFileService.diffAgainstPublished(fileRules, published, 2, {
      removeMissing: false,
      existingRemovedCodes: [],
    });
    expect(diff.unchangedCount).toBe(1);
    expect(diff.added.map((item) => item.code)).toEqual(['b']);
    expect(diff.changed).toEqual([]);
  });

  it('removeMissing ставит к удалению только code, которых нет в файле', () => {
    const published = [rule('keep', 'Оставить'), rule('gone', 'Убрать')];
    const fileRules = [rule('keep', 'Оставить')];
    const diff = revisionFileService.diffAgainstPublished(fileRules, published, 1, {
      removeMissing: true,
      existingRemovedCodes: [],
    });
    expect(diff.removedCodes).toEqual(['gone']);
    expect(diff.unchangedCount).toBe(1);
  });

  it('tombstone active: false против published active — changed, не removed', () => {
    const published = [rule('a', 'А', { active: true })];
    const fileRules = [rule('a', 'А', { active: false })];
    const diff = revisionFileService.diffAgainstPublished(fileRules, published, 1, {
      removeMissing: true,
      existingRemovedCodes: [],
    });
    expect(diff.changed.map((item) => item.code)).toEqual(['a']);
    expect(diff.removedCodes).toEqual([]);
  });

  it('diff клонирует Vue-прокси правил без structuredClone-ошибки', () => {
    const published = [rule('keep', 'Оставить')];
    const fileRules = reactive([rule('keep', 'Оставить', { description: 'иначе' }), rule('new', 'Новое')]);
    expect(() =>
      revisionFileService.diffAgainstPublished(fileRules, published, 1, {
        removeMissing: false,
        existingRemovedCodes: [],
      }),
    ).not.toThrow();
  });
});

describe('срез ревизии: spec ссылается по code, не по id', () => {
  it('ни одно spec не содержит id другого правила среза', () => {
    const rules = generateRevisionRules(2, 12);
    const idTexts = new Set(rules.map((item) => (item.id == null ? '' : String(item.id))).filter(Boolean));
    for (const item of rules) {
      const strings: string[] = [];
      collectStrings(item.spec, strings);
      collectStrings(item.mechanicPayload, strings);
      for (const text of strings) {
        if (idTexts.has(text) && text !== String(item.id)) {
          throw new Error(`${item.code} ссылается на id ${text}`);
        }
      }
    }
  });
});
