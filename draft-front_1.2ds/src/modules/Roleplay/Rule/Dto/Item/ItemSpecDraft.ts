import type { ItemSpecBase } from './ItemSpecBase'
import type { WeaponBlock } from './WeaponBlock'
import type { ArmorBlock } from './ArmorBlock'
import type { ShieldBlock } from './ShieldBlock'

/** Черновой слой редактора: все блоки подтипов опциональны. */
export interface ItemSpecDraft extends ItemSpecBase {
  weapon?: WeaponBlock
  armor?: ArmorBlock
  shield?: ShieldBlock
}
