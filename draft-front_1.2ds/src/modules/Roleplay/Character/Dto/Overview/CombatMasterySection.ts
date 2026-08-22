import type { CharacteristicOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacteristicOverview';

/** Секция мастерства (ближний/дальний бой): базовый стат + тайлы экипированного оружия семьи. */
export interface CombatMasterySection {
  stat: CharacteristicOverview;
  weapons: CharacteristicOverview[];
}
