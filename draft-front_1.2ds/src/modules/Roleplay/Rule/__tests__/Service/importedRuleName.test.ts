import { describe, expect, it } from 'vitest';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { mockDevelopmentImport } from '@/modules/Roleplay/Rule/Mock/mockDevelopmentImport';
import type { AbilitySpecBase } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecBase';
import { importedRuleNameService } from '@/modules/Roleplay/Rule/Service/Instance/importedRuleNameService';
import { ImportedRuleNameService } from '@/modules/Roleplay/Rule/Service/ImportedRuleNameService';

describe('ImportedRuleNameService', () => {
  const service = importedRuleNameService;

  it('срезает «х из N» / домен из имени', () => {
    expect(service.parse('Развитие внимательности( х из 3)').name).toBe('Развитие внимательности');
    expect(service.parse('Предельная концентрация( x из 2 )').name).toBe('Предельная концентрация');
    expect(service.parse('Длительное напряжение( n из 5)').parameterCode).toBe('n');
    expect(service.parse('Длительное напряжение( n из 5)').parameterMax).toBe(5);
    expect(service.parse('Знание законов( субъект )').domainRef).toBe('subject');
    expect(service.parse('Владение языком( Язык , х из 3)')).toEqual({
      name: 'Владение языком',
      domainRef: 'language',
      parameterCode: 'x',
      parameterMax: 3,
      hadSchemaTail: true,
    });
    expect(service.parse('Владение оружием ( x из 3, оружие )').domainRef).toBe('weapon-family');
    expect(service.parse('Знание болезней( вид , х )').domainRef).toBe('species');
  });

  it('не трогает скобки, которые не схема импорта', () => {
    expect(service.parse('Простая атака (ближний бой)').hadSchemaTail).toBe(false);
    expect(service.parse('3↓ (3м.е./ход)').hadSchemaTail).toBe(false);
    expect(service.parse('Ложные трудности(').hadSchemaTail).toBe(false);
  });

  it('дописывает domain_ref только если в спеке пусто', () => {
    const filled = service.sanitizeRule({
      id: 1,
      code: 'instr',
      type: 'ability',
      name: 'Владение музыкальным инструментом ( x из 3, инструмент )',
      description: '',
      spaceId: 1,
      spec: {
        type: 'skill',
        zones: { or: { kind: 'array', levels_cost: [1, 1, 2] } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
      },
      createdAt: '',
    });
    const spec = filled.spec as AbilitySpecBase;
    expect(filled.name).toBe('Владение музыкальным инструментом');
    expect(spec.multiple).toBe(true);
    expect(spec.domain_ref).toBe('instrument');
    expect(spec.parameters).toBeUndefined();

    const kept = service.sanitizeRule({
      id: null,
      code: 'lips',
      type: 'ability',
      name: 'Чтение по губам( Вид )',
      description: '',
      spaceId: 1,
      spec: {
        type: 'skill',
        zones: { or: { kind: 'array', levels_cost: [1] } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
        multiple: true,
        domain_ref: 'language',
      },
      createdAt: '',
    });
    expect((kept.spec as AbilitySpecBase).domain_ref).toBe('language');
  });

  it('параметр из хвоста — только если зона не кодирует тот же максимум уровнями', () => {
    const withParam = service.sanitizeRule({
      id: null,
      code: 'hold',
      type: 'ability',
      name: 'Длительное напряжение( n из 5)',
      description: '',
      spaceId: 1,
      spec: {
        type: 'skill',
        zones: { or: { kind: 'progression', max_level: 1, base_cost: 3, step: 0 } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
      },
      createdAt: '',
    });
    expect((withParam.spec as AbilitySpecBase).parameters).toEqual([
      { code: 'n', label: 'N', resolution: 'purchase', default: 1, min: 1, max: 5 },
    ]);

    const levels = service.sanitizeRule({
      id: null,
      code: 'attn',
      type: 'ability',
      name: 'Развитие внимательности( х из 3)',
      description: '',
      spaceId: 1,
      spec: {
        type: 'skill',
        zones: { or: { kind: 'array', levels_cost: [1, 1, 2] } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
      },
      createdAt: '',
    });
    expect((levels.spec as AbilitySpecBase).parameters).toBeUndefined();
  });

  it('повторный проход идемпотентен', () => {
    const once = service.sanitizeCatalog(mockDevelopmentImport);
    const twice = new ImportedRuleNameService().sanitizeCatalog(once);
    expect(twice).toEqual(once);
  });

  it('в каталоге нет импортных хвостов в именах', () => {
    const dirty = [...mockDevelopmentImport, ...ruleCatalog].filter((rule) => service.parse(rule.name).hadSchemaTail);
    expect(dirty.map((rule) => `${rule.code}:${rule.name}`)).toEqual([]);
  });
});
