import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { ChatFoldChild } from '@/modules/Messages/Chat/Dto/ChatFoldChild';
import type { ChatFoldNode } from '@/modules/Messages/Chat/Dto/ChatFoldNode';
import { ATTACK_CALC_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Attack/ATTACK_CALC_ATTACHMENT_TYPE';
import {
  COMBAT_CHAT_ATTACK,
  COMBAT_CHAT_INITIATIVE,
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
    if (kind === COMBAT_CHAT_INITIATIVE) return COMBAT_CHAT_ROUND;
    if (kind === COMBAT_CHAT_TURN) return COMBAT_CHAT_ROUND;

    return COMBAT_CHAT_ROUND;
  }

  private injurySummary(text: string): string | null {
    if (text.includes('не получает увечье')) return null;
    const match = text.match(/^(.*) получает(?: постоянное)? увечье с силой (\d+)/m);
    if (!match) return null;
    const target = match[1].trim();
    const attrs: string[] = [];
    if (text.includes('постоянное увечье')) attrs.push('постоянное');
    if (text.includes('обезображивает')) attrs.push('обезображивающее');
    if (text.includes('смертельно')) attrs.push('смертельное');
    const adj = attrs.length > 0 ? `${attrs.join(' ')} ` : '';

    return `${target} получает ${adj}увечье силой ${match[2]}.`;
  }

  private declineSummary(text: string): string | null {
    const match = text.match(/^(.*) не выдерживает истощение[^\n]*— (Слабость|Обессилен|Потеря сознания)\./m);
    if (!match) return null;
    const target = match[1].trim();
    const verb = match[2] === 'Слабость' ? 'ослаблен' : match[2] === 'Обессилен' ? 'обессилен' : 'потерял сознание';

    return `${target} ${verb}!`;
  }

  private compact(text: string): string {
    return text.replace(/\s+/g, ' ').trim();
  }

  private exhaustionAmount(text: string): number {
    const match = text.match(/наносит (\d+) истощения/);

    return match ? Number(match[1]) : 0;
  }

  private woundAmount(text: string): number {
    const match = text.match(/(\d+) рану/);

    return match ? Number(match[1]) : 0;
  }

  private isSpellEffect(text: string): boolean {
    return text.includes(' бьёт по ') && text.includes('наносит');
  }

  private isHitConnect(text: string): boolean {
    return text.includes('попадает') || text.includes('промахивается');
  }

  private isHitResult(message: ChatMessage): boolean {
    if (this.isSpellEffect(message.content)) return false;

    return (
      message.attachments.some((attachment) => attachment.type === ATTACK_CALC_ATTACHMENT_TYPE) ||
      this.isHitConnect(message.content)
    );
  }

  private isSpellOutcome(text: string): boolean {
    return (
      text.includes('успешно сотворил') ||
      text.includes('не смог сотворить') ||
      text.includes('уходит в молоко') ||
      text.includes('Сотворение провалилось')
    );
  }

  private combinedHitSummary(messages: ChatMessage[]): string | null {
    const miss = messages.find((message) => message.content.includes('промахивается'));
    if (miss?.content) return this.compact(miss.content);
    const hit = messages.find((message) => this.isHitConnect(message.content) && !this.isSpellEffect(message.content));
    const effect = messages.find((message) => this.isSpellEffect(message.content));
    const exhaustion = this.exhaustionAmount(hit?.content ?? '') + this.exhaustionAmount(effect?.content ?? '');
    const wound = this.woundAmount(hit?.content ?? '') + this.woundAmount(effect?.content ?? '');
    const connect = hit?.content.match(/^(.*) попадает по (.*?)(?: с \d+ РУ)?(?: и наносит .*?)?!/);
    const fromEffect = effect?.content.match(/^(.*) бьёт по (.*) и наносит /);
    const attacker = connect?.[1] ?? null;
    const defender = connect?.[2] ?? fromEffect?.[2] ?? null;
    if (attacker && defender && exhaustion > 0) {
      const bits = [`${exhaustion} истощения`];
      if (wound > 0) bits.push(`${wound} рану`);

      return `${this.compact(attacker)} попадает по ${this.compact(defender)} и наносит ${bits.join(' и ')}!`;
    }
    if (hit?.content) return this.compact(hit.content);
    if (effect?.content) return this.compact(effect.content);

    return null;
  }

  attackFoldSummary(messages: ChatMessage[]): string {
    const joined = messages.map((message) => message.content).join('\n');
    const spell = messages.find((message) => this.isSpellOutcome(message.content));
    const hasSpell = Boolean(spell) || messages.some((message) => this.isSpellEffect(message.content));
    const bits: string[] = [];
    if (spell?.content) bits.push(this.compact(spell.content));
    if (hasSpell) {
      const hit = this.combinedHitSummary(messages);
      if (hit) bits.push(hit);
    } else {
      const hit = [...messages].reverse().find((message) => this.isHitResult(message));
      if (hit?.content) bits.push(this.compact(hit.content));
    }
    const injury = this.injurySummary(joined);
    if (injury) bits.push(injury);
    const decline = this.declineSummary(joined);
    if (decline) bits.push(decline);
    if (bits.length === 0) return 'Атака';

    return bits.join(' ');
  }

  private initiativeFoldSummary(messages: ChatMessage[]): string {
    const order = [...messages].reverse().find((message) => message.content.includes('Порядок инициативы'));
    if (order?.content) return order.content.replace(/\s+/g, ' ').trim();
    const titled = messages.find((message) => message.content.includes('Проверка на инициативу'));
    if (titled?.content) return titled.content.replace(/\s+/g, ' ').trim();

    return 'Проверка на инициативу';
  }

  private foldSummary(kind: string, header: ChatMessage | undefined, body: ChatMessage[]): string {
    if (kind === COMBAT_CHAT_ATTACK) return this.attackFoldSummary(header ? [header, ...body] : body);
    if (kind === COMBAT_CHAT_INITIATIVE) return this.initiativeFoldSummary(header ? [header, ...body] : body);
    if (header?.content) return header.content;
    if (kind === COMBAT_CHAT_TURN) return 'Ход';
    if (kind === COMBAT_CHAT_ROUND) return 'Раунд';

    return header?.content || 'Свёртка';
  }

  private toNode(acc: Map<string, CombatChatFoldGroup>, group: CombatChatFoldGroup): ChatFoldNode {
    const header =
      group.kind === COMBAT_CHAT_ATTACK || group.kind === COMBAT_CHAT_INITIATIVE
        ? undefined
        : group.messages.find((message) => message.kind != null);
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
      chrome: group.kind === COMBAT_CHAT_ATTACK || group.kind === COMBAT_CHAT_INITIATIVE ? 'end' : 'start',
      tone: group.kind === COMBAT_CHAT_ROUND ? 'highlighted' : 'default',
      variant: group.kind === COMBAT_CHAT_ATTACK || group.kind === COMBAT_CHAT_INITIATIVE ? 'block' : 'divider',
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
