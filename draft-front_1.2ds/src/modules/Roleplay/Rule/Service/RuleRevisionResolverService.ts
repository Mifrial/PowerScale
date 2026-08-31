import type { IRevisionRulesFetcher } from '@/modules/Roleplay/Rule/Interface/IRevisionRulesFetcher';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleRevisionQuery } from '@/modules/Roleplay/Rule/Dto/RuleRevisionQuery';
import type { RevisionRuleSlice } from '@/modules/Roleplay/Rule/Dto/RevisionRuleSlice';

export class RuleRevisionResolverService {
  constructor(
    private readonly getFetcher: () => IRevisionRulesFetcher | null,
    private readonly fetchCatalogRule: (code: string, signal?: AbortSignal) => Promise<Rule | null>,
  ) {}

  /** Поиск в срезе ревизии только по semantic code. */
  findRuleInRevision(rules: Rule[], ruleCode: string): Rule | null {
    return rules.find((rule) => rule.code === ruleCode) ?? null;
  }

  /**
   * Резолв правила из контекста ревизии: слайдеры/чипы показывают версию среза
   * (spaceId + rulesRevision), а не глобальный каталог. Нет в срезе — фолбэк каталога по code.
   */
  async resolveRuleFromRevision(query: RuleRevisionQuery, signal?: AbortSignal): Promise<Rule | null> {
    const slice = await this.resolveRevisionSlice(query, signal);

    return slice.rule;
  }

  async resolveRevisionSlice(query: RuleRevisionQuery, signal?: AbortSignal): Promise<RevisionRuleSlice> {
    const { spaceId, rulesRevision, ruleCode } = query;
    if (ruleCode == null) return { rule: null, rules: [] };

    if (spaceId != null && rulesRevision != null) {
      const fetcher = this.getFetcher();
      if (fetcher) {
        try {
          const rules = await fetcher.fetchRules(spaceId, rulesRevision, signal);
          const found = this.findRuleInRevision(rules, ruleCode);
          if (found) return { rule: found, rules };

          const fallback = await this.tryCatalogRule(ruleCode, signal);

          return { rule: fallback, rules };
        } catch (error) {
          if (error instanceof DOMException && error.name === 'AbortError') throw error;
        }
      }
    }

    const rule = await this.tryCatalogRule(ruleCode, signal);

    return { rule, rules: rule ? [rule] : [] };
  }

  private async tryCatalogRule(code: string, signal?: AbortSignal): Promise<Rule | null> {
    try {
      return await this.fetchCatalogRule(code, signal);
    } catch (error) {
      if (error instanceof DOMException && error.name === 'AbortError') throw error;

      return null;
    }
  }
}
