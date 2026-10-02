import { describe, expect, it } from 'vitest';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { knowledgeDefenseService } from '@/modules/Roleplay/Game/Service/Instance/knowledgeDefenseService';
import { ADVANTAGE_SOURCE_TRAINING } from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';

const DEFENSE_RULE: Rule = {
  mechanics: [],
  id: null,
  code: 'zaschita-znaniem',
  type: 'ability',
  name: 'Защита знанием',
  description: '',
  spaceId: 1,
  spec: {
    type: 'skill',
    zones: {},
    requirements: [],
    grants: [],
    parent_ability_code: null,
    known_attack_defense: { delta: 1, source_code: 'training' },
  },
  createdAt: 1,
};

const BARE_DEFENSE: Rule = {
  ...DEFENSE_RULE,
  code: 'bare-defense',
  spec: {
    type: 'skill',
    zones: {},
    requirements: [],
    grants: [],
    parent_ability_code: null,
  },
};

function abilities(...codes: string[]): CharacterAbility[] {
  return codes.map((ruleCode) => ({ ruleCode, level: 1 }));
}

describe('KnowledgeDefenseService', () => {
  it('даёт +1 training, если есть навык и известна атака', () => {
    expect(
      knowledgeDefenseService.modifier(abilities('zaschita-znaniem', 'prostaya-ataka'), 'prostaya-ataka', [DEFENSE_RULE]),
    ).toEqual(
      {
        source_code: ADVANTAGE_SOURCE_TRAINING,
        source_label: 'тренировки',
        delta: 1,
      },
    );
  });

  it('не даёт бонус без навыка, без владения атакой или без кода атаки', () => {
    expect(knowledgeDefenseService.modifier(abilities('prostaya-ataka'), 'prostaya-ataka', [DEFENSE_RULE])).toBeNull();
    expect(knowledgeDefenseService.modifier(abilities('zaschita-znaniem'), 'prostaya-ataka', [DEFENSE_RULE])).toBeNull();
    expect(knowledgeDefenseService.modifier(abilities('zaschita-znaniem', 'prostaya-ataka'), null, [DEFENSE_RULE])).toBeNull();
    expect(
      knowledgeDefenseService.modifier(abilities('bare-defense', 'prostaya-ataka'), 'prostaya-ataka', [BARE_DEFENSE]),
    ).toBeNull();
  });

  it('неизвестный апгрейд не отменяет известную основную атаку', () => {
    expect(
      knowledgeDefenseService.modifier(abilities('zaschita-znaniem', 'prostaya-ataka'), 'prostaya-ataka', [DEFENSE_RULE]),
    ).not.toBeNull();
    expect(
      knowledgeDefenseService.knowsAttack(abilities('zaschita-znaniem', 'prostaya-ataka'), 'neizvestnoe-uluchshenie'),
    ).toBe(false);
  });
});
