import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { MechanicPayload } from '@/modules/Roleplay/Rule/Dto/MechanicPayload';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { RuleType } from '@/modules/Roleplay/Rule/Enum/RuleType';
import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import type { RevisionFile } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFile';
import type { RevisionFileImportDiff } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileImportDiff';
import type { RevisionFileKeyword } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileKeyword';
import type { RevisionFileMechanic } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileMechanic';
import type { RevisionFileMechanicRef } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileMechanicRef';
import type { RevisionFileProblem } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileProblem';
import type { RevisionFileSource } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileSource';
import type { RuleExternal } from '@/modules/Roleplay/RuleSpace/Dto/RuleExternal';
import type { SpaceRevision } from '@/modules/Roleplay/RuleSpace/Dto/SpaceRevision';
import { REVISION_FILE_FORBIDDEN_RULE_KEYS } from '@/modules/Roleplay/RuleSpace/Constant/REVISION_FILE_FORBIDDEN_RULE_KEYS';
import {
  REVISION_FILE_FORMAT,
  REVISION_FILE_FORMAT_VERSION,
} from '@/modules/Roleplay/RuleSpace/Constant/REVISION_FILE_FORMAT';
import { REVISION_FILE_PROBLEM_CODE } from '@/modules/Roleplay/RuleSpace/Constant/REVISION_FILE_PROBLEM_CODE';
import { REVISION_FILE_ROOT_KEYS } from '@/modules/Roleplay/RuleSpace/Constant/REVISION_FILE_ROOT_KEYS';
import { REVISION_FILE_RULE_KEYS } from '@/modules/Roleplay/RuleSpace/Constant/REVISION_FILE_RULE_KEYS';
import { RevisionFileProblemError } from '@/modules/Roleplay/RuleSpace/Service/RevisionFileProblemError';
import type { ruleDiffService } from '@/modules/Roleplay/Rule/init';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';

const ROOT_KEY_SET = new Set<string>(REVISION_FILE_ROOT_KEYS);
const RULE_KEY_SET = new Set<string>(REVISION_FILE_RULE_KEYS);
const FORBIDDEN_RULE_KEY_SET = new Set<string>(REVISION_FILE_FORBIDDEN_RULE_KEYS);
const SOURCE_KEYS = new Set(['spaceCode', 'spaceName', 'revision', 'publishedAt']);
const KEYWORD_KEYS = new Set(['code', 'name', 'description', 'active']);
const MECHANIC_KEYS = new Set(['code', 'name', 'description', 'version']);
const MECHANIC_REF_KEYS = new Set(['code', 'version']);
const SECTION_KEYS = new Set(['code', 'name', 'parentCode', 'sortOrder', 'catalogRootFor']);

/**
 * Файл ревизии v3: внешние коды, без внутренних id.
 */
export class RevisionFileService {
  constructor(
    private readonly ruleDiff: typeof ruleDiffService,
    private readonly nowUnix: () => number,
  ) {}

  assemble(revision: SpaceRevision<Rule>, keywords: readonly Keyword[], mechanics: readonly Mechanic[]): RevisionFile {
    const keywordsById = new Map(keywords.map((item) => [item.id, item]));
    const mechanicsById = new Map(mechanics.map((item) => [item.id, item]));
    const usedKeywords = new Map<string, RevisionFileKeyword>();
    const usedMechanics = new Map<string, RevisionFileMechanic>();
    const rules: RuleExternal[] = [];

    for (let index = 0; index < revision.rules.length; index += 1) {
      const rule = revision.rules[index];
      const path = `/rules/${index}`;
      const keywordCodes: string[] = [];
      for (const keywordId of rule.keywordIds ?? []) {
        const keyword = keywordsById.get(keywordId);
        if (!keyword) {
          this.fail(
            REVISION_FILE_PROBLEM_CODE.unresolved,
            path,
            `Нет признака id=${keywordId} для правила ${rule.code}`,
          );
        }
        keywordCodes.push(keyword.code);
        usedKeywords.set(keyword.code, {
          code: keyword.code,
          name: keyword.name,
          description: keyword.description,
          active: keyword.active,
        });
      }
      let mechanic: RevisionFileMechanicRef | null = null;
      if (rule.mechanicId != null) {
        const row = mechanicsById.get(rule.mechanicId);
        if (!row) {
          this.fail(
            REVISION_FILE_PROBLEM_CODE.unresolved,
            `${path}/mechanic`,
            `Нет механики id=${rule.mechanicId} для правила ${rule.code}`,
          );
        }
        mechanic = { code: row.code, version: row.version };
        usedMechanics.set(`${row.code}\0${row.version}`, {
          code: row.code,
          name: row.name,
          description: row.description,
          version: row.version,
        });
      }
      const mechanicPayload = this.payloadForAssemble(rule, mechanic, path);
      const external: RuleExternal = {
        code: rule.code,
        type: rule.type,
        name: rule.name,
        description: rule.description,
        spec: (rule.spec ?? {}) as object,
        keywordCodes,
        mechanic,
        mechanicPayload,
        contentStatus: rule.contentStatus ?? 'needs_work',
        active: rule.active !== false,
      };
      if (rule.catalogSection !== undefined) {
        external.catalogSection = rule.catalogSection;
      }
      if (rule.catalogSortOrder !== undefined) {
        external.catalogSortOrder = rule.catalogSortOrder;
      }
      rules.push(external);
    }

    if (rules.length === 0) {
      this.fail(REVISION_FILE_PROBLEM_CODE.format, '/rules', 'В файле нет правил');
    }

    return this.canonicalize({
      format: REVISION_FILE_FORMAT,
      formatVersion: REVISION_FILE_FORMAT_VERSION,
      exportedAt: this.nowUnix(),
      source: {
        spaceCode: revision.spaceCode,
        spaceName: revision.spaceName,
        revision: revision.revision,
        publishedAt: revision.publishedAt,
      },
      keywords: [...usedKeywords.values()],
      mechanics: [...usedMechanics.values()],
      sections: cloneData(revision.sections),
      rules,
    });
  }

  serialize(file: RevisionFile): RevisionFile {
    return this.canonicalize(file);
  }

  parse(raw: string): RevisionFile {
    let parsed: unknown;
    try {
      parsed = JSON.parse(raw) as unknown;
    } catch {
      this.fail(REVISION_FILE_PROBLEM_CODE.notJson, '', 'Файл не является JSON', 'syntax');
    }
    if (!this.isPlainObject(parsed)) {
      this.fail(REVISION_FILE_PROBLEM_CODE.format, '', 'Некорректный файл ревизии');
    }
    this.assertAllowedKeys(parsed, ROOT_KEY_SET, '');
    if (parsed.format !== REVISION_FILE_FORMAT) {
      this.fail(REVISION_FILE_PROBLEM_CODE.format, '/format', 'Это не файл ревизии PowerScale');
    }
    if (parsed.formatVersion !== REVISION_FILE_FORMAT_VERSION) {
      this.fail(
        REVISION_FILE_PROBLEM_CODE.unsupportedVersion,
        '/formatVersion',
        'Неподдерживаемая версия файла ревизии',
      );
    }
    const exportedAt = this.requireUnix(parsed.exportedAt, '/exportedAt');
    const source = this.parseSource(parsed.source);
    const keywords = this.parseKeywords(parsed.keywords);
    const mechanics = this.parseMechanics(parsed.mechanics);
    const sections = this.parseSections(parsed.sections);
    const rules = this.parseRules(parsed.rules);

    return this.canonicalize({
      format: REVISION_FILE_FORMAT,
      formatVersion: REVISION_FILE_FORMAT_VERSION,
      exportedAt,
      source,
      keywords,
      mechanics,
      sections,
      rules,
    });
  }

  materializeRules(
    file: RevisionFile,
    spaceId: number,
    keywords: readonly Keyword[],
    mechanics: readonly Mechanic[],
  ): Rule[] {
    const keywordsByCode = new Map(keywords.map((item) => [item.code, item]));
    const mechanicsByKey = new Map(mechanics.map((item) => [`${item.code}\0${item.version}`, item]));

    return file.rules.map((external, index) => {
      const path = `/rules/${index}`;
      const keywordIds: number[] = [];
      for (const keywordCode of external.keywordCodes) {
        const keyword = keywordsByCode.get(keywordCode);
        if (!keyword) {
          this.fail(REVISION_FILE_PROBLEM_CODE.unresolved, path, `Нет признака code=${keywordCode}`);
        }
        keywordIds.push(keyword.id);
      }
      let mechanicId: number | null = null;
      if (external.mechanic) {
        const mechanic = mechanicsByKey.get(`${external.mechanic.code}\0${external.mechanic.version}`);
        if (!mechanic) {
          this.fail(
            REVISION_FILE_PROBLEM_CODE.unresolved,
            `${path}/mechanic`,
            `Нет механики ${external.mechanic.code}@${external.mechanic.version}`,
          );
        }
        mechanicId = mechanic.id;
      }
      const rule: Rule = {
        id: null,
        code: external.code,
        type: external.type as RuleType,
        name: external.name,
        description: external.description,
        spaceId,
        spec: external.spec as RuleSpec,
        keywordIds,
        mechanicId,
        mechanicPayload: this.asMechanicPayload(external.mechanicPayload),
        contentStatus: external.contentStatus,
        active: external.active,
        createdAt: file.source.publishedAt,
      };
      if (external.catalogSection !== undefined) {
        rule.catalogSection = external.catalogSection;
      }
      if (external.catalogSortOrder !== undefined) {
        rule.catalogSortOrder = external.catalogSortOrder;
      }

      return rule;
    });
  }

  remapForSpace(rules: readonly Rule[], spaceId: number): Rule[] {
    return rules.map((rule) => ({
      ...cloneData(rule),
      spaceId,
      id: null,
    }));
  }

  diffAgainstPublished(
    fileRules: readonly Rule[],
    published: readonly Rule[],
    spaceId: number,
    options: { removeMissing: boolean; existingRemovedCodes: readonly string[] },
  ): RevisionFileImportDiff {
    const publishedByCode = new Map(published.map((rule) => [rule.code, rule]));
    const fileCodes = new Set(fileRules.map((rule) => rule.code));
    const added: Rule[] = [];
    const changed: Rule[] = [];
    let unchangedCount = 0;

    for (const fileRule of fileRules) {
      const local = publishedByCode.get(fileRule.code);
      if (!local) {
        added.push({ ...cloneData(fileRule), spaceId, id: null });
        continue;
      }
      if (this.ruleDiff.samePayload(fileRule, local)) {
        unchangedCount += 1;
        continue;
      }
      changed.push({ ...cloneData(fileRule), spaceId, id: local.id });
    }

    const removedCodes = [...options.existingRemovedCodes.filter((code) => !fileCodes.has(code))];
    if (options.removeMissing) {
      for (const rule of published) {
        if (!fileCodes.has(rule.code) && !removedCodes.includes(rule.code)) removedCodes.push(rule.code);
      }
    }

    return {
      added,
      changed,
      unchangedCount,
      removedCodes,
      sectionsDiffer: false,
    };
  }

  formatImportSummary(diff: RevisionFileImportDiff): string {
    const sectionsNote = diff.sectionsDiffer ? ', секции изменятся' : '';
    if (this.isEmptyDiff(diff)) {
      return `Нет отличий от текущей ревизии (${diff.unchangedCount} без изменений)${sectionsNote}`;
    }

    return `Добавлено ${diff.added.length}, изменено ${diff.changed.length}, без изменений ${diff.unchangedCount}, к удалению ${diff.removedCodes.length}${sectionsNote}`;
  }

  isEmptyDiff(diff: RevisionFileImportDiff): boolean {
    return (
      diff.added.length === 0 && diff.changed.length === 0 && diff.removedCodes.length === 0 && !diff.sectionsDiffer
    );
  }

  private canonicalize(file: RevisionFile): RevisionFile {
    const rules = [...file.rules]
      .map((rule) => this.canonicalizeRule(rule))
      .sort((left, right) => this.compareText(left.code, right.code));
    const keywords = [...file.keywords].sort((left, right) => this.compareText(left.code, right.code));
    const mechanics = [...file.mechanics].sort((left, right) => {
      const byCode = this.compareText(left.code, right.code);
      if (byCode !== 0) return byCode;

      return this.compareText(left.version, right.version);
    });
    const sections = [...file.sections].sort((left, right) => {
      if (left.sortOrder !== right.sortOrder) return left.sortOrder - right.sortOrder;

      return this.compareText(left.code, right.code);
    });

    return {
      format: REVISION_FILE_FORMAT,
      formatVersion: REVISION_FILE_FORMAT_VERSION,
      exportedAt: file.exportedAt,
      source: { ...file.source },
      keywords,
      mechanics,
      sections,
      rules,
    };
  }

  private canonicalizeRule(rule: RuleExternal): RuleExternal {
    const external: RuleExternal = {
      code: rule.code,
      type: rule.type,
      name: rule.name,
      description: rule.description,
      spec: rule.spec,
      keywordCodes: [...rule.keywordCodes].sort((left, right) => this.compareText(left, right)),
      mechanic: rule.mechanic ? { code: rule.mechanic.code, version: rule.mechanic.version } : null,
      mechanicPayload: rule.mechanicPayload,
      contentStatus: rule.contentStatus,
      active: rule.active,
    };
    if (rule.catalogSection !== undefined) {
      external.catalogSection = rule.catalogSection;
    }
    if (rule.catalogSortOrder !== undefined) {
      external.catalogSortOrder = rule.catalogSortOrder;
    }

    return external;
  }

  private parseSource(value: unknown): RevisionFileSource {
    if (!this.isPlainObject(value)) {
      this.fail(REVISION_FILE_PROBLEM_CODE.format, '/source', 'Некорректный файл ревизии');
    }
    this.assertAllowedKeys(value, SOURCE_KEYS, '/source');
    const spaceCode = this.requireNonEmptyString(value.spaceCode, '/source/spaceCode');
    const spaceName = this.requireNonEmptyString(value.spaceName, '/source/spaceName');
    const revision = this.requireInt(value.revision, '/source/revision');
    if (revision < 1) {
      this.fail(REVISION_FILE_PROBLEM_CODE.format, '/source/revision', 'Некорректный номер ревизии');
    }

    return {
      spaceCode,
      spaceName,
      revision,
      publishedAt: this.requireUnix(value.publishedAt, '/source/publishedAt'),
    };
  }

  private parseKeywords(value: unknown): RevisionFileKeyword[] {
    const list = this.requireList(value, '/keywords');
    const seen = new Set<string>();
    const keywords: RevisionFileKeyword[] = [];
    for (let index = 0; index < list.length; index += 1) {
      const path = `/keywords/${index}`;
      const row = list[index];
      if (!this.isPlainObject(row)) {
        this.fail(REVISION_FILE_PROBLEM_CODE.format, path, 'Некорректный признак в файле');
      }
      this.assertAllowedKeys(row, KEYWORD_KEYS, path);
      const code = this.requireNonEmptyString(row.code, `${path}/code`);
      if (seen.has(code)) {
        this.fail(REVISION_FILE_PROBLEM_CODE.duplicate, `${path}/code`, `Повтор признака ${code}`);
      }
      seen.add(code);
      if (typeof row.name !== 'string' || row.name.trim() === '') {
        this.fail(REVISION_FILE_PROBLEM_CODE.format, `${path}/name`, 'Некорректный признак в файле');
      }
      if (typeof row.description !== 'string') {
        this.fail(REVISION_FILE_PROBLEM_CODE.format, `${path}/description`, 'Некорректный признак в файле');
      }
      if (typeof row.active !== 'boolean') {
        this.fail(REVISION_FILE_PROBLEM_CODE.format, `${path}/active`, 'Некорректный признак в файле');
      }
      keywords.push({
        code,
        name: row.name.trim(),
        description: row.description,
        active: row.active,
      });
    }

    return keywords;
  }

  private parseMechanics(value: unknown): RevisionFileMechanic[] {
    const list = this.requireList(value, '/mechanics');
    const seen = new Set<string>();
    const mechanics: RevisionFileMechanic[] = [];
    for (let index = 0; index < list.length; index += 1) {
      const path = `/mechanics/${index}`;
      const row = list[index];
      if (!this.isPlainObject(row)) {
        this.fail(REVISION_FILE_PROBLEM_CODE.format, path, 'Некорректная механика в файле');
      }
      this.assertAllowedKeys(row, MECHANIC_KEYS, path);
      const code = this.requireNonEmptyString(row.code, `${path}/code`);
      const version = this.requireNonEmptyString(row.version, `${path}/version`);
      const key = `${code}\0${version}`;
      if (seen.has(key)) {
        this.fail(REVISION_FILE_PROBLEM_CODE.duplicate, path, `Повтор механики ${code}@${version}`);
      }
      seen.add(key);
      if (typeof row.name !== 'string' || row.name.trim() === '') {
        this.fail(REVISION_FILE_PROBLEM_CODE.format, `${path}/name`, 'Некорректная механика в файле');
      }
      if (typeof row.description !== 'string') {
        this.fail(REVISION_FILE_PROBLEM_CODE.format, `${path}/description`, 'Некорректная механика в файле');
      }
      mechanics.push({
        code,
        name: row.name.trim(),
        description: row.description,
        version,
      });
    }

    return mechanics;
  }

  private parseSections(value: unknown): AbilitySection[] {
    const list = this.requireList(value, '/sections');
    const seen = new Set<string>();
    const sections: AbilitySection[] = [];
    for (let index = 0; index < list.length; index += 1) {
      const path = `/sections/${index}`;
      const row = list[index];
      if (!this.isPlainObject(row)) {
        this.fail(REVISION_FILE_PROBLEM_CODE.format, path, 'Некорректная секция в файле');
      }
      this.assertAllowedKeys(row, SECTION_KEYS, path);
      const code = this.requireNonEmptyString(row.code, `${path}/code`);
      if (seen.has(code)) {
        this.fail(REVISION_FILE_PROBLEM_CODE.duplicate, `${path}/code`, `Повтор секции ${code}`);
      }
      seen.add(code);
      if (typeof row.name !== 'string' || row.name.trim() === '') {
        this.fail(REVISION_FILE_PROBLEM_CODE.format, `${path}/name`, 'Некорректная секция в файле');
      }
      if (row.parentCode !== null && typeof row.parentCode !== 'string') {
        this.fail(REVISION_FILE_PROBLEM_CODE.format, `${path}/parentCode`, 'Некорректная секция в файле');
      }
      const section: AbilitySection = {
        code,
        name: row.name.trim(),
        parentCode: row.parentCode === null ? null : this.requireNonEmptyString(row.parentCode, `${path}/parentCode`),
        sortOrder: this.requireInt(row.sortOrder, `${path}/sortOrder`),
      };
      if (row.catalogRootFor !== undefined) {
        section.catalogRootFor = this.requireNonEmptyString(row.catalogRootFor, `${path}/catalogRootFor`);
      }
      sections.push(section);
    }

    return sections;
  }

  private parseRules(value: unknown): RuleExternal[] {
    const list = this.requireList(value, '/rules');
    if (list.length === 0) {
      this.fail(REVISION_FILE_PROBLEM_CODE.format, '/rules', 'В файле нет правил');
    }
    const seen = new Set<string>();
    const rules: RuleExternal[] = [];
    for (let index = 0; index < list.length; index += 1) {
      rules.push(this.parseRule(list[index], `/rules/${index}`, seen));
    }

    return rules;
  }

  private parseRule(value: unknown, path: string, seen: Set<string>): RuleExternal {
    if (!this.isPlainObject(value)) {
      this.fail(REVISION_FILE_PROBLEM_CODE.format, path, 'Некорректное правило в файле');
    }
    for (const key of Object.keys(value)) {
      if (FORBIDDEN_RULE_KEY_SET.has(key)) {
        this.fail(REVISION_FILE_PROBLEM_CODE.forbiddenField, `${path}/${key}`, `Запрещённое поле ${key}`);
      }
    }
    this.assertAllowedKeys(value, RULE_KEY_SET, path);
    const code = this.requireNonEmptyString(value.code, `${path}/code`);
    if (seen.has(code)) {
      this.fail(REVISION_FILE_PROBLEM_CODE.duplicate, `${path}/code`, `Повтор правила ${code}`);
    }
    seen.add(code);
    const type = this.requireNonEmptyString(value.type, `${path}/type`);
    const name = this.requireNonEmptyString(value.name, `${path}/name`);
    if (typeof value.description !== 'string') {
      this.fail(REVISION_FILE_PROBLEM_CODE.format, `${path}/description`, 'Некорректное правило в файле');
    }
    if (!this.isObjectOrArray(value.spec)) {
      this.fail(REVISION_FILE_PROBLEM_CODE.format, `${path}/spec`, 'Некорректное правило в файле');
    }
    const keywordCodes = this.parseStringList(value.keywordCodes, `${path}/keywordCodes`);
    const mechanic = this.parseMechanicRef(value.mechanic, `${path}/mechanic`);
    if (!this.isObjectOrArray(value.mechanicPayload)) {
      this.fail(REVISION_FILE_PROBLEM_CODE.format, `${path}/mechanicPayload`, 'Некорректное правило в файле');
    }
    if (mechanic === null && !this.isEmptyArray(value.mechanicPayload)) {
      this.fail(
        REVISION_FILE_PROBLEM_CODE.format,
        `${path}/mechanicPayload`,
        'Без механики payload должен быть пустым списком',
      );
    }
    if (typeof value.contentStatus !== 'string' || value.contentStatus.trim() === '') {
      this.fail(REVISION_FILE_PROBLEM_CODE.format, `${path}/contentStatus`, 'Некорректное правило в файле');
    }
    if (typeof value.active !== 'boolean') {
      this.fail(REVISION_FILE_PROBLEM_CODE.format, `${path}/active`, 'Некорректное правило в файле');
    }
    const external: RuleExternal = {
      code,
      type,
      name,
      description: value.description,
      spec: value.spec,
      keywordCodes,
      mechanic,
      mechanicPayload: value.mechanicPayload,
      contentStatus: value.contentStatus.trim(),
      active: value.active,
    };
    if (value.catalogSection !== undefined) {
      if (value.catalogSection !== null && typeof value.catalogSection !== 'string') {
        this.fail(REVISION_FILE_PROBLEM_CODE.format, `${path}/catalogSection`, 'Некорректное правило в файле');
      }
      external.catalogSection = value.catalogSection;
    }
    if (value.catalogSortOrder !== undefined) {
      external.catalogSortOrder = this.requireInt(value.catalogSortOrder, `${path}/catalogSortOrder`);
    }

    return external;
  }

  private parseMechanicRef(value: unknown, path: string): RevisionFileMechanicRef | null {
    if (value === null) return null;
    if (!this.isPlainObject(value)) {
      this.fail(REVISION_FILE_PROBLEM_CODE.format, path, 'Некорректная ссылка на механику');
    }
    this.assertAllowedKeys(value, MECHANIC_REF_KEYS, path);

    return {
      code: this.requireNonEmptyString(value.code, `${path}/code`),
      version: this.requireNonEmptyString(value.version, `${path}/version`),
    };
  }

  private parseStringList(value: unknown, path: string): string[] {
    const list = this.requireList(value, path);
    const seen = new Set<string>();
    const codes: string[] = [];
    for (let index = 0; index < list.length; index += 1) {
      const itemPath = `${path}/${index}`;
      const code = this.requireNonEmptyString(list[index], itemPath);
      if (seen.has(code)) {
        this.fail(REVISION_FILE_PROBLEM_CODE.duplicate, itemPath, `Повтор признака ${code}`);
      }
      seen.add(code);
      codes.push(code);
    }

    return codes;
  }

  private payloadForAssemble(rule: Rule, mechanic: RevisionFileMechanicRef | null, path: string): object {
    if (mechanic === null) {
      return [];
    }
    const payload = rule.mechanicPayload;
    if (payload == null) {
      return [];
    }
    if (!this.isObjectOrArray(payload)) {
      this.fail(REVISION_FILE_PROBLEM_CODE.format, `${path}/mechanicPayload`, 'Некорректный payload механики');
    }

    return payload;
  }

  private asMechanicPayload(value: object): MechanicPayload | null {
    if (this.isEmptyArray(value)) {
      return null;
    }

    return value as MechanicPayload;
  }

  private assertAllowedKeys(row: Record<string, unknown>, allowed: ReadonlySet<string>, path: string): void {
    for (const key of Object.keys(row)) {
      if (!allowed.has(key)) {
        const fieldPath = path === '' ? `/${key}` : `${path}/${key}`;
        this.fail(REVISION_FILE_PROBLEM_CODE.forbiddenField, fieldPath, `Неизвестное поле ${key}`);
      }
    }
  }

  private requireList(value: unknown, path: string): unknown[] {
    if (!Array.isArray(value)) {
      this.fail(REVISION_FILE_PROBLEM_CODE.format, path, 'Ожидался список');
    }

    return value;
  }

  private requireNonEmptyString(value: unknown, path: string): string {
    if (typeof value !== 'string' || value.trim() === '') {
      this.fail(REVISION_FILE_PROBLEM_CODE.format, path, 'Ожидалась непустая строка');
    }

    return value.trim();
  }

  private requireInt(value: unknown, path: string): number {
    if (typeof value !== 'number' || !Number.isInteger(value)) {
      this.fail(REVISION_FILE_PROBLEM_CODE.format, path, 'Ожидалось целое число');
    }

    return value;
  }

  private requireUnix(value: unknown, path: string): number {
    const unix = this.requireInt(value, path);
    if (unix < 0) {
      this.fail(REVISION_FILE_PROBLEM_CODE.format, path, 'Ожидался unix');
    }

    return unix;
  }

  private isPlainObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
  }

  private isObjectOrArray(value: unknown): value is object {
    return typeof value === 'object' && value !== null;
  }

  private isEmptyArray(value: unknown): value is [] {
    return Array.isArray(value) && value.length === 0;
  }

  private compareText(left: string, right: string): number {
    if (left < right) return -1;
    if (left > right) return 1;

    return 0;
  }

  private fail(code: string, path: string, message: string, stage: RevisionFileProblem['stage'] = 'format'): never {
    const problem: RevisionFileProblem = { code, path, stage, message };
    throw new RevisionFileProblemError(problem);
  }
}
