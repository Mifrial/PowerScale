import type { ItemSpecBase } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpecBase'

export const ITEM_CATEGORIES: { label: string; value: ItemSpecBase['category'] }[] = [
  { label: 'Деньги', value: 'money' },
  { label: 'Снаряжение', value: 'equipment' },
  { label: 'Прочее', value: 'other' },
]
