import type { ItemModifierOp } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierOp';

export const ITEM_MODIFIER_OP_TYPE_OPTIONS: { title: string; value: ItemModifierOp['type'] }[] = [
  { title: 'Вес', value: 'weight' },
  { title: 'Мин. сила', value: 'min_strength' },
  { title: 'Прочность', value: 'durability' },
  { title: 'Блок', value: 'block' },
  { title: 'Защита', value: 'defense' },
  { title: 'Надёжность', value: 'armor_reliability' },
  { title: 'Модификатор характеристики', value: 'characteristic_modify' },
  { title: 'Потолок характеристики', value: 'characteristic_limit' },
  { title: 'Сила удара', value: 'action_strength' },
  { title: 'Сопротивление', value: 'resistance' },
  { title: 'Признак', value: 'keyword' },
  { title: 'Мин. ОД', value: 'min_action_cost' },
  { title: 'Проводник магии', value: 'magic_conductor' },
  { title: 'Помеха/преимущество', value: 'advantage' },
  { title: 'Помеха на проверку', value: 'check_advantage' },
];
