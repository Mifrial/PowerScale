import type { DimensionalFormula } from '@/modules/Roleplay/Rule/Dto/Ability/DimensionalFormula';
import type { ScalarFormula } from '@/modules/Roleplay/Rule/Dto/Ability/ScalarFormula';

/**
 * JSON-узел формулы. Ожидаемый kind задаёт поле-потребитель, а не этот union.
 */
export type Formula = ScalarFormula | DimensionalFormula;
