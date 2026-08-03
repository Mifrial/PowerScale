import type { RaceCharacteristic } from '@/modules/Roleplay/Rule/Dto/Race/RaceCharacteristic';
import type { RaceAbilityRef } from '@/modules/Roleplay/Rule/Dto/Race/RaceAbilityRef';

/** Раса (type='race') — играбельная, терминальная точка цепочки Вид → … → Раса. */
export interface RaceSpec {
  parent_race_code: string | null;
  cost_os: number;
  characteristics: RaceCharacteristic[];
  abilities: RaceAbilityRef[];
}
