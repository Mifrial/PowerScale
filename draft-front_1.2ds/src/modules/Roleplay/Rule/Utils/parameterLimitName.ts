import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';

/**
 * Имя способности с потолком параметра расы: «Сопротивление магии X» + {x: 2}
 * → «Сопротивление магии до 2», «Кристалл X» + {x: 4↓} → «Кристалл до 4↓».
 * Размерное значение печатается как есть (база и размер), без перевода в целое:
 * 4↓ — это 2 в целых, но потолок дара задан именно парой.
 * Без параметров или без «X» в имени — исходное имя.
 */
export function parameterLimitName(name: string, parameters?: Record<string, DimensionalNumberValue>): string {
  if (!parameters) return name;
  const entry = Object.entries(parameters)[0];
  if (!entry) return name;
  const value = new DimensionalNumber(entry[1]).toString();

  return /[XХ]\s*$/.test(name) ? name.replace(/[XХ]\s*$/, `до ${value}`) : `${name} (до ${value})`;
}
