import { describe, it, expect } from 'vitest';
import {
  isAcquiredAbility,
  isAttackAbility,
  isPhysicalDevelopmentAbility,
} from '@/modules/Roleplay/Character/Utils/developmentCategory';
import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';

const base = (over: Partial<EditorAbility>): EditorAbility =>
  ({
    ruleId: 'rule-x',
    code: 'x',
    name: 'X',
    type: null,
    description: '',
    keywordIds: [],
    zones: [],
    level: 0,
    levels: [],
    automatic: false,
    gifted: false,
    racial: false,
    visible: true,
    characteristic: false,
    characteristicCode: null,
    groupCode: null,
    parentCode: null,
    parameters: [],
    multiple: false,
    domainRef: null,
    instances: [],
    domainOptions: [],
    ...over,
  }) as EditorAbility;

describe('developmentCategory', () => {
  it('«Атаки» — по признаку Атака (keyword 71)', () => {
    expect(isAttackAbility(base({ keywordIds: [64, 71] }))).toBe(true);
    expect(isAttackAbility(base({ keywordIds: [64] }))).toBe(false);
  });

  it('«Физическое развитие» — только раздел «Тело» (keyword 61)', () => {
    expect(isPhysicalDevelopmentAbility(base({ keywordIds: [61] }))).toBe(true);
    expect(isPhysicalDevelopmentAbility(base({ keywordIds: [64] }))).toBe(false);
    expect(isPhysicalDevelopmentAbility(base({ keywordIds: [61, 56] }))).toBe(true);
  });

  it('«Приобретённые» — куплено, дар или авто', () => {
    expect(isAcquiredAbility(base({ level: 2 }))).toBe(true);
    expect(isAcquiredAbility(base({ level: 0, gifted: true }))).toBe(true);
    expect(isAcquiredAbility(base({ level: 0, automatic: true }))).toBe(true);
    expect(isAcquiredAbility(base({ level: 0 }))).toBe(false);
  });
});
