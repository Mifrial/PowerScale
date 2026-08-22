import { describe, expect, it } from 'vitest';
import { buildChatRulesContext } from '@/modules/Roleplay/Game/Utils/chatRulesContext';
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
    mechanic_payload: { type: 'roll', data: { sub_mechanics: ['six_one_rule', 'advantage_disadvantage'] } },
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
    id: 'rule-strength',
    code: 'strength',
    type: 'characteristic',
    name: 'Сила',
    description: '',
    spaceId: 1,
    keywordIds: [],
    mechanicId: null,
    mechanic_payload: null,
    createdAt: '2026-01-15T10:00:00Z',
  },
];

const MECHANICS: Mechanic[] = [
  { id: 1, code: 'six_one_rule', name: 'Правило 6 и 1', description: '', version: '4.5.0' },
];

describe('buildChatRulesContext', () => {
  it('строит имена правил и источник «Вставить ссылку» из ревизии', async () => {
    const context = buildChatRulesContext(RULES, MECHANICS);
    expect(context.ruleNames.roll).toBe('Бросок');
    expect(context.ruleNames.strength).toBe('Сила');

    const ruleSource = context.tokenSources.find((source) => source.type === 'rule');
    expect(ruleSource).toBeDefined();
    const results = ruleSource ? await ruleSource.search('сил') : [];
    expect(results.map((option) => option.label)).toEqual(['Сила']);
  });

  it('processAttachments считает бросок механиками ревизии и пропускает готовые результаты', () => {
    const context = buildChatRulesContext(RULES, MECHANICS);

    const processed = context.processAttachments([
      { type: ROLL_ATTACHMENT_TYPE, payload: { diceCount: 2, dieFaces: 6, efficiency: 3, adv: 0, dieSize: 0 } },
    ]);
    const result = processed[0].payload as { rolls: number[]; totalSuccesses: number };
    expect(result.rolls).toHaveLength(2);
    expect(typeof result.totalSuccesses).toBe('number');

    const passthrough = context.processAttachments([{ type: ROLL_ATTACHMENT_TYPE, payload: result }]);
    expect(passthrough[0].payload).toBe(result);
  });
});
