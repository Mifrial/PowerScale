/**
 * Фолбэк словаря домена, если в ревизии ещё нет детей check-communication.
 * Актуальный список — правила type check с parent_check_code = check-communication.
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
