import type { LanguageRole } from '@/modules/Roleplay/Rule/Enum/LanguageRole';

export const LANGUAGE_ROLE_ITEMS: { title: string; value: LanguageRole }[] = [
  { title: 'Язык (изучается)', value: 'language' },
  { title: 'Сток (семья, не изучается)', value: 'stock' },
];
