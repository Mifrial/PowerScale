import type { ItemSpecBase } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpecBase';

export interface ItemLabels {
  category: Record<ItemSpecBase['category'], string>;
  subtype: Record<'weapon' | 'armor' | 'shield', string>;
}

export const ITEM_LABELS: ItemLabels = {
  category: {
    money: 'Деньги',
    equipment: 'Снаряжение',
    other: 'Прочее',
  },
  subtype: {
    weapon: 'Оружие',
    armor: 'Броня',
    shield: 'Щит',
  },
};
