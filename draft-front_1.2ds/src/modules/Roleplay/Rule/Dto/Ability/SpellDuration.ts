import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { SpellValue } from '@/modules/Roleplay/Rule/Dto/Ability/SpellValue';

export type SpellDuration =
  | { type: 'instant' }
  | {
      type: 'lingering';
      limit?: { value: DimensionalNumberValue | number; unit: 'turn' | 'minute' | 'hour' };
    }
  | {
      type: 'refreshable';
      action_cost: DimensionalNumberValue | number;
      limit?: { value: DimensionalNumberValue | number; unit: 'turn' | 'minute' | 'hour' };
    }
  | {
      type: 'sustained';
      /** Мощь поддержания; в первый ход равна мощи сотворения, дальше можно менять. */
      power: SpellValue;
      limit?: { value: DimensionalNumberValue | number; unit: 'turn' | 'minute' | 'hour' };
    };
