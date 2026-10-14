import { describe, expect, it } from 'vitest';
import { gameInitiativeSpecService } from '@/modules/Roleplay/Game/Service/Instance/gameInitiativeSpecService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

function rule(partial: Partial<Rule> & Pick<Rule, 'code' | 'type' | 'name'>): Rule {
  return {
    id: 1,
    spaceId: 1,
    description: '',
    mechanics: [],
    createdAt: 0,
    ...partial,
  };
}

describe('gameInitiativeSpecService', () => {
  it('берёт characteristic_code единственной карточки инициативы', () => {
    const rules = [
      rule({
        code: 'watch',
        type: 'check',
        name: 'Watch',
        spec: {
          type: 'check',
          difficulty_input: { kind: 'none' },
          allowed_modes: 'both',
          initiative: true,
          characteristic_code: 'awareness',
        },
      }),
    ];

    expect(gameInitiativeSpecService.defaultCharacteristicCode(rules)).toBe('awareness');
  });

  it('без карточки не подставляет код', () => {
    expect(gameInitiativeSpecService.defaultCharacteristicCode([])).toBe('');
  });
});
