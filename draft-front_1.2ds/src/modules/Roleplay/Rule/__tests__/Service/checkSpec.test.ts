import { describe, expect, it } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CheckSpecService } from '@/modules/Roleplay/Rule/Service/Spec/CheckSpecService';

function stateRule(code: string, name: string): Rule {
  return {
    id: null,
    code,
    type: 'state',
    name,
    description: '',
    spaceId: 1,
    mechanics: [],
    createdAt: 1787400000,
  };
}

describe('CheckSpecService', () => {
  const service = new CheckSpecService();

  it('adds, switches and removes outcomes', () => {
    const added = service.addOutcome(undefined);
    expect(added).toEqual([{ rating: -1, state_rule_code: null }]);

    const max = service.setOutcomeKind(added, 0, 'max');
    expect(max).toEqual([{ max_rating: -1, state_rule_code: null }]);

    const rating = service.setOutcomeRating(max, 0, '-2');
    expect(rating).toEqual([{ rating: -2, state_rule_code: null }]);

    const withState = service.setOutcomeState(rating, 0, 'weakness');
    expect(withState).toEqual([{ rating: -2, state_rule_code: 'weakness' }]);

    expect(service.removeOutcome(withState, 0)).toEqual([]);
  });

  it('formats outcome rows with rule names', () => {
    const rules = [stateRule('weakness', 'Слабость'), stateRule('unconscious', 'Потеря сознания')];
    const rows = service.outcomeRows(
      [
        { rating: -1, state_rule_code: 'weakness' },
        { max_rating: -3, state_rule_code: 'unconscious' },
        { max_rating: -4, state_rule_code: null },
      ],
      rules,
    );

    expect(rows).toEqual([
      { condition: 'РУ = -1', stateName: 'Слабость' },
      { condition: 'РУ ≤ -3', stateName: 'Потеря сознания' },
      { condition: 'РУ ≤ -4', stateName: 'чисто' },
    ]);
  });

  it('matches exact and upper-bound outcomes in order', () => {
    const outcomes = [
      { rating: -1, state_rule_code: 'weakness' },
      { max_rating: -3, state_rule_code: 'unconscious' },
    ];

    expect(service.matchOutcome(outcomes, -1)).toEqual({ index: 0, outcome: outcomes[0] });
    expect(service.matchOutcome(outcomes, -2)).toBeNull();
    expect(service.matchOutcome(outcomes, -4)).toEqual({ index: 1, outcome: outcomes[1] });
    expect(service.outcomeStateCodes(outcomes)).toEqual(['weakness', 'unconscious']);
    expect(service.outcomeRank(outcomes, 0)).toBe(1);
    expect(service.outcomeRank(outcomes, 1)).toBe(2);
  });
});
