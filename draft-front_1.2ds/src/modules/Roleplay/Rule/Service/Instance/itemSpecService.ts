import { ItemSpecService } from '@/modules/Roleplay/Rule/Service/Spec/ItemSpecService';
import { ITEM_SUBTYPE_FIELDS } from '@/modules/Roleplay/Rule/Constant/Item/ITEM_SUBTYPE_FIELDS';
import { ITEM_BLOCK_FIELDS } from '@/modules/Roleplay/Rule/Constant/Item/ITEM_BLOCK_FIELDS';

export const itemSpecService = new ItemSpecService(ITEM_SUBTYPE_FIELDS, ITEM_BLOCK_FIELDS);
