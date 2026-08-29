import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { DefenseOverview } from '@/modules/Roleplay/Character/Dto/Overview/DefenseOverview';
import type { DamageTypeHook } from '@/modules/Roleplay/Game/Dto/DamageTypeHook';

export interface ApplyAttackDamageInput {
  weaponDamage: DimensionalNumberValue;
  sr: number;
  damageTypeCode: string | null;
  defense: DefenseOverview | null;
  endurance: DimensionalNumberValue | number;
  accumulatedDamage?: DimensionalNumberValue;
  hooks: DamageTypeHook[];
  /** Тип урона с галочкой «Защита не помогает» — линии defense не складываются в сопротивление. */
  defenseIgnored?: boolean;
}
