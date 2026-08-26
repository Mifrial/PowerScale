import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Enum/HitDefenseReaction';

export interface HitRollInput {
  attackerLabel: string;
  defenderLabel: string;
  attackerKey?: CombatEntityKey;
  defenderKey?: CombatEntityKey;
  attack: Pick<AttackOverview, 'itemName' | 'profileType' | 'accuracy' | 'reach' | 'falloff'>;
  attackerOverview: CharacterOverview | null;
  defenderOverview: CharacterOverview | null;
  reaction: HitDefenseReaction;
  /** Эффективность защиты (уклон/блок). Игнор не использует. */
  defenseEfficiency?: DimensionalNumberValue | null;
  attackerAdv?: number;
  defenderAdv?: number;
  distanceIpari?: number | null;
  cover?: number;
  flank?: boolean;
  turn?: boolean;
}
