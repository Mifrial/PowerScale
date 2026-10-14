import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/** Запись порядка из game.rollInitiative. */
export interface GameInitiativeOrderEntry {
  type: 'character' | 'npc';
  id: number;
  difficulty: DimensionalNumberValue | null;
  roll: DimensionalNumberValue | null;
  success: boolean | null;
  rating: number | null;
}
