import type { ChatInlineRendererContext } from '@/modules/Messages/Chat/Dto/ChatInlineRendererContext';
import type { ChatRulesContext } from '@/modules/Messages/Chat/Dto/ChatRulesContext';

/** Data-срез пакета донора — без колбэков ввода. */
export function chatInlineRendererContext(context: ChatRulesContext | null): ChatInlineRendererContext | null {
  if (!context) return null;

  return {
    tokenLabels: context.tokenLabels,
    spaceId: context.spaceId,
    rulesRevision: context.rulesRevision,
  };
}
