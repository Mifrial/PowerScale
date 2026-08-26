import { describe, expect, it } from 'vitest';
import { hostInlineRendererContext } from '@/modules/Messages/Chat/Utils/hostInlineRendererContext';

describe('hostInlineRendererContext', () => {
  it('кладёт data-срез хоста без shim ruleNames', () => {
    const bag = hostInlineRendererContext({ tokenLabels: { a: 'A' }, spaceId: 2, rulesRevision: 3 }, (ref) => ref);

    expect(bag?.tokenLabels).toEqual({ a: 'A' });
    expect(bag?.ruleNames).toBeUndefined();
    expect(bag?.spaceId).toBe(2);
    expect(bag?.rulesRevision).toBe(3);
    expect(typeof bag?.openEntity).toBe('function');
  });
});
