import type { MagicStudyScope } from '@/modules/Roleplay/Rule/Enum/Ability/MagicStudyScope';

export const MAGIC_STUDY_SCOPE_OPTIONS: { title: string; value: MagicStudyScope }[] = [
  { title: 'Заклинания', value: 'spell' },
  { title: 'Навыки волшебства (не заклинания)', value: 'non_spell' },
];
