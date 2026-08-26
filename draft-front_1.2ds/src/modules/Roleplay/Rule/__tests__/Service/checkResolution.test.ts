import { describe, expect, it } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CheckSpec } from '@/modules/Roleplay/Rule/Dto/Check/CheckSpec';
import {
  CHECK_COMMUNICATION_CODE,
  CHECK_INJURY_CODE,
  CHECK_SIMPLE_CODE,
} from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { checkResolutionService } from '@/modules/Roleplay/Rule/Service/Instance/checkResolutionService';

function checkRule(code: string, spec: CheckSpec): Rule {
  return {
    id: code,
    code,
    type: 'check',
    name: code,
    description: '',
    spaceId: 1,
    spec,
    createdAt: '2026-08-22T12:00:00Z',
  };
}

const catalog: Rule[] = [
  {
    id: 'roll',
    code: 'roll',
    type: 'simple',
    name: 'Бросок',
    description: '',
    spaceId: 1,
    mechanic_payload: {
      type: 'roll',
      data: { sub_mechanics: ['advantage_disadvantage'], efficiency: 3 },
    },
    createdAt: '2026-08-22T12:00:00Z',
  },
  checkRule(CHECK_SIMPLE_CODE, {
    type: 'check',
    difficulty_input: { kind: 'ask' },
    allowed_modes: 'both',
    attached_rule_codes: ['rule-6-and-1', 'advantages'],
  }),
  checkRule(CHECK_COMMUNICATION_CODE, {
    type: 'check',
    parent_check_code: CHECK_SIMPLE_CODE,
    characteristic_code: 'communication',
    difficulty_input: { kind: 'ask' },
    allowed_modes: 'both',
  }),
  checkRule('deception', {
    type: 'check',
    parent_check_code: CHECK_COMMUNICATION_CODE,
    difficulty_input: { kind: 'ask' },
    allowed_modes: 'both',
  }),
  checkRule(CHECK_INJURY_CODE, {
    type: 'check',
    difficulty_input: { kind: 'ask' },
    allowed_modes: 'solo',
    attached_rule_codes: ['rule-6-and-1', 'advantages'],
  }),
];

describe('checkResolution', () => {
  it('предки обмана: обман → общение → простая', () => {
    expect(checkResolutionService.checkAncestorCodes('deception', catalog)).toEqual([
      'deception',
      CHECK_COMMUNICATION_CODE,
      CHECK_SIMPLE_CODE,
    ]);
  });

  it('грант на общение матчит обман; грант на простую — не увечье', () => {
    expect(checkResolutionService.checkMatchesGrant('deception', CHECK_COMMUNICATION_CODE, catalog)).toBe(true);
    expect(checkResolutionService.checkMatchesGrant('deception', CHECK_SIMPLE_CODE, catalog)).toBe(true);
    expect(checkResolutionService.checkMatchesGrant(CHECK_INJURY_CODE, CHECK_SIMPLE_CODE, catalog)).toBe(false);
  });

  it('пул обмана — Красноречие; подмена на запуске побеждает', () => {
    expect(checkResolutionService.resolveCheckCharacteristicCode('deception', catalog)).toBe('communication');
    expect(checkResolutionService.resolveCheckCharacteristicCode('deception', catalog, 'willpower')).toBe('willpower');
  });

  it('правила броска: обман наследует простую проверку; увечье — тот же набор, что у простой', () => {
    expect(checkResolutionService.resolveCheckAttachedRuleCodes('deception', catalog)).toEqual([
      'rule-6-and-1',
      'advantages',
    ]);
    expect(checkResolutionService.resolveCheckAttachedRuleCodes(CHECK_INJURY_CODE, catalog)).toEqual([
      'rule-6-and-1',
      'advantages',
    ]);
    expect(checkResolutionService.resolveCheckAttachedRuleCodes(CHECK_SIMPLE_CODE, catalog)).toEqual([
      'rule-6-and-1',
      'advantages',
    ]);
  });

  it('код проверки характеристики: check-strength, иначе простая', () => {
    const withStrength = [
      ...catalog,
      checkRule('check-strength', {
        type: 'check',
        parent_check_code: CHECK_SIMPLE_CODE,
        characteristic_code: 'strength',
        difficulty_input: { kind: 'ask' },
        allowed_modes: 'both',
      }),
    ];
    expect(checkResolutionService.resolveCheckCodeForCharacteristic('strength', withStrength)).toBe('check-strength');
    expect(checkResolutionService.resolveCheckCodeForCharacteristic('unknown', withStrength)).toBe(CHECK_SIMPLE_CODE);
  });
});
