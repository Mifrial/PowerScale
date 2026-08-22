import type { ChronicleRef } from '@/modules/Roleplay/Game/Dto/ChronicleRef';
import { INLINE_CONTENT_TOKEN_RE } from '@/modules/Messages/Chat/init';

/**
 * Сегменты содержимого летописи: текст и инлайн-ссылки `[[character:id]]` / `[[npc:id]]`
 * (тот же синтаксис, что и у чата). Токены других типов не поддерживаются и не отображаются.
 */
export type ChronicleSegment = { kind: 'text'; text: string } | { kind: 'ref'; ref: ChronicleRef };

export function parseChronicleContent(content: string): ChronicleSegment[] {
  const segments: ChronicleSegment[] = [];
  let last = 0;
  for (const match of content.matchAll(INLINE_CONTENT_TOKEN_RE)) {
    const start = match.index;
    if (start > last) segments.push({ kind: 'text', text: content.slice(last, start) });
    const type = match[1];
    if (type === 'character' || type === 'npc') {
      const id = Number(match[2].trim());
      if (Number.isInteger(id) && id > 0) segments.push({ kind: 'ref', ref: { kind: type, id } });
    }
    last = start + match[0].length;
  }
  if (last < content.length) segments.push({ kind: 'text', text: content.slice(last) });

  return segments;
}

/** Уникальные ссылки из содержимого (источник `related` записи — на границе «бэка»). */
export function chronicleRefsFromContent(content: string): ChronicleRef[] {
  const refs: ChronicleRef[] = [];
  const seen = new Set<string>();
  for (const segment of parseChronicleContent(content)) {
    if (segment.kind !== 'ref') continue;
    const key = `${segment.ref.kind}:${segment.ref.id}`;
    if (seen.has(key)) continue;
    seen.add(key);
    refs.push({ ...segment.ref });
  }

  return refs;
}
