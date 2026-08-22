import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatRulesContext } from '@/modules/Messages/Chat/Dto/ChatRulesContext';
import type { ITokenSource } from '@/modules/Messages/Chat/Interface/ITokenSource';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import { getTokenSources } from '@/modules/Messages/Chat/init';
import { rollEngine } from '@/modules/Roleplay/Game/Service/Roll/Instance/rollEngine';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';

/**
 * Готовый контекст правил чата по ревизии: имена для inline-чипов [[rule:...]], источники
 * «Вставить ссылку» (правило из ревизии + глобальные прочие) и обработка вложений (броски
 * через RollEngine — механики ревизии). Общий для игрового чата, обсуждений и мессенджера.
 */
export function buildChatRulesContext(
  rules: Rule[],
  mechanics: Mechanic[],
  spaceId?: number | null,
  rulesRevision?: number | null,
): ChatRulesContext {
  const ruleNames: Record<string, string> = {};
  for (const rule of rules) ruleNames[rule.code] = rule.name;

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

  const processAttachments = (attachments: ChatAttachment[]): ChatAttachment[] => {
    if (rules.length === 0 || mechanics.length === 0) return attachments;

    return attachments.map((attachment) => {
      if (attachment.type !== ROLL_ATTACHMENT_TYPE) return attachment;
      if ('rolls' in (attachment.payload as object)) return attachment;
      const payload = rollEngine.roll(attachment.payload as DiceRollSpec, Math.random, rules, mechanics);

      return { ...attachment, payload };
    });
  };

  return { ruleNames, tokenSources, processAttachments, spaceId, rulesRevision };
}
