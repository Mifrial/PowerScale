import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface AttackResistanceLayer {
  itemName: string;
  kind: 'defense' | 'resistance';
  value: number;
  durability: number;
  sourceLabel: string | null;
  /** Источник слоя: source_code линии или код предмета. */
  sourceKey?: string;
  ignored: boolean;
  /** sr — надёжность ≤ РУ; defense_flag — тип игнорирует защиту; source — слабее того же источника; kept — вошло в сумму. */
  reason: 'sr' | 'defense_flag' | 'source' | 'kept';
}

export interface AttackCalcPayload {
  raw: number;
  damage: DimensionalNumberValue;
  damageTypeName: string;
  /** Сумма учтённых слоёв до пробития. */
  resistance: number;
  /** Пробитие оружия; в расчёте уменьшает защиту, не ниже 0. */
  penetration?: number;
  /** Смягчение уклона после (урон − сопротивление) × РУ. */
  dodgeSoak?: number;
  durabilityShave?: number;
  endurance: DimensionalNumberValue;
  defenseIgnored: boolean;
  attackSrLabel: string;
  appliedSr: number | null;
  srCap: number | null;
  heading: string | null;
  stun: number | null;
  shock: number | null;
  exhaustion: number;
  remainingHpDamage: number;
  wound: number | null;
  knockout: boolean;
  cuttingWound: number | null;
  layers: AttackResistanceLayer[];
}
