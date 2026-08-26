import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatRulesContext } from '@/modules/Messages/Chat/Dto/ChatRulesContext';
import type { ITokenSource } from '@/modules/Messages/Chat/Interface/ITokenSource';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import { getTokenSources } from '@/modules/Messages/Chat/init';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { rollService } from '@/modules/Roleplay/Game/Service/Instance/rollService';
import { simpleCheckRollService } from '@/modules/Roleplay/Game/Service/Instance/simpleCheckRollService';

export class GameChatRulesContextService {
  /**
   * Готовый контекст правил чата по ревизии: имена для inline-чипов [[rule:...]], источники
   * «Вставить ссылку» (правило из ревизии + глобальные прочие) и обработка вложений (броски
   * через RollEngine как простая проверка vs {0|0}). Общий для игрового чата, обсуждений и мессенджера.
   */
  buildChatRulesContext(
    rules: Rule[],
    mechanics: Mechanic[],
    spaceId?: number | null,
    rulesRevision?: number | null,
    rng: DiceRng = Math.random,
  ): ChatRulesContext {
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

    const processAttachments = (attachments: ChatAttachment[]): ChatAttachment[] => {
      if (rules.length === 0 || mechanics.length === 0) return attachments;

      return attachments.map((attachment) => {
        if (attachment.type !== ROLL_ATTACHMENT_TYPE) return attachment;
        const raw = attachment.payload as DiceRollSpec | DiceRollResult;
        if (rollService.isDiceRollResult(raw)) return attachment;
        const payload = simpleCheckRollService.rollSimpleCheckZero(raw, rng, rules, mechanics);

        return { ...attachment, payload };
      });
    };

    return { tokenLabels, tokenSources, processAttachments, spaceId, rulesRevision };
  }
}
