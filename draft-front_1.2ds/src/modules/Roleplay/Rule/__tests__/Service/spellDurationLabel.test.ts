import { describe, expect, it } from 'vitest';
import { spellDurationLabelService } from '@/modules/Roleplay/Rule/Service/Instance/spellDurationLabelService';

describe('SpellDurationLabelService', () => {
  it('поддержание берёт свою мощь, не мощь сотворения', () => {
    expect(
      spellDurationLabelService.action({ type: 'sustained', power: { type: 'parameter', parameter_code: 'x' } }),
    ).toBe('Поддержание(Мощь: x)');
    expect(spellDurationLabelService.action({ type: 'sustained', power: { base: 4, size: -1 } })).toBe(
      'Поддержание(Мощь: 4↓)',
    );
  });
});
