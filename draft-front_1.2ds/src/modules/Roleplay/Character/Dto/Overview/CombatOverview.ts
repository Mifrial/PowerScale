import type { CombatMasterySection } from '@/modules/Roleplay/Character/Dto/Overview/CombatMasterySection';

/** Секция боя карточки: мастерство ближнего и дальнего боя с тайлами экипированного оружия. */
export interface CombatOverview {
  melee: CombatMasterySection | null;
  ranged: CombatMasterySection | null;
}
