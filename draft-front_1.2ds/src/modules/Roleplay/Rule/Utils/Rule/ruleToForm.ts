import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleFormState } from '@/modules/Roleplay/Rule/Dto/RuleFormState';

export function ruleToForm(rule: Rule): RuleFormState {
  return {
    type: rule.type,
    name: rule.name,
    code: rule.code,
    loadedCode: rule.code,
    description: rule.description,
    mechanics: rule.mechanics.map((row) => ({
      mechanicId: row.mechanicId,
      mechanicPayload: row.mechanicPayload == null ? row.mechanicPayload : structuredClone(row.mechanicPayload),
    })),
    keywordIds: rule.keywordIds ?? [],
    spec: rule.spec ?? null,
    catalogSection: rule.catalogSection ?? null,
    catalogSortOrder: rule.catalogSortOrder ?? 100,
    contentStatus: rule.contentStatus ?? 'needs_work',
    contentNote: rule.contentNote ?? '',
  };
}
