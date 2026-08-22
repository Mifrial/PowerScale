import type { CharacteristicOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacteristicOverview';
import type { ResourceOverview } from '@/modules/Roleplay/Character/Dto/Overview/ResourceOverview';
import type { AbilityOverview } from '@/modules/Roleplay/Character/Dto/Overview/AbilityOverview';
import type { MiscItemOverview } from '@/modules/Roleplay/Character/Dto/Overview/MiscItemOverview';
import type { InventoryItemOverview } from '@/modules/Roleplay/Character/Dto/Overview/InventoryItemOverview';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { DefenseOverview } from '@/modules/Roleplay/Character/Dto/Overview/DefenseOverview';
import type { CombatOverview } from '@/modules/Roleplay/Character/Dto/Overview/CombatOverview';
import type { StateEntryOverview } from '@/modules/Roleplay/Character/Dto/Overview/StateOverview';

export interface CharacterOverview {
  characteristics: CharacteristicOverview[];
  combat: CombatOverview | null;
  resources: ResourceOverview[];
  abilities: AbilityOverview[];
  misc: MiscItemOverview[];
  inventory: InventoryItemOverview[];
  defense: DefenseOverview | null;
  attacks: AttackOverview[];
  states: StateEntryOverview[];
}
