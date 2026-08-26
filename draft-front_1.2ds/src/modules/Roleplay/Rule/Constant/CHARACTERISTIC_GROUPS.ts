import type { CharacteristicGroup } from '@/modules/Roleplay/Rule/Enum/CharacteristicGroup';

export const CHARACTERISTIC_GROUPS: { title: string; value: CharacteristicGroup }[] = [
  { title: 'Основные', value: 'primary' },
  { title: 'Важные', value: 'important' },
  { title: 'Второстепенные', value: 'secondary' },
  { title: 'Боевые', value: 'combat' },
  { title: 'Базовые', value: 'base' },
];
