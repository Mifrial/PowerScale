import { describe, expect, it } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import {
  checkAllowsPairwise,
  checkAllowsSolo,
  checkVersusLabel,
  isLaunchableCheck,
  launchableChecks,
} from '@/modules/Roleplay/Rule/Utils/checkLaunch';

function check(code: string, extra: Partial<Rule> = {}): Rule {
  return {
    id: code,
    code,
    type: 'check',
    name: code,
    description: '',
    spaceId: 1,
    spec: { type: 'check', difficulty_input: { kind: 'ask' }, allowed_modes: 'both' },
    keywordIds: [],
    mechanicId: null,
    createdAt: '2026-08-22T12:00:00Z',
    ...extra,
  };
}

describe('checkLaunch', () => {
  it('скрывает инициативу, увечье и удар', () => {
    const rules = [
      check('check-simple'),
      check('check-initiative'),
      check('check-injury', {
        spec: { type: 'check', difficulty_input: { kind: 'ask' }, allowed_modes: 'solo' },
      }),
      check('check-hit'),
      { id: 'roll', code: 'roll', type: 'simple', name: 'Бросок', description: '', spaceId: 1, createdAt: '' } as Rule,
    ];
    expect(launchableChecks(rules).map((rule) => rule.code)).toEqual(['check-simple']);
    expect(isLaunchableCheck(rules[1]!)).toBe(false);
  });

  it('простая проверка первая в списке запуска', () => {
    const rules = [check('check-strength'), check('check-simple'), check('check-communication')];
    expect(launchableChecks(rules).map((rule) => rule.code)[0]).toBe('check-simple');
  });

  it('from_state: подпись «характеристика против состояния»', () => {
    const exhaustion = check('check-exhaustion', {
      spec: {
        type: 'check',
        characteristic_code: 'willpower',
        difficulty_input: { kind: 'from_state', state_code: 'exhaustion' },
        allowed_modes: 'solo',
      },
    });
    const rules: Rule[] = [
      exhaustion,
      {
        id: 'willpower',
        code: 'willpower',
        type: 'characteristic',
        name: 'Сила воли',
        description: '',
        spaceId: 1,
        createdAt: '',
      },
      {
        id: 'exhaustion',
        code: 'exhaustion',
        type: 'state',
        name: 'Истощение',
        description: '',
        spaceId: 1,
        createdAt: '',
      },
    ];
    expect(checkVersusLabel(exhaustion, rules)).toBe('Сила воли против «Истощение»');
  });

  it('allowed_modes режет соло/pairwise', () => {
    const joint = check('check-x', {
      spec: { type: 'check', difficulty_input: { kind: 'none' }, allowed_modes: 'joint' },
    });
    const solo = check('check-y', {
      spec: { type: 'check', difficulty_input: { kind: 'ask' }, allowed_modes: 'solo' },
    });
    expect(checkAllowsPairwise(joint)).toBe(true);
    expect(checkAllowsSolo(joint)).toBe(false);
    expect(checkAllowsSolo(solo)).toBe(true);
    expect(checkAllowsPairwise(solo)).toBe(false);
  });
});
