import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleType } from '@/modules/Roleplay/Rule/Enum/RuleType';
import type { SpaceRevision } from '@/modules/Roleplay/Space/Dto/SpaceRevision';
import type { RevisionFile } from '@/modules/Roleplay/Space/Dto/RevisionFile';
import type { RevisionFileImportDiff } from '@/modules/Roleplay/Space/Dto/RevisionFileImportDiff';
import {
  REVISION_FILE_FORMAT,
  REVISION_FILE_FORMAT_VERSION,
} from '@/modules/Roleplay/Space/Constant/REVISION_FILE_FORMAT';
import type { ruleDiffService } from '@/modules/Roleplay/Rule/init';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';

export class RevisionFileService {
  constructor(
    private readonly ruleDiff: typeof ruleDiffService,
    private readonly typeLabels: Readonly<Record<string, string>>,
  ) {}

  serialize(revision: SpaceRevision<Rule>): RevisionFile {
    return {
      format: REVISION_FILE_FORMAT,
      formatVersion: REVISION_FILE_FORMAT_VERSION,
      exportedAt: new Date().toISOString(),
      revision,
    };
  }

  parse(raw: string): RevisionFile {
    let parsed: unknown;
    try {
      parsed = JSON.parse(raw) as unknown;
    } catch {
      throw new Error('Файл не является JSON');
    }
    if (typeof parsed !== 'object' || parsed === null) throw new Error('Некорректный файл ревизии');
    const row = parsed as Record<string, unknown>;
    if (row.format !== REVISION_FILE_FORMAT) throw new Error('Это не файл ревизии PowerScale');
    if (row.formatVersion !== REVISION_FILE_FORMAT_VERSION) throw new Error('Неподдерживаемая версия файла ревизии');
    if (typeof row.exportedAt !== 'string') throw new Error('Некорректный файл ревизии');
    const revision = this.parseRevision(row.revision);

    return {
      format: REVISION_FILE_FORMAT,
      formatVersion: REVISION_FILE_FORMAT_VERSION,
      exportedAt: row.exportedAt,
      revision,
    };
  }

  remapForSpace(rules: readonly Rule[], spaceId: number, idByCode: ReadonlyMap<string, string>): Rule[] {
    return rules.map((rule) => ({
      ...cloneData(rule),
      spaceId,
      id: idByCode.get(rule.code) ?? rule.id,
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
        added.push({ ...cloneData(fileRule), spaceId, id: `imported-${fileRule.code}` });
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

    return { added, changed, unchangedCount, removedCodes };
  }

  formatImportSummary(diff: RevisionFileImportDiff): string {
    if (diff.added.length + diff.changed.length + diff.removedCodes.length === 0) {
      return `Нет отличий от текущей ревизии (${diff.unchangedCount} без изменений)`;
    }

    return `Добавлено ${diff.added.length}, изменено ${diff.changed.length}, без изменений ${diff.unchangedCount}, к удалению ${diff.removedCodes.length}`;
  }

  isEmptyDiff(diff: RevisionFileImportDiff): boolean {
    return diff.added.length === 0 && diff.changed.length === 0 && diff.removedCodes.length === 0;
  }

  private parseRevision(value: unknown): SpaceRevision<Rule> {
    if (typeof value !== 'object' || value === null) throw new Error('Некорректный файл ревизии');
    const row = value as Record<string, unknown>;
    if (typeof row.revision !== 'number' || typeof row.publishedAt !== 'string') {
      throw new Error('Некорректный файл ревизии');
    }
    if (typeof row.spaceCode !== 'string' || typeof row.spaceName !== 'string') {
      throw new Error('Некорректный файл ревизии');
    }
    if (!Array.isArray(row.rules) || row.rules.length === 0) throw new Error('В файле нет правил');

    return {
      revision: row.revision,
      publishedAt: row.publishedAt,
      spaceCode: row.spaceCode,
      spaceName: row.spaceName,
      rules: row.rules.map((item) => this.parseRule(item)),
    };
  }

  private parseRule(value: unknown): Rule {
    if (typeof value !== 'object' || value === null) throw new Error('Некорректное правило в файле');
    const row = value as Record<string, unknown>;
    if (typeof row.id !== 'string' || typeof row.code !== 'string') throw new Error('Некорректное правило в файле');
    if (typeof row.type !== 'string' || !(row.type in this.typeLabels)) {
      throw new Error(`Неизвестный тип правила: ${String(row.type)}`);
    }
    if (typeof row.name !== 'string' || typeof row.description !== 'string') {
      throw new Error('Некорректное правило в файле');
    }
    if (typeof row.spaceId !== 'number' || typeof row.createdAt !== 'string') {
      throw new Error('Некорректное правило в файле');
    }

    return {
      id: row.id,
      code: row.code,
      type: row.type as RuleType,
      name: row.name,
      description: row.description,
      spaceId: row.spaceId,
      spec: row.spec as Rule['spec'],
      keywordIds: Array.isArray(row.keywordIds) ? (row.keywordIds as number[]) : undefined,
      mechanicId: typeof row.mechanicId === 'number' || row.mechanicId === null ? row.mechanicId : undefined,
      mechanic_payload: row.mechanic_payload as Rule['mechanic_payload'],
      createdAt: row.createdAt,
      updatedAt: typeof row.updatedAt === 'string' ? row.updatedAt : undefined,
    };
  }
}
