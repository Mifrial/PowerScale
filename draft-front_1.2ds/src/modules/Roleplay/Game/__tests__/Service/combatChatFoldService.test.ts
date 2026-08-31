import { describe, expect, it } from 'vitest';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import { ATTACK_CALC_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Attack/ATTACK_CALC_ATTACHMENT_TYPE';
import {
  COMBAT_CHAT_ATTACK,
  COMBAT_CHAT_ROUND,
  COMBAT_CHAT_TURN,
} from '@/modules/Roleplay/Game/Constant/Combat/COMBAT_CHAT_FOLD_KINDS';
import { combatChatFoldService } from '@/modules/Roleplay/Game/Service/Instance/combatChatFoldService';

import { chatFoldService } from '@/modules/Messages/Chat/init';

function msg(id: number, content: string, extra: Partial<ChatMessage> = {}): ChatMessage {
  return {
    id,
    chatId: 1,
    userId: 1,
    username: 't',
    content,
    attachments: [],
    createdAt: `2026-08-25T10:00:${String(id).padStart(2, '0')}Z`,
    updatedAt: `2026-08-25T10:00:${String(id).padStart(2, '0')}Z`,
    ...extra,
  };
}

describe('buildCombatChatFolds', () => {
  it('вкладывает атаку в ход в раунд; воля остаётся в атаке', () => {
    const messages = [
      msg(1, 'до боя'),
      msg(2, 'Новый раунд: 1', {
        kind: 'highlighted',
        thread: { id: 'r1', kind: COMBAT_CHAT_ROUND },
      }),
      msg(3, 'Ходит Гаррик', { kind: 'default', thread: { id: 't1', parentId: 'r1', kind: COMBAT_CHAT_TURN } }),
      msg(4, 'совершает действие', { thread: { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK } }),
      msg(5, 'Гаррик попадает по Бородачу с 4 РУ и наносит 2 истощения!', {
        thread: { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK },
        attachments: [{ type: ATTACK_CALC_ATTACHMENT_TYPE, payload: {} }],
      }),
      msg(6, 'Бородач не выдерживает истощение (РУ -1) — Слабость.', {
        thread: { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK },
      }),
      msg(7, 'заряжаю арбалет', { thread: { id: 't1', parentId: 'r1', kind: COMBAT_CHAT_TURN } }),
      msg(8, 'получил 1 повреждение от кровопотери', { thread: { id: 't1', parentId: 'r1', kind: COMBAT_CHAT_TURN } }),
    ];
    const forest = combatChatFoldService.buildCombatChatFolds(messages);
    expect(forest[0]).toMatchObject({ type: 'message', message: { id: 1 } });
    expect(forest[1]?.type).toBe('fold');
    if (forest[1]?.type !== 'fold') return;
    const round = forest[1].fold;
    expect(round.kind).toBe(COMBAT_CHAT_ROUND);
    expect(round.summary).toBe('Новый раунд: 1');
    expect(round.children).toHaveLength(1);
    const turn = round.children[0];
    expect(turn?.type).toBe('fold');
    if (turn?.type !== 'fold') return;
    expect(turn.fold.kind).toBe(COMBAT_CHAT_TURN);
    const kinds = turn.fold.children.map((child) => (child.type === 'fold' ? child.fold.kind : 'message'));
    expect(kinds).toEqual([COMBAT_CHAT_ATTACK, 'message', 'message']);
    const attack = turn.fold.children[0];
    if (attack?.type !== 'fold') return;
    expect(attack.fold.summary).toContain('попадает');
    expect(attack.fold.summary).toContain('ослаблен');
    expect(attack.fold.summary).not.toContain('воля');
    expect(attack.fold.children).toHaveLength(3);
  });

  it('сводка атаки: сила увечья и флаги, без хвоста «увечье»', () => {
    const messages = [
      msg(1, 'Ходит Гаррик', { kind: 'default', thread: { id: 't1', kind: COMBAT_CHAT_TURN } }),
      msg(2, '[[character:1,Гаррик]] попадает по [[npc:2,Бородач]] с 4 РУ и наносит 2 истощения!', {
        thread: { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK },
        attachments: [{ type: ATTACK_CALC_ATTACHMENT_TYPE, payload: {} }],
      }),
      msg(3, '[[npc:2,Бородач]] получает постоянное увечье с силой 4.\nУвечье обезображивает.', {
        thread: { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK },
      }),
      msg(4, '[[npc:2,Бородач]] не выдерживает истощение (РУ -2) — Обессилен.', {
        thread: { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK },
      }),
    ];
    const forest = combatChatFoldService.buildCombatChatFolds(messages);
    const turn = forest[0];
    expect(turn?.type).toBe('fold');
    if (turn?.type !== 'fold') return;
    const attack = turn.fold.children[0];
    if (attack?.type !== 'fold') return;
    expect(attack.fold.summary).toBe(
      '[[character:1,Гаррик]] попадает по [[npc:2,Бородач]] с 4 РУ и наносит 2 истощения! Это наносит 4 постоянное обезображивающее увечье. [[npc:2,Бородач]] обессилен!',
    );
  });

  it('сообщения без thread остаются плоскими', () => {
    const forest = combatChatFoldService.buildCombatChatFolds([msg(1, 'привет'), msg(2, 'пока')]);
    expect(forest.every((child) => child.type === 'message')).toBe(true);
    expect(forest).toHaveLength(2);
  });

  it('live раскрывает ход, завершённая атака свёрнута', () => {
    const messages = [
      msg(1, 'Новый раунд: 1', { kind: 'highlighted', thread: { id: 'r1', kind: COMBAT_CHAT_ROUND } }),
      msg(2, 'Ходит Гаррик', { kind: 'default', thread: { id: 't1', parentId: 'r1', kind: COMBAT_CHAT_TURN } }),
      msg(3, 'Гаррик попадает!', {
        thread: { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK },
        attachments: [{ type: ATTACK_CALC_ATTACHMENT_TYPE, payload: {} }],
      }),
    ];
    const live = new Set(['r1', 't1']);
    const rows = chatFoldService.flattenChatFolds(
      combatChatFoldService.buildCombatChatFolds(messages),
      (id) => live.has(id),
      null,
    );
    expect(rows.some((row) => row.type === 'panel' && row.foldId === 'a1' && !row.expanded)).toBe(true);
    expect(rows.some((row) => row.type === 'message' && row.message.id === 3)).toBe(false);
    expect(rows.some((row) => row.type === 'chrome' && row.foldId === 't1' && row.expanded)).toBe(true);
  });
});
