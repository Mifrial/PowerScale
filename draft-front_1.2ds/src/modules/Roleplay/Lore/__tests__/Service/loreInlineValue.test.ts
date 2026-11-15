import { describe, expect, it } from 'vitest';
import { LoreInlineValueService } from '@/modules/Roleplay/Lore/Service/Inline/LoreInlineValueService';

const service = new LoreInlineValueService();

describe('LoreInlineValueService', () => {
  it('builds and parses world/article', () => {
    expect(service.build('myth', 'god')).toBe('myth/god');
    expect(service.parse('myth/god')).toEqual({ worldCode: 'myth', articleCode: 'god' });
  });

  it('rejects malformed values', () => {
    expect(service.parse('')).toBeNull();
    expect(service.parse('myth')).toBeNull();
    expect(service.parse('myth/god/extra')).toBeNull();
    expect(service.parse('Myth/god')).toBeNull();
    expect(service.parse('myth/')).toBeNull();
    expect(service.parse('/god')).toBeNull();
  });
});
