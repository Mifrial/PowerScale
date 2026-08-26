import type { ChatRulesContext } from '@/modules/Messages/Chat/Dto/ChatRulesContext';
import type { ITokenSource } from '@/modules/Messages/Chat/Interface/ITokenSource';
import { getTokenSources } from '@/modules/Messages/Chat/init';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/** Имена и источники ссылок на правила ревизии — без бросков (RollEngine живёт в Game). */
export class CharacterChatRulesContextService {
  build(rules: Rule[], spaceId?: number | null, rulesRevision?: number | null): ChatRulesContext {
    const tokenLabels: Record<string, string> = {};
    for (const rule of rules) tokenLabels[rule.code] = rule.name;

    const ruleTokenSource: ITokenSource = {
      type: 'rule',
      label: 'Правило',
      icon: 'mdi-book-open-variant',
      search: async (query) => {
        const normalized = query.toLowerCase();
        const matched = normalized
          ? rules.filter(
              (rule) => rule.name.toLowerCase().includes(normalized) || rule.code.toLowerCase().includes(normalized),
            )
          : rules.slice(0, 10);

        return matched.map((rule) => ({ value: rule.code, label: rule.name }));
      },
    };
    const tokenSources = [ruleTokenSource, ...getTokenSources().filter((source) => source.type !== 'rule')];

    return {
      tokenLabels,
      tokenSources,
      processAttachments: (attachments) => attachments,
      spaceId,
      rulesRevision,
    };
  }
}
