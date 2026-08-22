import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';

/**
 * Имя способности с потолком параметра расы: «Сопротивление магии X» + {x: 2}
 * → «Сопротивление магии до 2». Без параметров или без «X» в имени — исходное имя.
 */
export function parameterLimitName(name: string, parameters?: Record<string, DimensionalNumberValue>): string {
  if (!parameters) return name;
  const entry = Object.entries(parameters)[0];
  if (!entry) return name;
  const value = new DimensionalNumber(entry[1]).toNumber();

  return /[XХ]\s*$/.test(name) ? name.replace(/[XХ]\s*$/, `до ${value}`) : `${name} (до ${value})`;
}
