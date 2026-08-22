import { InlineContentService } from '@/modules/Messages/Chat/Service/InlineContentService';
import { INLINE_CONTENT_TOKEN_RE } from '@/modules/Messages/Chat/Constant/Chat/INLINE_CONTENT_TOKEN_RE';
import { getInlineRenderer } from '@/modules/Messages/Chat/init';

export const inlineContentService = new InlineContentService(INLINE_CONTENT_TOKEN_RE, (segment) => {
  const describe = getInlineRenderer(segment.type)?.describe;

  return describe?.(segment) ?? null;
});
