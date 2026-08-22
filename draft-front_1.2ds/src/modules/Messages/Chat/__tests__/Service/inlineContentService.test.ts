import { describe, it, expect } from 'vitest';
import { InlineContentService } from '@/modules/Messages/Chat/Service/InlineContentService';
import { INLINE_CONTENT_TOKEN_RE } from '@/modules/Messages/Chat/Constant/Chat/INLINE_CONTENT_TOKEN_RE';

const service = new InlineContentService(INLINE_CONTENT_TOKEN_RE);

describe('InlineContentService.parse', () => {
  it('returns a single text segment for plain content', () => {
    expect(service.parse('Привет всем')).toEqual([{ kind: 'text', text: 'Привет всем' }]);
  });

  it('parses a single user token', () => {
    expect(service.parse('Спасибо [[user:ivan]]')).toEqual([
      { kind: 'text', text: 'Спасибо ' },
      { kind: 'token', type: 'user', params: ['ivan'] },
    ]);
  });

  it('parses multiple tokens and trims params', () => {
    expect(service.parse('[[user:ivan]] и [[rule:melee-fighting]] end')).toEqual([
      { kind: 'token', type: 'user', params: ['ivan'] },
      { kind: 'text', text: ' и ' },
      { kind: 'token', type: 'rule', params: ['melee-fighting'] },
      { kind: 'text', text: ' end' },
    ]);
  });

  it('splits params by comma and skips empty ones', () => {
    expect(service.parse('[[rule:a,b,c]]')).toEqual([{ kind: 'token', type: 'rule', params: ['a', 'b', 'c'] }]);
    expect(service.parse('[[rule:a, ,c]]')).toEqual([{ kind: 'token', type: 'rule', params: ['a', 'c'] }]);
  });

  it('does not treat malformed tokens as tokens', () => {
    expect(service.parse('текст [[без:закрытия')).toEqual([{ kind: 'text', text: 'текст [[без:закрытия' }]);
  });

  it('returns empty array for empty content', () => {
    expect(service.parse('')).toEqual([]);
  });
});

describe('InlineContentService.toText', () => {
  it('keeps plain text as is', () => {
    expect(service.toText('Привет всем')).toBe('Привет всем');
  });

  it('replaces a token with the injected token label', () => {
    const labeled = new InlineContentService(INLINE_CONTENT_TOKEN_RE, () => 'Тестовая метка');
    expect(labeled.toText('Смотри [[test-token:x]]')).toBe('Смотри Тестовая метка');
  });

  it('falls back to first param when no token label resolves', () => {
    expect(service.toText('[[plain-token:ivan]]')).toBe('ivan');
  });

  it('joins segments around tokens', () => {
    const labeled = new InlineContentService(INLINE_CONTENT_TOKEN_RE, () => 'X');
    expect(labeled.toText('a[[joined:1]]b[[joined:2]]c')).toBe('aXbXc');
  });
});
