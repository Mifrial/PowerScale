import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { GamePhpSpellCastUpgrade } from '@/modules/Roleplay/Game/Dto/GamePhpSpellCastUpgrade';

/** Блок spellCast в объявлении удара касания. */
export interface GamePhpSpellCastPayload {
  spellCode: string;
  pathCode: string;
  parameters: Record<string, DimensionalNumberValue | number>;
  usedPower: {
    source:
      | { kind: 'characteristic'; code: string }
      | { kind: 'item'; inventoryId: number }
      | { kind: 'grant'; abilityCode: string };
    value: DimensionalNumberValue;
  };
  spellTarget?: { type: 'character' | 'npc'; id: number };
  upgrades?: GamePhpSpellCastUpgrade[];
}
