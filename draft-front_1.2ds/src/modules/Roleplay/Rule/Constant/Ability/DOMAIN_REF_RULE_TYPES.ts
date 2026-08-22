import type { RuleType } from '@/modules/Roleplay/Rule/Enum/RuleType';

/**
 * Словарь домена множественного навыка (domain_ref) → типы правил ревизии, которые его образуют.
 * Справочники живут в правилах (как виды), а не в карточке способности. Неизвестный ключ
 * (region/culture/subject/instrument) → словаря нет, домен вводится свободным текстом.
 */
export const DOMAIN_REF_RULE_TYPES: Record<string, RuleType[]> = {
  species: ['species'],
  language: ['language'],
  'weapon-family': ['weapon_family'],
};
