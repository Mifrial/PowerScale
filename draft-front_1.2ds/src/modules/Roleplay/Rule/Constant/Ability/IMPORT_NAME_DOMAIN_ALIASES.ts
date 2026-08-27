/**
 * Слова из хвоста импортного имени («( субъект )», «( Язык , х из 3)») → domain_ref.
 * Неизвестное слово в скобках — не схема импорта, имя не трогаем.
 */
export const IMPORT_NAME_DOMAIN_ALIASES: Record<string, string> = {
  язык: 'language',
  вид: 'species',
  культура: 'culture',
  регион: 'region',
  субъект: 'subject',
  инструмент: 'instrument',
  оружие: 'weapon-family',
};
