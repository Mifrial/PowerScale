import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/** Классификация правил черновика относительно последней опубликованной ревизии. */
export interface PublishDiff {
  /** Новые: есть в черновике, нет в published. */
  added: Rule[];
  /** Изменённые: есть в published и контент отличается. */
  changed: Rule[];
}

export interface ProblemEntry {
  ruleCode: string;
  ruleName: string;
  messages: string[];
}

export class RuleDiffService {
  /** Содержимое правила без служебных временных полей (created/updatedAt). */
  private static ruleContent(rule: Rule): string {
    const { id, code, type, name, description, spec, keywordIds, mechanicId } = rule;

    return JSON.stringify({ id, code, type, name, description, spec, keywordIds, mechanicId });
  }

  classifyDraftDiff(published: Rule[], draft: Rule[]): PublishDiff {
    const publishedById = new Map(published.map((r) => [r.id, r]));
    const added: Rule[] = [];
    const changed: Rule[] = [];
    for (const d of draft) {
      const pub = publishedById.get(d.id);
      if (!pub) {
        added.push(d);
      } else if (RuleDiffService.ruleContent(d) !== RuleDiffService.ruleContent(pub)) {
        changed.push(d);
      }
    }

    return { added, changed };
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

export const ruleDiffService = new RuleDiffService();
