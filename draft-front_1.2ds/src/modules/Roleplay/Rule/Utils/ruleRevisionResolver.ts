import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useRuleStore } from '@/modules/Roleplay/Rule/Store/rules';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

export interface RuleRevisionQuery {
  spaceId: number | null;
  rulesRevision: number | null;
  ruleId: string | null;
}

/** Поиск правила в срезе ревизии по коду или id (код — ключ среза, id — глобальный). */
export function findRuleInRevision(rules: Rule[], ruleId: string): Rule | null {
  return rules.find((rule) => rule.code === ruleId || rule.id === ruleId) ?? null;
}

/**
 * Резолв правила из контекста ревизии (Слой 1, §7.20): слайдеры/чипы правил должны
 * показывать версию ревизии (spaceId + rulesRevision), а не глобальный каталог. Если
 * ревизия задана — берём из её среза (по code||id); правило может отсутствовать в срезе
 * (изъято из ревизии) — тогда фолбэк на каталог по id.
 */
export async function resolveRuleFromRevision(query: RuleRevisionQuery, signal?: AbortSignal): Promise<Rule | null> {
  const { spaceId, rulesRevision, ruleId } = query;
  if (ruleId == null) return null;

  if (spaceId != null && rulesRevision != null) {
    try {
      const revision = await useSpaceRevisionStore().fetchRevision(spaceId, rulesRevision, signal);
      const found = findRuleInRevision(revision.rules, ruleId);
      if (found) return found;
    } catch (error) {
      if (error instanceof DOMException && error.name === 'AbortError') throw error;
      // ревизия не загрузилась — фолбэк на каталог ниже
    }
  }

  return useRuleStore().fetchRule(ruleId, signal);
}
