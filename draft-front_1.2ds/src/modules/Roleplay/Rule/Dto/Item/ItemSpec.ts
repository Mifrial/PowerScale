import type { ItemSpecBase } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpecBase';
import type { BlockProfile } from '@/modules/Roleplay/Rule/Dto/Item/BlockProfile';
import type { WeaponBlock } from '@/modules/Roleplay/Rule/Dto/Item/WeaponBlock';
import type { ArmorBlock } from '@/modules/Roleplay/Rule/Dto/Item/ArmorBlock';
import type { ShieldBlock } from '@/modules/Roleplay/Rule/Dto/Item/ShieldBlock';

/** Чистый слой: блоки подтипов опциональны, но типизированы. */
export interface ItemSpec extends ItemSpecBase {
  block_profile?: BlockProfile | null;
  weapon?: WeaponBlock;
  armor?: ArmorBlock;
  shield?: ShieldBlock;
}
