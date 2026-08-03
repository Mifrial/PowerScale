import type { ItemSpecBase } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpecBase'
import type { WeaponBlock } from '@/modules/Roleplay/Rule/Dto/Item/WeaponBlock'
import type { ArmorBlock } from '@/modules/Roleplay/Rule/Dto/Item/ArmorBlock'
import type { ShieldBlock } from '@/modules/Roleplay/Rule/Dto/Item/ShieldBlock'

/** Черновой слой редактора: все блоки подтипов опциональны. */
export interface ItemSpecDraft extends ItemSpecBase {
  weapon?: WeaponBlock
  armor?: ArmorBlock
  shield?: ShieldBlock
}
