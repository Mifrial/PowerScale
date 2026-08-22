/**
 * Статичные опции домена навыка (domain_ref) для справочников без типа правил. Пока нет правила
 * «проверка», типы проверок общения задаются здесь (запугивание/убеждение/обман/обольщение/торговля).
 * Ключ — значение domain_ref способности; приоритетнее справочников из DOMAIN_REF_RULE_TYPES.
 */
export const DOMAIN_STATIC_OPTIONS: Record<string, { code: string; name: string }[]> = {
  'communication-check': [
    { code: 'intimidation', name: 'Запугивание' },
    { code: 'persuasion', name: 'Убеждение' },
    { code: 'deception', name: 'Обман' },
    { code: 'seduction', name: 'Обольщение' },
    { code: 'trade', name: 'Торговля' },
  ],
};
