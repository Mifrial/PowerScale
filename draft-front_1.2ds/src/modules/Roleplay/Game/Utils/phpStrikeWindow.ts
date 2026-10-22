/**
 * Режим окна удара при смене привязки.
 *
 * - `close` — окно закрылось: состояние шага сбрасываем целиком.
 * - `server` — окно перевели на серверный удар: локальное объявление
 *   сбрасываем, режим защиты включаем.
 * - `keep` — окно открыто в режиме набора удара, ведущий объявил удар сам:
 *   локальное объявление не трогаем, иначе окно вернётся в набор и
 *   повторное «На одобрение» бьёт по уже открытому удару.
 */
export type PhpStrikeWindowMode = 'close' | 'server' | 'keep';

export function phpStrikeWindowMode(open: boolean, resumePhpStrike: boolean): PhpStrikeWindowMode {
  if (!open) return 'close';

  return resumePhpStrike ? 'server' : 'keep';
}
