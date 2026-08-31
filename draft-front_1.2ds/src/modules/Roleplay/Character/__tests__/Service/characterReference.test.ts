import { describe, it, expect } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CharacterReferenceService } from '@/modules/Roleplay/Character/Service/CharacterReferenceService';

function rule(id: number | null, code: string, name: string): Rule {
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

const rules: Rule[] = [rule(null, 'strength', 'Сила'), rule(null, 'melee-fighting', 'Ближний бой')];

const service = new CharacterReferenceService(rules, 'razrabotka', 5);

describe('CharacterReferenceService', () => {
  it('разрешает code в имя и ссылку внутри ревизии', () => {
    const resolved = service.resolve('strength');

    expect(resolved.isResolved).toBe(true);
    expect(resolved.name).toBe('Сила');
    expect(resolved.href).toBe('/space/razrabotka/5/rules/strength');
  });

  it('отсутствующее правило не падает и возвращает неразрешённую ссылку', () => {
    const resolved = service.resolve('rule-unknown');

    expect(resolved.isResolved).toBe(false);
    expect(resolved.name).toBe('rule-unknown');
    expect(resolved.href).toBeNull();
  });

  it('storage id больше не резолвится', () => {
    expect(service.resolve('rule-a').isResolved).toBe(false);
  });

  it('ruleByCode находит правило по семантическому коду', () => {
    expect(service.ruleByCode('strength')?.id).toBeNull();
    expect(service.ruleByCode('missing')).toBeNull();
  });
});
