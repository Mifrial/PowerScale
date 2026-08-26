import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';

/** Подписи типов формулы в FormulaInput. `actionCharacteristic` — то же «От характеристики», что и `characteristic`. */
export const FORMULA_TYPE_LABELS: Partial<Record<Formula['type'], string>> = {
  fixed: 'Число',
  characteristic: 'От характеристики',
  actionCharacteristic: 'От характеристики',
  ability_level: 'Уровень способности',
  dimensional: 'Размерное число',
};
