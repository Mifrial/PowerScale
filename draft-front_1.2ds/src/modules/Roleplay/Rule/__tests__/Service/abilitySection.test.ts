import { describe, expect, it } from 'vitest';
import { AbilitySectionService } from '@/modules/Roleplay/Rule/Service/AbilitySectionService';
import { abilitySectionService } from '@/modules/Roleplay/Rule/Service/Instance/abilitySectionService';

describe('AbilitySectionService', () => {
  const service = abilitySectionService;

  it('spec.section важнее признаков', () => {
    expect(service.fromSpec({ section: 'core-rules' })).toBe('core-rules');
    expect(service.fromKeywordCodes(['section-melee', 'skill'])).toBe('section-melee');
    expect(service.label('core-rules')).toBe('Основные правила');
  });

  it('неизвестный признак не раздел', () => {
    expect(new AbilitySectionService().fromKeywordCodes(['common', 'attack'])).toBeNull();
    expect(service.fromSpec({ section: '  ' })).toBeNull();
  });
});
