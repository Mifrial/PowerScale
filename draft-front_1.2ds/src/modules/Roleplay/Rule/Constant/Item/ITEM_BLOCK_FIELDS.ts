import type { ItemSpecDraft } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpecDraft';
import { ITEM_SUBTYPE_FIELDS } from '@/modules/Roleplay/Rule/Constant/Item/ITEM_SUBTYPE_FIELDS';

/** Универсум блочных полей предмета — производная от манифеста ITEM_SUBTYPE_FIELDS (union значений).
 * Используется фабрикой в prune: блок чистится, если не управляется ни одним активным подтипом. */
export const ITEM_BLOCK_FIELDS: (keyof ItemSpecDraft)[] = [...new Set(Object.values(ITEM_SUBTYPE_FIELDS).flat())];
