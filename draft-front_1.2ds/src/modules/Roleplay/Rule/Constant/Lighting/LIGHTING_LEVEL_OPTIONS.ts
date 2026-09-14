import type { LightingLevel } from '@/modules/Roleplay/Rule/Enum/LightingLevel';

/** Подписи уровней освещения для гранта чувства. */
export const LIGHTING_LEVEL_OPTIONS: { title: string; value: LightingLevel }[] = [
  { title: 'Хорошее освещение', value: 'good' },
  { title: 'Слабое освещение', value: 'dim' },
  { title: 'Минимальное освещение', value: 'minimal' },
  { title: 'Без освещения', value: 'none' },
];
