import type { ChatInlineRendererContext } from '@/modules/Messages/Chat/Dto/ChatInlineRendererContext';

/** Чип без data-среза хоста скрыт; имя только из `tokenLabels` ревизии, не из каталога. */
export function ruleChipFromContext(
  code: string,
  context: ChatInlineRendererContext | undefined,
): { id: string; name: string } | null {
  const labels = context?.tokenLabels;
  if (!labels) return null;
  const name = labels[code];

  return name ? { id: code, name } : null;
}
