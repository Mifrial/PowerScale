const TRANSLIT_MAP: Record<string, string> = {
  а: 'a',
  б: 'b',
  в: 'v',
  г: 'g',
  д: 'd',
  е: 'e',
  ё: 'e',
  ж: 'zh',
  з: 'z',
  и: 'i',
  й: 'y',
  к: 'k',
  л: 'l',
  м: 'm',
  н: 'n',
  о: 'o',
  п: 'p',
  р: 'r',
  с: 's',
  т: 't',
  у: 'u',
  ф: 'f',
  х: 'h',
  ц: 'ts',
  ч: 'ch',
  ш: 'sh',
  щ: 'shch',
  ъ: '',
  ы: 'y',
  ь: '',
  э: 'e',
  ю: 'yu',
  я: 'ya',
};

function transliterate(text: string): string {
  return text
    .split('')
    .map((ch) => TRANSLIT_MAP[ch] ?? ch)
    .join('');
}

/**
 * Транслитерация в латинский код правила (`[a-z0-9-_]`): кириллица → латиница, пробелы и
 * посторонние символы → `-`. По ТР §3 code — глобальный семантический ключ латиницей
 * (допустимы `-`, `_`, цифры 0-9); при пустом вводе — автотранскрипция из имени.
 */
export function slugify(name: string): string {
  const lowered = name.toLowerCase().trim();
  const transliterated = transliterate(lowered);

  return (
    transliterated
      .replace(/[^a-z0-9_-]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .replace(/_+/g, '_')
      .replace(/-+/g, '-') || 'rule'
  );
}
