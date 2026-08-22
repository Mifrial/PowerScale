import type { ItemModifierOp } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierOp';

export const ITEM_MODIFIER_OP_TYPE_OPTIONS: { title: string; value: ItemModifierOp['type'] }[] = [
  { title: 'Вес', value: 'weight' },
  { title: 'Мин. сила', value: 'min_strength' },
  { title: 'Прочность', value: 'durability' },
  { title: 'Блок', value: 'block' },
  { title: 'Защита', value: 'defense' },
  { title: 'Надёжность', value: 'armor_reliability' },
  { title: 'Макс. ловкость', value: 'max_agility' },
  { title: 'Штраф к силе', value: 'strength_penalty' },
  { title: 'Сила удара', value: 'action_strength' },
  { title: 'Сопротивление', value: 'resistance' },
];
