import type { AbilityTypeChipRefinement } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityTypeChipRefinement';

/** Более специфичные коды раньше: путь волшебства склеивается с признаком конкретного пути. */
export const ABILITY_TYPE_CHIP_REFINEMENTS: AbilityTypeChipRefinement[] = [
  { whenType: 'skill', keywordCode: 'magic-path', mode: 'pair' },
  { whenType: 'skill', keywordCode: 'magic', mode: 'replace' },
];
