import type { ItemSpecBase } from './ItemSpecBase'
import type { WeaponBlock } from './WeaponBlock'
import type { ArmorBlock } from './ArmorBlock'
import type { ShieldBlock } from './ShieldBlock'

/** Чистый слой: блоки подтипов опциональны, но типизированы. */
export interface ItemSpec extends ItemSpecBase {
  weapon?: WeaponBlock
  armor?: ArmorBlock
  shield?: ShieldBlock
}
