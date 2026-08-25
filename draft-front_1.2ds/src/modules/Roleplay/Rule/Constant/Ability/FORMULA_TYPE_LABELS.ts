import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';

/** Подписи типов формулы в FormulaInput. `actionCharacteristic` — то же «От характеристики», что и `characteristic`. */
export const FORMULA_TYPE_LABELS: Partial<Record<Formula['type'], string>> = {
  fixed: 'Число',
  characteristic: 'От характеристики',
  actionCharacteristic: 'От характеристики',
  ability_level: 'Уровень способности',
  dimensional: 'Размерное число',
};

const DEFAULT_MODES: Formula['type'][] = ['fixed', 'characteristic', 'dimensional'];

export function formulaTypeItems(
  current: Formula['type'] | undefined,
  modes: Formula['type'][] | undefined,
  hasAbilities: boolean,
): { label: string; value: Formula['type'] }[] {
  let values = modes?.length ? [...modes] : [...DEFAULT_MODES];
  if (hasAbilities && !modes?.length) values.push('ability_level');
  if (current === 'actionCharacteristic') {
    values = values.map((value) => (value === 'characteristic' ? 'actionCharacteristic' : value));
  }
  if (current && FORMULA_TYPE_LABELS[current] && !values.includes(current)) values.push(current);
  const both = values.includes('characteristic') && values.includes('actionCharacteristic');

  return values
    .filter((value): value is Formula['type'] => Boolean(FORMULA_TYPE_LABELS[value]))
    .map((value) => ({
      value,
      label: both && value === 'actionCharacteristic' ? 'Сила удара' : (FORMULA_TYPE_LABELS[value] ?? value),
    }));
}
