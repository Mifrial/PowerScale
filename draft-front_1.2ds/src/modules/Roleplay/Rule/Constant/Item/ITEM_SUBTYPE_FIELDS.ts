import type { ItemSpecDraft } from '../../Dto/Item/ItemSpecDraft'

/** Манифест: какой подтип управляет каким блоком предмета. */
export const ITEM_SUBTYPE_FIELDS: Record<string, (keyof ItemSpecDraft)[]> = {
  weapon: ['weapon'],
  armor: ['armor'],
  shield: ['shield'],
}
