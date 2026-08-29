import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface AttackResistanceLayer {
  itemName: string;
  kind: 'defense' | 'resistance';
  value: number;
  durability: number;
  sourceLabel: string | null;
  ignored: boolean;
  /** sr — надёжность ≤ РУ; defense_flag — тип игнорирует защиту; kept — вошло в сумму. */
  reason: 'sr' | 'defense_flag' | 'kept';
}

export interface AttackCalcPayload {
  raw: number;
  damage: DimensionalNumberValue;
  damageTypeName: string;
  resistance: number;
  endurance: DimensionalNumberValue;
  defenseIgnored: boolean;
  attackSrLabel: string;
  stun: number | null;
  exhaustion: number;
  remainingHpDamage: number;
  wound: number | null;
  knockout: boolean;
  cuttingWound: number | null;
  layers: AttackResistanceLayer[];
}
