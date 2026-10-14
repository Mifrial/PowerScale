import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { GameInitiativeRollMethod } from '@/modules/Roleplay/Game/Enum/GameInitiativeRollMethod';

/** Способ броска одного участника для game.rollInitiative. */
export type GameInitiativeRoll =
  | {
      type: 'character' | 'npc';
      id: number;
      method: Extract<GameInitiativeRollMethod, 'characteristic'>;
      characteristicCode?: string;
    }
  | {
      type: 'character' | 'npc';
      id: number;
      method: Extract<GameInitiativeRollMethod, 'free'>;
      diceCount: number;
      advantage?: number;
    }
  | {
      type: 'character' | 'npc';
      id: number;
      method: Extract<GameInitiativeRollMethod, 'fixed'>;
      value: DimensionalNumberValue;
    };
