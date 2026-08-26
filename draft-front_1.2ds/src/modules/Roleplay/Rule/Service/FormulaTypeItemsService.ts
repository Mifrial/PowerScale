import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';
import { FORMULA_TYPE_LABELS } from '@/modules/Roleplay/Rule/Constant/Ability/FORMULA_TYPE_LABELS';
import { FORMULA_TYPE_DEFAULT_MODES } from '@/modules/Roleplay/Rule/Constant/Ability/FORMULA_TYPE_DEFAULT_MODES';

export class FormulaTypeItemsService {
  constructor(
    private readonly labels: Partial<Record<Formula['type'], string>> = FORMULA_TYPE_LABELS,
    private readonly defaultModes: Formula['type'][] = FORMULA_TYPE_DEFAULT_MODES,
  ) {}

  formulaTypeItems(
    current: Formula['type'] | undefined,
    modes: Formula['type'][] | undefined,
    hasAbilities: boolean,
  ): { label: string; value: Formula['type'] }[] {
    let values = modes?.length ? [...modes] : [...this.defaultModes];
    if (hasAbilities && !modes?.length) values.push('ability_level');
    if (current === 'actionCharacteristic') {
      values = values.map((value) => (value === 'characteristic' ? 'actionCharacteristic' : value));
    }
    if (current && this.labels[current] && !values.includes(current)) values.push(current);
    const both = values.includes('characteristic') && values.includes('actionCharacteristic');

    return values
      .filter((value): value is Formula['type'] => Boolean(this.labels[value]))
      .map((value) => ({
        value,
        label: both && value === 'actionCharacteristic' ? 'Сила удара' : (this.labels[value] ?? value),
      }));
  }
}
