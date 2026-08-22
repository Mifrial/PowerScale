/** Разбивает текст описания на абзацы по переводам строк (одному или нескольким подряд),
 *  тримит и отбрасывает пустые фрагменты. Пустой/пробельный текст → пустой массив. */
export function splitParagraphs(text: string): string[] {
  return text
    .split(/\n+/)
    .map((part) => part.trim())
    .filter(Boolean);
}
