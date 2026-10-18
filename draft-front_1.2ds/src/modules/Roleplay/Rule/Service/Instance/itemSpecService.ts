import { ItemSpecService } from '@/modules/Roleplay/Rule/Service/Spec/ItemSpecService';
import { ITEM_SUBTYPE_FIELDS } from '@/modules/Roleplay/Rule/Constant/Item/ITEM_SUBTYPE_FIELDS';
import { ITEM_BLOCK_FIELDS } from '@/modules/Roleplay/Rule/Constant/Item/ITEM_BLOCK_FIELDS';
import { ITEM_WEIGHT_TICK_KILOGRAMS } from '@/modules/Roleplay/Rule/Constant/Item/ITEM_WEIGHT_TICK_KILOGRAMS';
import { ITEM_WEIGHT_SIZE } from '@/modules/Roleplay/Rule/Constant/Item/ITEM_WEIGHT_SIZE';

export const itemSpecService = new ItemSpecService(
  ITEM_SUBTYPE_FIELDS,
  ITEM_BLOCK_FIELDS,
  ITEM_WEIGHT_TICK_KILOGRAMS,
  ITEM_WEIGHT_SIZE,
);
