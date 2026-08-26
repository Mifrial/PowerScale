import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { ChatFoldChild } from '@/modules/Messages/Chat/Dto/ChatFoldChild';
import type { ChatVisibleRow } from '@/modules/Messages/Chat/Dto/ChatVisibleRow';

export class ChatFoldService {
  flattenChatFolds(
    forest: ChatFoldChild[],
    expanded: (id: string) => boolean,
    firstUnreadId: number | null,
  ): ChatVisibleRow[] {
    const rows: ChatVisibleRow[] = [];

    const walk = (nodes: ChatFoldChild[], level: number, unreadConsumed: boolean): boolean => {
      let consumed = unreadConsumed;
      for (const node of nodes) {
        if (node.type === 'message') {
          const unread = !consumed && firstUnreadId != null && node.message.id === firstUnreadId;
          if (unread) consumed = true;
          rows.push({ type: 'message', key: `m:${node.message.id}`, message: node.message, unread });
          continue;
        }
        const fold = node.fold;
        const isOpen = expanded(fold.id);
        const unreadInGroup = !consumed && firstUnreadId != null && fold.messageIds.includes(firstUnreadId);
        if (fold.chrome === 'end') {
          if (unreadInGroup) consumed = true;
          rows.push({
            type: 'panel',
            key: `f:${fold.id}`,
            foldId: fold.id,
            kind: fold.kind,
            summary: fold.summary,
            expanded: isOpen,
            level,
            messages: isOpen ? this.collectMessages(fold.children) : [],
            unread: !isOpen && unreadInGroup,
            unreadMessageId: isOpen && unreadInGroup ? firstUnreadId : null,
          });
          continue;
        }
        const headerUnread =
          unreadInGroup && firstUnreadId != null && !this.containsVisibleMessage(fold.children, firstUnreadId);
        const chrome: ChatVisibleRow = {
          type: 'chrome',
          key: `f:${fold.id}`,
          foldId: fold.id,
          kind: fold.kind,
          summary: fold.summary,
          expanded: isOpen,
          level,
          tone: fold.tone,
          variant: fold.variant,
          unread: false,
        };
        if ((!isOpen && unreadInGroup) || (isOpen && headerUnread)) {
          chrome.unread = true;
          consumed = true;
        }
        rows.push(chrome);
        if (isOpen) consumed = walk(fold.children, level + 1, consumed);
      }

      return consumed;
    };

    walk(forest, 0, false);

    return rows;
  }

  private containsVisibleMessage(nodes: ChatFoldChild[], id: number): boolean {
    for (const node of nodes) {
      if (node.type === 'message' && node.message.id === id) return true;
      if (node.type === 'fold' && this.containsVisibleMessage(node.fold.children, id)) return true;
    }

    return false;
  }

  private collectMessages(nodes: ChatFoldChild[]): ChatMessage[] {
    const messages: ChatMessage[] = [];
    for (const node of nodes) {
      if (node.type === 'message') messages.push(node.message);
      else messages.push(...this.collectMessages(node.fold.children));
    }

    return messages;
  }
}
