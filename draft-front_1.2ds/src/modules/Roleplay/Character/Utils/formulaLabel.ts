import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';

/**
 * Человекочитаемая формула правила: «Сила», «Сила − 1», «число 5», «уровень Владение мечом»,
 * «Сила × 3» (actionCharacteristic). Для actionCharacteristic можно добавить действие в скобках
 * («Сила − 4 (для strike)») — стиль карточки атак овевью.
 */
export function formulaLabel(
  formula: Formula,
  resolveName: (code: string) => string | null,
  withActionSuffix = false,
): string {
  switch (formula.type) {
    case 'fixed':
      return `число ${formula.value}`;
    case 'characteristic': {
      const name = resolveName(formula.characteristic_code) ?? formula.characteristic_code;
      if (formula.modifier === 0) return name;
      const sign = formula.modifier > 0 ? ' + ' : ' − ';

      return `${name}${sign}${Math.abs(formula.modifier)}`;
    }
    case 'ability_level': {
      const name = resolveName(formula.ability_code) ?? formula.ability_code;

      return `уровень ${name}`;
    }
    case 'dimensional':
      return `число ${new DimensionalNumber({ base: formula.base, size: formula.size }).toString()}`;
    case 'parameter':
      return `параметр «${formula.parameter_code}» × ${formula.per_unit}`;
    case 'characteristic_size':
      return resolveName(formula.characteristic_code) ?? formula.characteristic_code;
    case 'characteristic_size_gap': {
      const from = resolveName(formula.characteristic_code_from) ?? formula.characteristic_code_from;
      const to = resolveName(formula.characteristic_code_to) ?? formula.characteristic_code_to;

      return `${from} − ${to}`;
    }
    case 'actionCharacteristic': {
      const name = resolveName(formula.characteristic) ?? formula.characteristic;
      const delta = formula.modifier.reduce((sum, entry) => sum + entry.delta, 0);
      const action = withActionSuffix ? ` (для ${formula.action})` : '';
      const base = delta === 0 ? `${name}${action}` : `${name} ${delta > 0 ? '+ ' : '− '}${Math.abs(delta)}${action}`;

      return formula.multiplier ? `${base} × ${formula.multiplier}` : base;
    }
  }
}
