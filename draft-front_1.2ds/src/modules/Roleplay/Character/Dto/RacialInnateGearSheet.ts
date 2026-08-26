import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';

export interface RacialInnateGearSheet {
  raceRuleId: string | null;
  inventory: InventoryItem[];
  abilities: CharacterAbility[];
}
