import { describe, expect, it } from 'vitest';
import { parseChronicleContent, chronicleRefsFromContent } from '@/modules/Roleplay/Game/Utils/chronicleInline';

describe('parseChronicleContent', () => {
  it('делит содержимое на текст и ссылки', () => {
    expect(parseChronicleContent('Гаррик [[character:3]] и Ворон [[npc:2]] встретились.')).toEqual([
      { kind: 'text', text: 'Гаррик ' },
      { kind: 'ref', ref: { kind: 'character', id: 3 } },
      { kind: 'text', text: ' и Ворон ' },
      { kind: 'ref', ref: { kind: 'npc', id: 2 } },
      { kind: 'text', text: ' встретились.' },
    ]);
  });

  it('ссылка в начале и без текста вокруг', () => {
    expect(parseChronicleContent('[[npc:1]]')).toEqual([{ kind: 'ref', ref: { kind: 'npc', id: 1 } }]);
  });

  it('некорректный id токена игнорируется', () => {
    expect(parseChronicleContent('[[character:abc]] и текст')).toEqual([{ kind: 'text', text: ' и текст' }]);
  });

  it('токены неизвестных типов не превращаются в ссылки', () => {
    expect(parseChronicleContent('[[user:ivan]] помог')).toEqual([{ kind: 'text', text: ' помог' }]);
  });
});

describe('chronicleRefsFromContent', () => {
  it('собирает уникальные ссылки в порядке появления', () => {
    expect(chronicleRefsFromContent('[[npc:1]] потом [[character:3]] и снова [[npc:1]]')).toEqual([
      { kind: 'npc', id: 1 },
      { kind: 'character', id: 3 },
    ]);
  });

  it('пустое содержимое — пустой список', () => {
    expect(chronicleRefsFromContent('просто текст')).toEqual([]);
  });
});
