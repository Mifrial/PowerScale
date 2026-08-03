import type { ItemSpecDraft } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpecDraft';

/** Манифест: какой подтип управляет каким блоком предмета. */
export const ITEM_SUBTYPE_FIELDS: Record<string, (keyof ItemSpecDraft)[]> = {
  weapon: ['weapon'],
  armor: ['armor'],
  shield: ['shield'],
};
