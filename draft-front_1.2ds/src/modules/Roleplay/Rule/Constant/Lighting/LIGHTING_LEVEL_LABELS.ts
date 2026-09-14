import type { LightingLevel } from '@/modules/Roleplay/Rule/Enum/LightingLevel';
import { LIGHTING_LEVEL_OPTIONS } from '@/modules/Roleplay/Rule/Constant/Lighting/LIGHTING_LEVEL_OPTIONS';

/** Подпись уровня освещения по коду. */
export const LIGHTING_LEVEL_LABELS: Record<LightingLevel, string> = Object.fromEntries(
  LIGHTING_LEVEL_OPTIONS.map((option) => [option.value, option.title]),
) as Record<LightingLevel, string>;
