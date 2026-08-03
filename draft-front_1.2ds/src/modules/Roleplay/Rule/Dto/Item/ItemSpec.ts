import type { ItemSpecBase } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpecBase';
import type { WeaponBlock } from '@/modules/Roleplay/Rule/Dto/Item/WeaponBlock';
import type { ArmorBlock } from '@/modules/Roleplay/Rule/Dto/Item/ArmorBlock';
import type { ShieldBlock } from '@/modules/Roleplay/Rule/Dto/Item/ShieldBlock';

/** Чистый слой: блоки подтипов опциональны, но типизированы. */
export interface ItemSpec extends ItemSpecBase {
  weapon?: WeaponBlock;
  armor?: ArmorBlock;
  shield?: ShieldBlock;
}
