import { describe, expect, it } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CheckSpec } from '@/modules/Roleplay/Rule/Dto/Check/CheckSpec';
import { exhaustionCheckService } from '@/modules/Roleplay/Game/Service/Instance/exhaustionCheckService';

function stateRule(code: string, flag?: 'decline_weakness' | 'decline_disabled' | 'decline_unconscious'): Rule {
  return {
    code,
    type: 'state',
    spec: {
      value_type: 'flag',
      aggregation: 'max',
      effects: [],
      ...(flag ? { [flag]: true } : {}),
    },
  } as unknown as Rule;
}

function checkSpec(outcomes: CheckSpec['outcomes']): CheckSpec {
  return {
    type: 'check',
    difficulty_input: { kind: 'none' },
    allowed_modes: 'solo',
    outcomes,
  };
}

describe('ExhaustionCheckService.declineRule', () => {
  it('ищет исход упадка сил по флагу, не по коду каталога', () => {
    expect(exhaustionCheckService.declineRule([stateRule('weakness')], 'weakness')).toBeNull();
    expect(exhaustionCheckService.declineRule([stateRule('fatigue', 'decline_weakness')], 'weakness')?.code).toBe(
      'fatigue',
    );
    expect(exhaustionCheckService.declineRule([stateRule('disabled')], 'disabled')).toBeNull();
    expect(exhaustionCheckService.declineRule([stateRule('spent', 'decline_disabled')], 'disabled')?.code).toBe(
      'spent',
    );
    expect(exhaustionCheckService.declineRule([stateRule('unconscious')], 'unconscious')).toBeNull();
    expect(exhaustionCheckService.declineRule([stateRule('asleep', 'decline_unconscious')], 'unconscious')?.code).toBe(
      'asleep',
    );
    expect(exhaustionCheckService.declineRule([stateRule('fatigue', 'decline_weakness')], 'clear')).toBeNull();
  });
});

describe('ExhaustionCheckService.outcomeForRating', () => {
  const rules = [
    stateRule('tired', 'decline_weakness'),
    stateRule('spent', 'decline_disabled'),
    stateRule('asleep', 'decline_unconscious'),
  ];

  it('берёт код состояния из outcomes, а не из кода правила', () => {
    const check = checkSpec([
      { rating: -1, state_rule_code: 'tired' },
      { rating: -2, state_rule_code: 'spent' },
      { max_rating: -3, state_rule_code: 'asleep' },
    ]);

    expect(exhaustionCheckService.outcomeForRating(check, rules, -1)).toEqual({
      outcome: 'weakness',
      code: 'tired',
    });
    expect(exhaustionCheckService.outcomeForRating(check, rules, -2)).toEqual({
      outcome: 'disabled',
      code: 'spent',
    });
    expect(exhaustionCheckService.outcomeForRating(check, rules, -3)).toEqual({
      outcome: 'unconscious',
      code: 'asleep',
    });
    expect(exhaustionCheckService.outcomeForRating(check, rules, -8)).toEqual({
      outcome: 'unconscious',
      code: 'asleep',
    });
  });

  it('не накладывает состояние, если исход не совпал или задан чистым', () => {
    const check = checkSpec([
      { rating: -1, state_rule_code: 'tired' },
      { max_rating: -5, state_rule_code: null },
    ]);

    expect(exhaustionCheckService.outcomeForRating(check, rules, 0)).toEqual({ outcome: 'clear', code: null });
    expect(exhaustionCheckService.outcomeForRating(check, rules, -2)).toEqual({ outcome: 'clear', code: null });
    expect(exhaustionCheckService.outcomeForRating(check, rules, -6)).toEqual({ outcome: 'clear', code: null });
  });

  it('для старых ревизий падает на флаги decline_*', () => {
    expect(exhaustionCheckService.outcomeForRating(null, rules, -1)).toEqual({
      outcome: 'weakness',
      code: 'tired',
    });
    expect(exhaustionCheckService.outcomeForRating(null, rules, -2)).toEqual({
      outcome: 'disabled',
      code: 'spent',
    });
    expect(exhaustionCheckService.outcomeForRating(null, rules, 0)).toEqual({ outcome: 'clear', code: null });
  });
});

describe('ExhaustionCheckService.declineCodes', () => {
  it('снимает коды из outcomes без дублей', () => {
    const check = checkSpec([
      { rating: -1, state_rule_code: 'tired' },
      { rating: -2, state_rule_code: 'spent' },
      { max_rating: -3, state_rule_code: 'tired' },
    ]);

    expect(exhaustionCheckService.declineCodes([], check)).toEqual(['tired', 'spent']);
  });

  it('для старых ревизий собирает состояния с одним флагом decline_*', () => {
    const rules = [
      stateRule('tired', 'decline_weakness'),
      stateRule('spent', 'decline_disabled'),
      stateRule('asleep', 'decline_unconscious'),
      {
        ...stateRule('broken'),
        spec: { value_type: 'flag', aggregation: 'max', decline_weakness: true, decline_disabled: true },
      } as Rule,
    ];

    expect(exhaustionCheckService.declineCodes(rules, null)).toEqual(['tired', 'spent', 'asleep']);
  });
});
