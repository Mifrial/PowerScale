import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { SpellDuration } from '@/modules/Roleplay/Rule/Dto/Ability/SpellDuration';
import type { SpellValue } from '@/modules/Roleplay/Rule/Dto/Ability/SpellValue';

/**
 * Подпись режима длительности заклинания: у поддержания всегда указана мощь.
 */
export class SpellDurationLabelService {
  action(duration: SpellDuration): string {
    if (duration.type === 'sustained') {
      return `Поддержание(Мощь: ${this.powerLabel(duration.power)})`;
    }
    const base =
      duration.type === 'instant' ? 'мгновенно' : duration.type === 'lingering' ? 'длительно' : 'обновляемое';
    if (duration.type === 'instant' || !duration.limit) return base;
    const unitLabel = ({ turn: 'ход', minute: 'мин', hour: 'час' } as Record<string, string>)[duration.limit.unit];
    const value =
      typeof duration.limit.value === 'number'
        ? String(duration.limit.value)
        : new DimensionalNumber(duration.limit.value).toString();

    return `${base} (${value} ${unitLabel ?? duration.limit.unit})`;
  }

  private powerLabel(value: SpellValue): string {
    if ('parameter_code' in value) return value.parameter_code;

    return new DimensionalNumber(value).toString();
  }
}
