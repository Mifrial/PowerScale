import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export type DotAdvance =
  | { kind: 'skip' }
  | { kind: 'wait'; next: CharacterStateValue }
  | {
      kind: 'tick';
      next: CharacterStateValue | null;
      strength: DimensionalNumberValue;
      damageTypeCode: string;
      label: string;
    };
