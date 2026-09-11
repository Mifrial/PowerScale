import { describe, expect, it } from 'vitest';
import { DevelopmentListService } from '@/modules/Roleplay/Character/Service/DevelopmentListService';
import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';

const service = new DevelopmentListService();

function ability(over: Partial<EditorAbility>): EditorAbility {
  return {
    ruleCode: 'discharge',
    code: 'discharge',
    name: 'Разряд',
    type: 'spell',
    description: '',
    processSteps: [],
    keywordIds: [],
    zones: [],
    level: 1,
    levels: [],
    automatic: false,
    gifted: false,
    giftedLevel: 0,
    derived: false,
    racial: false,
    visible: true,
    characteristic: false,
    characteristicCode: null,
    groupCode: null,
    parentCode: null,
    parameters: [],
    multiple: true,
    domainRef: 'magic-path',
    instances: [],
    domain: null,
    domainCode: null,
    domainOptions: [],
    ...over,
  };
}

describe('DevelopmentListService', () => {
  it('разворачивает заклинание в каталог и отдельные экземпляры', () => {
    const rows = service.expand([
      ability({
        instances: [
          { domain: '', domainCode: null, level: 1, levels: [] },
          { domain: 'Становление Арканиста', domainCode: 'becoming-arcanist', level: 1, levels: [] },
        ],
      }),
    ]);
    expect(rows.map((row) => row.spellRowKind)).toEqual(['catalog', 'instance', 'instance']);
    expect(rows[1]?.ability.domain).toBe('');
    expect(rows[2]?.ability.domain).toBe('Становление Арканиста');
    expect(new Set(rows.map((row) => row.key)).size).toBe(3);
  });

  it('улучшение-экземпляр вкладывается в экземпляр родителя с тем же путём', () => {
    const parent = ability({
      instances: [{ domain: 'Становление Арканиста', domainCode: 'becoming-arcanist', level: 1, levels: [] }],
    });
    const child = ability({
      ruleCode: 'chain-lightning',
      code: 'chain-lightning',
      name: 'Цепная молния',
      type: 'skill',
      parentCode: 'discharge',
      instances: [{ domain: 'Становление Арканиста', domainCode: 'becoming-arcanist', level: 1, levels: [] }],
    });
    const rows = service.expand([parent, child]);
    const byParent = service.childrenByParentKey(rows);
    const parentInstance = rows.find((row) => row.spellRowKind === 'instance' && row.ability.ruleCode === 'discharge');
    const nested = parentInstance ? (byParent.get(parentInstance.key) ?? []) : [];
    expect(nested.some((row) => row.ability.ruleCode === 'chain-lightning' && row.spellRowKind === 'instance')).toBe(
      true,
    );
    const parentCatalog = rows.find((row) => row.spellRowKind === 'catalog' && row.ability.ruleCode === 'discharge');
    const catalogChildren = parentCatalog ? (byParent.get(parentCatalog.key) ?? []) : [];
    expect(
      catalogChildren.some((row) => row.ability.ruleCode === 'chain-lightning' && row.spellRowKind === 'catalog'),
    ).toBe(true);
    const roots = service.roots(rows, byParent);
    expect(roots.every((row) => row.ability.ruleCode === 'discharge')).toBe(true);
  });

  it('общий навык волшебства с доменом пути разворачивается как заклинание', () => {
    const rows = service.expand([
      ability({
        ruleCode: 'careful-magic',
        code: 'careful-magic',
        name: 'Аккуратное волшебство',
        type: 'skill',
        domainRef: 'magic-path',
        instances: [{ domain: 'Арканист', domainCode: 'arcanist', level: 1, levels: [] }],
      }),
    ]);
    expect(rows.map((row) => row.spellRowKind)).toEqual(['catalog', 'instance']);
    expect(rows[1]?.ability.domain).toBe('Арканист');
  });
});
