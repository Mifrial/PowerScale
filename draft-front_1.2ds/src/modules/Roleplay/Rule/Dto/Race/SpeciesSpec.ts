import type { RaceAbilityRef } from '@/modules/Roleplay/Rule/Dto/Race/RaceAbilityRef';

/** Вид/Подвид (type='species') — узел дерева рас; контента не несёт, кроме наследуемых способностей. */
export interface SpeciesSpec {
  parent_race_code: string | null;
  abilities: RaceAbilityRef[];
}
