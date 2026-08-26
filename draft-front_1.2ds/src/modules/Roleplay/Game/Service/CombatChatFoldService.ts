import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { ChatFoldChild } from '@/modules/Messages/Chat/Dto/ChatFoldChild';
import type { ChatFoldNode } from '@/modules/Messages/Chat/Dto/ChatFoldNode';
import { ATTACK_CALC_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Attack/ATTACK_CALC_ATTACHMENT_TYPE';
import {
  COMBAT_CHAT_ATTACK,
  COMBAT_CHAT_ROUND,
  COMBAT_CHAT_TURN,
} from '@/modules/Roleplay/Game/Constant/Combat/COMBAT_CHAT_FOLD_KINDS';
import type { CombatChatFoldGroup } from '@/modules/Roleplay/Game/Dto/CombatChatFoldGroup';

export class CombatChatFoldService {
  private firstChildId(child: ChatFoldChild): number {
    if (child.type === 'message') return child.message.id;

    return child.fold.messageIds[0] ?? 0;
  }

  private parentKindOf(kind: string): string {
    if (kind === COMBAT_CHAT_ATTACK) return COMBAT_CHAT_TURN;
    if (kind === COMBAT_CHAT_TURN) return COMBAT_CHAT_ROUND;

    return COMBAT_CHAT_ROUND;
  }

  private injurySummary(text: string): string | null {
    if (text.includes('не получает увечье')) return null;
    const strength = text.match(/увечье с силой (\d+)/);
    if (!strength) return null;
    const attrs: string[] = [];
    if (text.includes('постоянное увечье')) attrs.push('постоянное');
    if (text.includes('обезображивает')) attrs.push('обезображивающее');
    if (text.includes('смертельно')) attrs.push('смертельное');
    const adj = attrs.length > 0 ? `${attrs.join(' ')} ` : '';

    return `Это наносит ${strength[1]} ${adj}увечье.`;
  }

  private declineSummary(text: string): string | null {
    const match = text.match(/^(.*) не выдерживает истощение[^\n]*— (Слабость|Обессилен|Потеря сознания)\./m);
    if (!match) return null;
    const target = match[1].trim();
    const verb = match[2] === 'Слабость' ? 'ослаблен' : match[2] === 'Обессилен' ? 'обессилен' : 'потерял сознание';

    return `${target} ${verb}!`;
  }

  attackFoldSummary(messages: ChatMessage[]): string {
    const joined = messages.map((message) => message.content).join('\n');
    const result = [...messages]
      .reverse()
      .find(
        (message) =>
          message.attachments.some((attachment) => attachment.type === ATTACK_CALC_ATTACHMENT_TYPE) ||
          message.content.includes('попадает') ||
          message.content.includes('промахивается'),
      );
    const bits: string[] = [];
    if (result?.content) bits.push(result.content.replace(/\s+/g, ' ').trim());
    const injury = this.injurySummary(joined);
    if (injury) bits.push(injury);
    const decline = this.declineSummary(joined);
    if (decline) bits.push(decline);
    if (bits.length === 0) return 'Атака';

    return bits.join(' ');
  }

  private foldSummary(kind: string, header: ChatMessage | undefined, body: ChatMessage[]): string {
    if (kind === COMBAT_CHAT_ATTACK) return this.attackFoldSummary(header ? [header, ...body] : body);
    if (header?.content) return header.content;
    if (kind === COMBAT_CHAT_TURN) return 'Ход';
    if (kind === COMBAT_CHAT_ROUND) return 'Раунд';

    return header?.content || 'Свёртка';
  }

  private toNode(acc: Map<string, CombatChatFoldGroup>, group: CombatChatFoldGroup): ChatFoldNode {
    const header =
      group.kind === COMBAT_CHAT_ATTACK ? undefined : group.messages.find((message) => message.kind != null);
    const body = header ? group.messages.filter((message) => message.id !== header.id) : group.messages;
    const childGroups = [...acc.values()].filter((candidate) => candidate.parentId === group.id);
    const children: ChatFoldChild[] = [
      ...body.map((message) => ({ type: 'message' as const, message })),
      ...childGroups.map((child) => ({ type: 'fold' as const, fold: this.toNode(acc, child) })),
    ].sort((left, right) => this.firstChildId(left) - this.firstChildId(right));
    const messageIds = [
      ...group.messages.map((message) => message.id),
      ...children.flatMap((child) => (child.type === 'fold' ? child.fold.messageIds : [child.message.id])),
    ];

    return {
      id: group.id,
      kind: group.kind,
      summary: this.foldSummary(group.kind, header, body),
      chrome: group.kind === COMBAT_CHAT_ATTACK ? 'end' : 'start',
      tone: group.kind === COMBAT_CHAT_ROUND ? 'highlighted' : 'default',
      variant: group.kind === COMBAT_CHAT_ATTACK ? 'block' : 'divider',
      children,
      messageIds,
    };
  }

  buildCombatChatFolds(messages: ChatMessage[]): ChatFoldChild[] {
    const acc = new Map<string, CombatChatFoldGroup>();
    const unthreaded: ChatMessage[] = [];
    for (const message of messages) {
      const thread = message.thread;
      if (!thread) {
        unthreaded.push(message);
        continue;
      }
      let group = acc.get(thread.id);
      if (!group) {
        group = { id: thread.id, kind: thread.kind, parentId: thread.parentId, messages: [] };
        acc.set(thread.id, group);
      }
      group.messages.push(message);
    }
    for (const group of [...acc.values()]) {
      if (!group.parentId || acc.has(group.parentId)) continue;
      acc.set(group.parentId, {
        id: group.parentId,
        kind: this.parentKindOf(group.kind),
        messages: [],
      });
    }
    const roots = [...acc.values()].filter((group) => !group.parentId || !acc.has(group.parentId));
    const mixed: ChatFoldChild[] = [
      ...unthreaded.map((message) => ({ type: 'message' as const, message })),
      ...roots.map((group) => ({ type: 'fold' as const, fold: this.toNode(acc, group) })),
    ];

    return mixed.sort((left, right) => this.firstChildId(left) - this.firstChildId(right));
  }
}
