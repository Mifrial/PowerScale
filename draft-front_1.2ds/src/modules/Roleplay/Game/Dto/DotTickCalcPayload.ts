import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { AttackResistanceLayer } from '@/modules/Roleplay/Game/Dto/AttackCalcPayload';

export interface DotTickCalcPayload {
  label: string;
  raw: number;
  hpDamage: number;
  damage: DimensionalNumberValue;
  damageTypeName: string;
  resistance: number;
  defenseIgnored: boolean;
  exhaustion: number;
  layers: AttackResistanceLayer[];
}
