import { describe, expect, it } from 'vitest';
import { gameChatRulesContextService } from '@/modules/Roleplay/Game/Service/Instance/gameChatRulesContextService';

import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';

const RULES: Rule[] = [
  {
    id: 'rule-roll',
    code: 'roll',
    type: 'simple',
    name: 'Бросок',
    description: '',
    spaceId: 1,
    keywordIds: [],
    mechanicId: 5,
    mechanic_payload: { type: 'roll', data: { sub_mechanics: ['advantage_disadvantage'] } },
    createdAt: '2026-01-15T10:00:00Z',
  },
  {
    id: 'rule-1',
    code: 'rule-6-and-1',
    type: 'simple',
    name: 'Правило 6 и 1',
    description: '',
    spaceId: 1,
    keywordIds: [],
    mechanicId: 1,
    mechanic_payload: null,
    createdAt: '2026-01-15T10:00:00Z',
  },
  {
    id: 'rule-check-simple',
    code: 'check-simple',
    type: 'check',
    name: 'Простая проверка',
    description: '',
    spaceId: 1,
    spec: {
      type: 'check',
      difficulty_input: { kind: 'ask' },
      allowed_modes: 'both',
      attached_rule_codes: ['rule-6-and-1', 'advantages'],
    },
    createdAt: '2026-08-22T12:00:00Z',
  },
];

const MECHANICS: Mechanic[] = [
  { id: 1, code: 'six_one_rule', name: 'Правило 6 и 1', description: '', version: '4.5.0' },
];

describe('buildChatRulesContext', () => {
  it('строит имена правил и источник «Вставить ссылку» из ревизии', async () => {
    const context = gameChatRulesContextService.buildChatRulesContext(RULES, MECHANICS);
    expect(context.tokenLabels.roll).toBe('Бросок');
    expect(context.tokenLabels.strength).toBeUndefined();
    expect(context.tokenLabels['check-simple']).toBe('Простая проверка');

    const ruleSource = context.tokenSources.find((source) => source.type === 'rule');
    expect(ruleSource).toBeDefined();
    const results = ruleSource ? await ruleSource.search('прост') : [];
    expect(results.map((option) => option.label)).toEqual(['Простая проверка']);
  });

  it('processAttachments считает бросок механиками ревизии и пропускает готовые результаты', () => {
    const context = gameChatRulesContextService.buildChatRulesContext(RULES, MECHANICS);

    const processed = context.processAttachments([
      { type: ROLL_ATTACHMENT_TYPE, payload: { diceCount: 2, dieFaces: 6, efficiency: 3, advantages: [], dieSize: 0 } },
    ]);
    const result = processed[0].payload as {
      rolls: number[];
      totalSuccesses: number;
      check?: { check_code: string; rating: number; passed: boolean };
    };
    expect(result.rolls).toHaveLength(2);
    expect(typeof result.totalSuccesses).toBe('number');
    expect(result.check?.check_code).toBe('check-simple');
    expect(typeof result.check?.passed).toBe('boolean');
    expect(typeof result.check?.rating).toBe('number');

    const passthrough = context.processAttachments([{ type: ROLL_ATTACHMENT_TYPE, payload: result }]);
    expect(passthrough[0].payload).toBe(result);
  });
});
