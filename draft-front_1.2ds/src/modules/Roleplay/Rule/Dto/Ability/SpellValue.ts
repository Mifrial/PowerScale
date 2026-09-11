import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/** Мощь или контроль заклинания: размерное значение либо ссылка на parameter способности. */
export type SpellValue = DimensionalNumberValue | { type: 'parameter'; parameter_code: string };
