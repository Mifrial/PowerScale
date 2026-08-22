import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { PublishDiff } from '@/modules/Roleplay/Rule/Dto/PublishDiff';
import type { ProblemEntry } from '@/modules/Roleplay/Rule/Dto/ProblemEntry';

export class RuleDiffService {
  classifyDraftDiff(published: Rule[], draft: Rule[]): PublishDiff {
    const publishedById = new Map(published.map((r) => [r.id, r]));
    const added: Rule[] = [];
    const changed: Rule[] = [];
    for (const d of draft) {
      const pub = publishedById.get(d.id);
      if (!pub) {
        added.push(d);
      } else if (!RuleDiffService.sameContent(d, pub)) {
        changed.push(d);
      }
    }

    return { added, changed };
  }

  /** Каноническое сравнение содержимого правила без служебных временных полей (created/updatedAt). */
  private static sameContent(a: Rule, b: Rule): boolean {
    const aContent = {
      id: a.id,
      code: a.code,
      type: a.type,
      name: a.name,
      description: a.description,
      spec: a.spec,
      keywordIds: a.keywordIds,
      mechanicId: a.mechanicId,
    };
    const bContent = {
      id: b.id,
      code: b.code,
      type: b.type,
      name: b.name,
      description: b.description,
      spec: b.spec,
      keywordIds: b.keywordIds,
      mechanicId: b.mechanicId,
    };

    return RuleDiffService.deepEqual(aContent, bContent);
  }

  private static deepEqual(a: unknown, b: unknown): boolean {
    if (a === b) return true;
    if (typeof a !== 'object' || typeof b !== 'object' || a === null || b === null) return false;
    if (Array.isArray(a) !== Array.isArray(b)) return false;
    if (Array.isArray(a)) {
      const aa = a as unknown[];
      const bb = b as unknown[];
      if (aa.length !== bb.length) return false;

      return aa.every((v, i) => RuleDiffService.deepEqual(v, bb[i]));
    }

    const aEntries = Object.entries(a as Record<string, unknown>).filter(([, v]) => v !== undefined);
    const bEntries = Object.entries(b as Record<string, unknown>).filter(([, v]) => v !== undefined);
    if (aEntries.length !== bEntries.length) return false;

    for (const [key, value] of aEntries) {
      const bValue = (b as Record<string, unknown>)[key];
      if (bValue === undefined || !RuleDiffService.deepEqual(value, bValue)) return false;
    }

    return true;
  }

  /** Группирует ошибки валидации по правилу (ruleCode). */
  groupProblems(items: { ruleCode: string; ruleName: string; message: string }[]): ProblemEntry[] {
    const byCode = new Map<string, ProblemEntry>();
    const order: string[] = [];
    for (const item of items) {
      let entry = byCode.get(item.ruleCode);
      if (!entry) {
        entry = { ruleCode: item.ruleCode, ruleName: item.ruleName, messages: [] };
        byCode.set(item.ruleCode, entry);
        order.push(item.ruleCode);
      }
      entry.messages.push(item.message);
    }

    return [...byCode.values()];
  }
}
