import type { RaceAbilityRef } from '@/modules/Roleplay/Rule/Dto/Race/RaceAbilityRef';
import type { AgeRange } from '@/modules/Roleplay/Rule/Dto/Race/AgeRange';

/** Вид/Подвид (type='species') — узел дерева рас; контента не несёт, кроме наследуемых способностей. */
export interface SpeciesSpec {
  parent_race_code: string | null;
  abilities: RaceAbilityRef[];
  /** Таблица «годы → ступень возраста» (тип правила 'age'); наследуется расами по parent_race_code. */
  age_years?: AgeRange[];
}
