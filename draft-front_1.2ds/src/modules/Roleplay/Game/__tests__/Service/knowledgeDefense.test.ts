import { describe, expect, it } from 'vitest';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import { knowledgeDefenseService } from '@/modules/Roleplay/Game/Service/Instance/knowledgeDefenseService';
import { ADVANTAGE_SOURCE_TRAINING } from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';

function abilities(...codes: string[]): CharacterAbility[] {
  return codes.map((ruleCode) => ({ ruleCode, level: 1 }));
}

describe('KnowledgeDefenseService', () => {
  it('даёт +1 training, если есть навык и известна атака', () => {
    expect(knowledgeDefenseService.modifier(abilities('zaschita-znaniem', 'prostaya-ataka'), 'prostaya-ataka')).toEqual(
      {
        source_code: ADVANTAGE_SOURCE_TRAINING,
        source_label: 'тренировки',
        delta: 1,
      },
    );
  });

  it('не даёт бонус без навыка, без владения атакой или без кода атаки', () => {
    expect(knowledgeDefenseService.modifier(abilities('prostaya-ataka'), 'prostaya-ataka')).toBeNull();
    expect(knowledgeDefenseService.modifier(abilities('zaschita-znaniem'), 'prostaya-ataka')).toBeNull();
    expect(knowledgeDefenseService.modifier(abilities('zaschita-znaniem', 'prostaya-ataka'), null)).toBeNull();
  });

  it('неизвестный апгрейд не отменяет известную основную атаку', () => {
    expect(
      knowledgeDefenseService.modifier(abilities('zaschita-znaniem', 'prostaya-ataka'), 'prostaya-ataka'),
    ).not.toBeNull();
    expect(
      knowledgeDefenseService.knowsAttack(abilities('zaschita-znaniem', 'prostaya-ataka'), 'neizvestnoe-uluchshenie'),
    ).toBe(false);
  });
});
