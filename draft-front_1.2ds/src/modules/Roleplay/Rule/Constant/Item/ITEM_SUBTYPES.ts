import type { ItemSpecDraft } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpecDraft';

export const ITEM_SUBTYPES: { label: string; value: string; field: keyof ItemSpecDraft }[] = [
  { label: 'Оружие', value: 'weapon', field: 'weapon' },
  { label: 'Броня', value: 'armor', field: 'armor' },
  { label: 'Щит', value: 'shield', field: 'shield' },
];
