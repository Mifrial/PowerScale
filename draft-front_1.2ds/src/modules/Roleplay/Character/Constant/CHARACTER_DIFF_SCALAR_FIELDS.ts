import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

export const CHARACTER_DIFF_SCALAR_FIELDS = [
  'name',
  'shortDescription',
  'fullDescription',
  'raceRuleCode',
  'ageYears',
  'points',
  'money',
  'budgets',
  'ethnicityCode',
  'ethnicityText',
  'nativeLanguageCode',
  'nativeLanguageText',
] as const satisfies readonly (keyof CharacterVersion)[];
