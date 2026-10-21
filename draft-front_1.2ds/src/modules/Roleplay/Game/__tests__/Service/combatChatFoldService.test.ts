import { describe, expect, it } from 'vitest';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import { ATTACK_CALC_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Attack/ATTACK_CALC_ATTACHMENT_TYPE';
import {
  COMBAT_CHAT_ATTACK,
  COMBAT_CHAT_INITIATIVE,
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
    createdAt: id,
    updatedAt: id,
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
      '[[character:1,Гаррик]] попадает по [[npc:2,Бородач]] с 4 РУ и наносит 2 истощения! [[npc:2,Бородач]] получает постоянное обезображивающее увечье силой 4. [[npc:2,Бородач]] обессилен!',
    );
  });

  it('сводка одиночной атаки включает действие и реакцию', () => {
    const thread = { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK };
    const action = '[[character:1,Гаррик]] совершает действие [[rule:udar]] за 2ОД.';
    const narrative =
      '[[character:1,Гаррик]] наносит дробящий удар оружием [[rule:mech]] по [[npc:2,Бородач]]. [[npc:2,Бородач]] пытается совершить [[rule:dodge]] за 1ОД!';
    const hit = '[[character:1,Гаррик]] попадает по [[npc:2,Бородач]] с 4 РУ и наносит 2 истощения!';
    const decline = '[[npc:2,Бородач]] не выдерживает истощение (РУ -1) — Слабость.';
    const forest = combatChatFoldService.buildCombatChatFolds([
      msg(1, action, { thread }),
      msg(2, narrative, { thread }),
      msg(3, hit, {
        thread,
        attachments: [{ type: ATTACK_CALC_ATTACHMENT_TYPE, payload: {} }],
      }),
      msg(4, decline, { thread }),
    ]);
    const turn = forest[0];
    expect(turn?.type).toBe('fold');
    if (turn?.type !== 'fold') return;
    const attack = turn.fold.children[0];
    expect(attack?.type).toBe('fold');
    if (attack?.type !== 'fold') return;
    expect(attack.fold.summary).toBe(`${action} ${narrative} ${hit} [[npc:2,Бородач]] ослаблен!`);
  });

  it('сводка широкого удара: действие, реакции и все промахи', () => {
    const thread = { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK };
    const action = '[[npc:1,Тестовичок]] совершает действие [[rule:shirokiy-udar]] за 4ОД.';
    const narrative =
      '[[npc:1,Тестовичок]] наносит дробящий удар оружием [[rule:ruka]] по [[character:2,Воюн]] и [[npc:2,Вторец]]. [[character:2,Воюн]] пытается совершить [[rule:dodge]] за 1ОД, [[npc:2,Вторец]] пытается совершить [[rule:dodge]] за 1ОД!';
    const missVoyun = '[[npc:1,Тестовичок]] промахивается по [[character:2,Воюн]]!';
    const missVtor = '[[npc:1,Тестовичок]] промахивается по [[npc:2,Вторец]]!';
    const forest = combatChatFoldService.buildCombatChatFolds([
      msg(1, action, { thread }),
      msg(2, narrative, { thread }),
      msg(3, missVoyun, {
        thread,
        attachments: [{ type: ATTACK_CALC_ATTACHMENT_TYPE, payload: {} }],
      }),
      msg(4, missVtor, {
        thread,
        attachments: [{ type: ATTACK_CALC_ATTACHMENT_TYPE, payload: {} }],
      }),
    ]);
    const turn = forest[0];
    expect(turn?.type).toBe('fold');
    if (turn?.type !== 'fold') return;
    const attack = turn.fold.children[0];
    expect(attack?.type).toBe('fold');
    if (attack?.type !== 'fold') return;
    expect(attack.fold.summary).toBe(`${action} ${narrative} ${missVoyun} ${missVtor}`);
  });

  it('сводка широкого удара: все попадания, увечья и Упадок сил', () => {
    const thread = { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK };
    const action = '[[npc:1,Тестовичок]] совершает действие [[rule:shirokiy-udar]] за 4ОД.';
    const narrative =
      '[[npc:1,Тестовичок]] наносит дробящий удар оружием [[rule:ruka]] по [[character:2,Воюн]] и [[npc:2,Вторец]]. [[character:2,Воюн]] пытается совершить [[rule:dodge]] за 1ОД, [[npc:2,Вторец]] пытается совершить [[rule:dodge]] за 1ОД!';
    const hitVoyun = '[[npc:1,Тестовичок]] попадает по [[character:2,Воюн]] с 6 РУ и наносит 7 истощения!';
    const hitVtor = '[[npc:1,Тестовичок]] попадает по [[npc:2,Вторец]] с 3 РУ и наносит 2 истощения!';
    const forest = combatChatFoldService.buildCombatChatFolds([
      msg(1, action, { thread }),
      msg(2, narrative, { thread }),
      msg(3, hitVoyun, {
        thread,
        attachments: [{ type: ATTACK_CALC_ATTACHMENT_TYPE, payload: {} }],
      }),
      msg(4, '[[character:2,Воюн]] не выдерживает истощение (РУ -6) — Потеря сознания.', { thread }),
      msg(5, '[[character:2,Воюн]] получает временное увечье с силой 4.', { thread }),
      msg(6, hitVtor, {
        thread,
        attachments: [{ type: ATTACK_CALC_ATTACHMENT_TYPE, payload: {} }],
      }),
      msg(7, '[[npc:2,Вторец]] выдерживает истощение (РУ +1) — без Упадка сил.', { thread }),
      msg(8, '[[npc:2,Вторец]] получает временное увечье с силой 4.', { thread }),
    ]);
    const turn = forest[0];
    expect(turn?.type).toBe('fold');
    if (turn?.type !== 'fold') return;
    const attack = turn.fold.children[0];
    expect(attack?.type).toBe('fold');
    if (attack?.type !== 'fold') return;
    expect(attack.fold.summary).toBe(
      `${action} ${narrative} ${hitVoyun} ${hitVtor} [[character:2,Воюн]] получает временное увечье силой 4. [[npc:2,Вторец]] получает временное увечье силой 4. [[character:2,Воюн]] потерял сознание! [[npc:2,Вторец]] выдерживает истощение — без Упадка сил.`,
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

  it('сводка заклинания без удара — итог сотворения, не «Атака»', () => {
    const forest = combatChatFoldService.buildCombatChatFolds([
      msg(1, 'Ходит Бородач', { kind: 'default', thread: { id: 't1', kind: COMBAT_CHAT_TURN } }),
      msg(2, '[[character:1,Старый Бородач]] творит заклинание [[rule:lightning-generator]] за 4ОД!', {
        thread: { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK },
      }),
      msg(3, '[[character:1,Старый Бородач]] успешно сотворил [[rule:lightning-generator]] за 4ОД!', {
        thread: { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK },
      }),
    ]);
    const turn = forest[0];
    expect(turn?.type).toBe('fold');
    if (turn?.type !== 'fold') return;
    const attack = turn.fold.children[0];
    if (attack?.type !== 'fold') return;
    expect(attack.fold.summary).toBe(
      '[[character:1,Старый Бородач]] успешно сотворил [[rule:lightning-generator]] за 4ОД!',
    );
  });

  it('сводка удара с провалом сотворения держит оба факта', () => {
    const forest = combatChatFoldService.buildCombatChatFolds([
      msg(1, 'Ходит Бородач', { kind: 'default', thread: { id: 't1', kind: COMBAT_CHAT_TURN } }),
      msg(2, '[[character:1,Старый Бородач]] попадает по [[npc:2,Гаррик из Тени]]!', {
        thread: { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK },
      }),
      msg(3, '[[character:1,Старый Бородач]] не смог сотворить [[rule:discharge]].', {
        thread: { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK },
      }),
    ]);
    const turn = forest[0];
    expect(turn?.type).toBe('fold');
    if (turn?.type !== 'fold') return;
    const attack = turn.fold.children[0];
    if (attack?.type !== 'fold') return;
    expect(attack.fold.summary).toBe(
      '[[character:1,Старый Бородач]] не смог сотворить [[rule:discharge]]. [[character:1,Старый Бородач]] попадает по [[npc:2,Гаррик из Тени]]!',
    );
  });

  it('сводка касания с заклинанием: сначала сотворение, затем суммарный удар', () => {
    const forest = combatChatFoldService.buildCombatChatFolds([
      msg(1, 'Ходит Бородач', { kind: 'default', thread: { id: 't1', kind: COMBAT_CHAT_TURN } }),
      msg(2, '[[character:1,Старый Бородач]] успешно сотворил [[rule:discharge]] за 2ОД!', {
        thread: { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK },
      }),
      msg(
        3,
        '[[character:1,Старый Бородач]] попадает по [[character:2,Гаррик из Тени]] с 0 РУ и наносит 4 истощения!',
        {
          thread: { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK },
          attachments: [{ type: ATTACK_CALC_ATTACHMENT_TYPE, payload: {} }],
        },
      ),
      msg(4, '[[rule:discharge]] бьёт по [[character:2,Гаррик из Тени]] и наносит 3 истощения!', {
        thread: { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK },
        attachments: [{ type: ATTACK_CALC_ATTACHMENT_TYPE, payload: {} }],
      }),
      msg(5, '[[character:2,Гаррик из Тени]] получает увечье с силой 5.', {
        thread: { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK },
      }),
      msg(6, '[[character:2,Гаррик из Тени]] не выдерживает истощение (РУ -2) — Потеря сознания.', {
        thread: { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK },
      }),
    ]);
    const turn = forest[0];
    expect(turn?.type).toBe('fold');
    if (turn?.type !== 'fold') return;
    const attack = turn.fold.children[0];
    if (attack?.type !== 'fold') return;
    expect(attack.fold.summary).toBe(
      '[[character:1,Старый Бородач]] успешно сотворил [[rule:discharge]] за 2ОД! [[character:1,Старый Бородач]] попадает по [[character:2,Гаррик из Тени]] и наносит 7 истощения! [[character:2,Гаррик из Тени]] получает увечье силой 5. [[character:2,Гаррик из Тени]] потерял сознание!',
    );
  });

  it('проверка на инициативу — отдельный тред со сводкой порядка', () => {
    const forest = combatChatFoldService.buildCombatChatFolds([
      msg(1, 'Новый раунд: 1', { kind: 'highlighted', thread: { id: 'r1', kind: COMBAT_CHAT_ROUND } }),
      msg(2, 'Проверка на инициативу', {
        thread: { id: 'i1', parentId: 'r1', kind: COMBAT_CHAT_INITIATIVE },
      }),
      msg(3, 'Порядок инициативы: А (5), Б (2).', {
        thread: { id: 'i1', parentId: 'r1', kind: COMBAT_CHAT_INITIATIVE },
      }),
      msg(4, 'Ходит А', { kind: 'default', thread: { id: 't1', parentId: 'r1', kind: COMBAT_CHAT_TURN } }),
    ]);
    const round = forest[0];
    expect(round?.type).toBe('fold');
    if (round?.type !== 'fold') return;
    const kinds = round.fold.children.map((child) => (child.type === 'fold' ? child.fold.kind : 'message'));
    expect(kinds).toEqual([COMBAT_CHAT_INITIATIVE, COMBAT_CHAT_TURN]);
    const initiative = round.fold.children[0];
    if (initiative?.type !== 'fold') return;
    expect(initiative.fold.summary).toBe('Порядок инициативы: А (5), Б (2).');
  });

  it('атаки без parentId привязываются к предшествующему ходу', () => {
    const messages = [
      msg(1, 'Ходит Тестовичок', { kind: 'default', thread: { id: 't1', kind: COMBAT_CHAT_TURN } }),
      msg(2, 'Снимает состояние повреждения', { thread: { id: 't1', kind: COMBAT_CHAT_TURN } }),
      msg(3, 'Первая атака', {
        thread: { id: 'a1', kind: COMBAT_CHAT_ATTACK },
        attachments: [{ type: ATTACK_CALC_ATTACHMENT_TYPE, payload: {} }],
      }),
      msg(4, 'Вторая атака', {
        thread: { id: 'a2', kind: COMBAT_CHAT_ATTACK },
        attachments: [{ type: ATTACK_CALC_ATTACHMENT_TYPE, payload: {} }],
      }),
      msg(5, 'Третья атака', {
        thread: { id: 'a3', kind: COMBAT_CHAT_ATTACK },
        attachments: [{ type: ATTACK_CALC_ATTACHMENT_TYPE, payload: {} }],
      }),
      msg(6, 'Задаёт очки действия', { thread: { id: 't1', kind: COMBAT_CHAT_TURN } }),
    ];
    const forest = combatChatFoldService.buildCombatChatFolds(messages);
    expect(forest).toHaveLength(1);
    const turn = forest[0];
    expect(turn?.type).toBe('fold');
    if (turn?.type !== 'fold') return;
    expect(turn.fold.id).toBe('t1');
    const children = turn.fold.children.map((child) => (child.type === 'fold' ? child.fold.id : child.message.id));
    expect(children).toEqual([2, 'a1', 'a2', 'a3', 6]);
    const rows = chatFoldService.flattenChatFolds(forest, (id) => id === 't1', null);
    const visible = rows.flatMap((row) => {
      if (row.type === 'message') return [row.message.id];
      if (row.type === 'panel') return [row.foldId];

      return [];
    });
    expect(visible).toEqual([2, 'a1', 'a2', 'a3', 6]);
  });

  it('сводка атаки включает итог рывка по помехе', () => {
    const forest = combatChatFoldService.buildCombatChatFolds([
      msg(1, 'Ходит Осведомитель', { kind: 'default', thread: { id: 't1', kind: COMBAT_CHAT_TURN } }),
      msg(
        2,
        'Осведомитель совершает Размах.\nЭффекты: после удара проверка Волю против 3: успех — внутреннего дробящего [сила удара]↓, без помехи действия (опция)',
        { thread: { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK } },
      ),
      msg(3, 'Осведомитель промахивается по Старый Бородач!', {
        thread: { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK },
        attachments: [{ type: ATTACK_CALC_ATTACHMENT_TYPE, payload: {} }],
      }),
      msg(4, 'Осведомитель проходит проверку на Яростный рывок против 3. Провал: помеха действия остаётся.', {
        thread: { id: 'a1', parentId: 't1', kind: COMBAT_CHAT_ATTACK },
      }),
    ]);
    const turn = forest[0];
    expect(turn?.type).toBe('fold');
    if (turn?.type !== 'fold') return;
    const attack = turn.fold.children[0];
    if (attack?.type !== 'fold') return;
    expect(attack.fold.summary).toBe('Осведомитель промахивается по Старый Бородач! Помеха после удара остаётся.');
  });
});
