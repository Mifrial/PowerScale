import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface AttackCalcPayload {
  raw: number;
  damage: DimensionalNumberValue;
  damageTypeName: string;
  resistance: number;
  defenseIgnored: boolean;
  attackSrLabel: string;
  stun: number | null;
  wound: number | null;
  knockout: boolean;
  cuttingWound: number | null;
}
