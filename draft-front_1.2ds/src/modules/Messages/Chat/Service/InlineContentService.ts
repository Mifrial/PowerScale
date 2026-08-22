import type { InlineSegment } from '@/modules/Messages/Chat/Dto/InlineSegment';
import { INLINE_CONTENT_TOKEN_RE } from '@/modules/Messages/Chat/Constant/Chat/INLINE_CONTENT_TOKEN_RE';

type InlineToken = Extract<InlineSegment, { kind: 'token' }>;

export class InlineContentService {
  constructor(
    private readonly tokenRe: RegExp = INLINE_CONTENT_TOKEN_RE,
    private readonly tokenLabel: ((segment: InlineToken) => string | null) | null = null,
  ) {}

  parse(content: string): InlineSegment[] {
    const segments: InlineSegment[] = [];
    let last = 0;
    for (const match of content.matchAll(this.tokenRe)) {
      const start = match.index;
      if (start > last) {
        segments.push({ kind: 'text', text: content.slice(last, start) });
      }
      const params = match[2]
        .split(',')
        .map((p) => p.trim())
        .filter((p) => p.length > 0);
      segments.push({ kind: 'token', type: match[1], params });
      last = start + match[0].length;
    }
    if (last < content.length) {
      segments.push({ kind: 'text', text: content.slice(last) });
    }

    return segments;
  }

  toText(content: string): string {
    return this.parse(content)
      .map((segment) => {
        if (segment.kind === 'text') return segment.text;
        const label = this.tokenLabel?.(segment) ?? segment.params[0] ?? segment.type;

        return label;
      })
      .join('');
  }
}
