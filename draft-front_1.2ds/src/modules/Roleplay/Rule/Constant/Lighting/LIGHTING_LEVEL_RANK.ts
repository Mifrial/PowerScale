import type { LightingLevel } from '@/modules/Roleplay/Rule/Enum/LightingLevel';

/** Глубже тьма, которую чувство всё ещё считает хорошим светом — больше ранг. */
export const LIGHTING_LEVEL_RANK: Record<LightingLevel, number> = {
  good: 0,
  dim: 1,
  minimal: 2,
  none: 3,
};
