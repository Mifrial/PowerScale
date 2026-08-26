import type { IRevisionRulesFetcher } from '@/modules/Roleplay/Rule/Interface/IRevisionRulesFetcher';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleRevisionQuery } from '@/modules/Roleplay/Rule/Dto/RuleRevisionQuery';
import type { RevisionRuleSlice } from '@/modules/Roleplay/Rule/Dto/RevisionRuleSlice';

export class RuleRevisionResolverService {
  constructor(
    private readonly getFetcher: () => IRevisionRulesFetcher | null,
    private readonly fetchCatalogRule: (ruleId: string, signal?: AbortSignal) => Promise<Rule | null>,
  ) {}

  /** Поиск правила в срезе ревизии по коду или id (код — ключ среза, id — глобальный). */
  findRuleInRevision(rules: Rule[], ruleId: string): Rule | null {
    return rules.find((rule) => rule.code === ruleId || rule.id === ruleId) ?? null;
  }

  /**
   * Резолв правила из контекста ревизии (Слой 1, §7.20): слайдеры/чипы правил должны
   * показывать версию ревизии (spaceId + rulesRevision), а не глобальный каталог. Если
   * ревизия задана — берём из её среза (по code||id); правило может отсутствовать в срезе
   * (изъято из ревизии) — тогда фолбэк на каталог по id.
   */
  async resolveRuleFromRevision(query: RuleRevisionQuery, signal?: AbortSignal): Promise<Rule | null> {
    const slice = await this.resolveRevisionSlice(query, signal);

    return slice.rule;
  }

  async resolveRevisionSlice(query: RuleRevisionQuery, signal?: AbortSignal): Promise<RevisionRuleSlice> {
    const { spaceId, rulesRevision, ruleId } = query;
    if (ruleId == null) return { rule: null, rules: [] };

    if (spaceId != null && rulesRevision != null) {
      const fetcher = this.getFetcher();
      if (fetcher) {
        try {
          const rules = await fetcher.fetchRules(spaceId, rulesRevision, signal);
          const found = this.findRuleInRevision(rules, ruleId);
          if (found) return { rule: found, rules };

          const fallback = await this.tryCatalogRule(ruleId, signal);

          return { rule: fallback, rules };
        } catch (error) {
          if (error instanceof DOMException && error.name === 'AbortError') throw error;
        }
      }
    }

    const rule = await this.tryCatalogRule(ruleId, signal);

    return { rule, rules: rule ? [rule] : [] };
  }

  /** Чип чата передаёт code, каталог — id; getRule кидает, если ключ не тот. */
  private async tryCatalogRule(ruleId: string, signal?: AbortSignal): Promise<Rule | null> {
    try {
      return await this.fetchCatalogRule(ruleId, signal);
    } catch (error) {
      if (error instanceof DOMException && error.name === 'AbortError') throw error;

      return null;
    }
  }
}
