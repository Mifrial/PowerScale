import { describe, it, expect } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CharacterReferenceService } from '@/modules/Roleplay/Character/Service/CharacterReferenceService';

function rule(id: string, code: string, name: string): Rule {
  return {
    id,
    code,
    type: 'simple',
    name,
    description: '',
    spaceId: 1,
    createdAt: '2026-01-01T00:00:00Z',
  };
}

const rules: Rule[] = [rule('rule-a', 'strength', 'Сила'), rule('rule-b', 'melee-fighting', 'Ближний бой')];

const service = new CharacterReferenceService(rules, 'razrabotka', 5);

describe('CharacterReferenceService', () => {
  it('разрешает ruleId в имя и ссылку внутри ревизии', () => {
    const resolved = service.resolve('rule-a');

    expect(resolved.isResolved).toBe(true);
    expect(resolved.name).toBe('Сила');
    expect(resolved.href).toBe('/space/razrabotka/5/rules/rule-a');
  });

  it('фолбэк по коду, если в ревизии нет правила с таким id', () => {
    const resolved = service.resolve('strength');

    expect(resolved.isResolved).toBe(true);
    expect(resolved.name).toBe('Сила');
    expect(resolved.href).toBe('/space/razrabotka/5/rules/rule-a');
  });

  it('отсутствующее правило не падает и возвращает неразрешённую ссылку', () => {
    const resolved = service.resolve('rule-unknown');

    expect(resolved.isResolved).toBe(false);
    expect(resolved.name).toBe('rule-unknown');
    expect(resolved.href).toBeNull();
  });

  it('ruleById возвращает правило только по точному id', () => {
    expect(service.ruleById('rule-b')?.code).toBe('melee-fighting');
    expect(service.ruleById('missing')).toBeNull();
  });

  it('ruleByCode находит правило по семантическому коду', () => {
    expect(service.ruleByCode('strength')?.id).toBe('rule-a');
    expect(service.ruleByCode('missing')).toBeNull();
  });
});
