import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType';

/** Порядок приоритета типов при разрешении типа способности по тегам (resolveTypeFromKeywords). */
export const ABILITY_TYPE_PRECEDENCE: AbilityType[] = ['spell', 'process', 'action', 'skill', 'feature', 'trait'];
