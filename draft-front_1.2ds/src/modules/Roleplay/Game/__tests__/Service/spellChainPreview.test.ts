import { describe, expect, it } from 'vitest';
import type { GamePhpSpellChain } from '@/modules/Roleplay/Game/Dto/GamePhpSpellChain';
import { SpellChainPreviewService } from '@/modules/Roleplay/Game/Service/SpellChainPreviewService';

function chain(overrides: Partial<GamePhpSpellChain> = {}): GamePhpSpellChain {
  return {
    castId: 'cast-1',
    ruleCode: 'chain-lightning',
    spellCode: 'lightning-strike',
    damageTypeCode: 'electricity',
    amount: { base: 4, size: 0 },
    min: { base: 3, size: -1 },
    damageSizePerHop: 1,
    sameTarget: 'via_other',
    retarget: 'from_last_hit',
    spellSr: 2,
    rating: 4,
    freeIpari: 2,
    sizePerExtraIpari: 1,
    damageMin: { base: 3, size: -1 },
    lastKey: 'npc:1',
    hit: ['npc:1'],
    ...overrides,
  };
}

const service = new SpellChainPreviewService();

describe('SpellChainPreviewService', () => {
  it('теряет размер за прыжок без дистанции', () => {
    expect(service.damage(chain(), 0)).toEqual({ base: 4, size: -1 });
  });

  it('снижает урон по ипари до потери размера за прыжок', () => {
    expect(service.damage(chain({ min: { base: 3, size: -2 } }), 3)).toEqual({ base: 4, size: -2 });
  });

  it('обрывает прыжок ниже минимума цепи', () => {
    expect(service.damage(chain({ amount: { base: 3, size: -1 } }), 0)).toBeNull();
  });

  it('обрывает прыжок ниже минимума урона по дистанции', () => {
    expect(service.damage(chain({ amount: { base: 4, size: -1 } }), 3)).toBeNull();
  });

  it('без данных о снижении считает только размер за прыжок', () => {
    expect(service.damage(chain({ freeIpari: null, sizePerExtraIpari: null }), 10)).toEqual({
      base: 4,
      size: -1,
    });
  });
});
