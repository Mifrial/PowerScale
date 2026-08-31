import { InlineContentService } from '@/modules/Messages/Chat/Service/InlineContentService';
import { INLINE_CONTENT_TOKEN_RE } from '@/modules/Messages/Chat/Constant/Chat/INLINE_CONTENT_TOKEN_RE';
import { chatInlineRendererRegistry } from '@/modules/Messages/Chat/Service/Instance/chatInlineRendererRegistry';

export const inlineContentService = new InlineContentService(INLINE_CONTENT_TOKEN_RE, (segment) => {
  const describe = chatInlineRendererRegistry.get(segment.type)?.describe;

  return describe?.(segment) ?? null;
});
