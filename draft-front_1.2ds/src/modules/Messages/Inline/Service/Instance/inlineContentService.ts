import { InlineContentService } from '@/modules/Messages/Inline/Service/InlineContentService';
import { INLINE_CONTENT_TOKEN_RE } from '@/modules/Messages/Inline/Constant/INLINE_CONTENT_TOKEN_RE';
import { inlineRendererRegistry } from '@/modules/Messages/Inline/Service/Instance/inlineRendererRegistry';

export const inlineContentService = new InlineContentService(INLINE_CONTENT_TOKEN_RE, (segment) => {
  const describe = inlineRendererRegistry.get(segment.type)?.describe;

  return describe?.(segment) ?? null;
});
