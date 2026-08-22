import { describe, expect, it } from 'vitest';
import { MechanicEngine } from '@/modules/Roleplay/Rule/Service/Mechanic/MechanicEngine';
import { MechanicHandlerRegistry } from '@/modules/Roleplay/Rule/Service/Mechanic/MechanicHandlerRegistry';
import {
  purchaseSurchargeHandler,
  PURCHASE_SURCHARGE_EVENT,
} from '@/modules/Roleplay/Rule/Service/Mechanic/Handlers/PurchaseSurchargeHandler';
import type { MechanicHandler } from '@/modules/Roleplay/Rule/Service/Mechanic/MechanicHandler';
import type { CharacterMechanicContext } from '@/modules/Roleplay/Rule/Dto/CharacterMechanicContext';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

function rule(overrides: Partial<Rule>): Rule {
  return {
    id: 'rule-x',
    code: 'code-x',
    type: 'simple',
    name: 'Правило',
    description: '',
    spaceId: 1,
    keywordIds: [],
    mechanicId: null,
    mechanic_payload: null,
    createdAt: '2026-01-15T10:00:00Z',
    ...overrides,
  };
}

function mechanic(overrides: Partial<Mechanic>): Mechanic {
  return { id: 1, code: 'm', name: 'Механика', description: '', version: '1.0.0', ...overrides };
}

describe('MechanicEngine', () => {
  it('resolveActive резолвит механики правил через каталог (mechanicId → code@version)', () => {
    const registry = new MechanicHandlerRegistry();
    const handler: MechanicHandler<object> = {
      code: 'm',
      version: '1.0.0',
      subscriptions: { ev: 0 },
      run: () => undefined,
    };
    registry.register(handler);
    const engine = new MechanicEngine(registry);

    const rules = [rule({ id: 'r1', code: 'roll', mechanicId: 5 }), rule({ id: 'r2', code: 'plain' })];
    const mechanics = [mechanic({ id: 5, code: 'm', version: '1.0.0' })];

    const resolved = engine.resolveActive(rules, mechanics);
    expect(resolved).toHaveLength(1);
    expect(resolved[0].handler.code).toBe('m');
  });

  it('resolveActive фильтрует срез по includeCodes и добавляет пер-ролл правила вне фильтра', () => {
    const registry = new MechanicHandlerRegistry();
    const handlerA: MechanicHandler<object> = {
      code: 'a',
      version: '1.0.0',
      subscriptions: { ev: 0 },
      run: () => undefined,
    };
    const handlerB: MechanicHandler<object> = {
      code: 'b',
      version: '1.0.0',
      subscriptions: { ev: 0 },
      run: () => undefined,
    };
    registry.register(handlerA);
    registry.register(handlerB);
    const engine = new MechanicEngine(registry);
    const rules = [rule({ id: 'r1', code: 'one', mechanicId: 1 }), rule({ id: 'r2', code: 'two', mechanicId: 2 })];
    const mechanics = [
      mechanic({ id: 1, code: 'a', version: '1.0.0' }),
      mechanic({ id: 2, code: 'b', version: '1.0.0' }),
    ];

    const resolved = engine.resolveActive(rules, mechanics, { includeCodes: ['a'], extraRuleCodes: ['two'] });
    expect(resolved.map((item) => item.handler.code).sort()).toEqual(['a', 'b']);
  });

  it('runEvent выполняет подписанных в порядке приоритета и мутирует контекст', () => {
    const registry = new MechanicHandlerRegistry();
    const late: MechanicHandler<{ trace: string[] }> = {
      code: 'late',
      version: '1.0.0',
      subscriptions: { ev: 20 },
      run({ context }) {
        context.trace.push('late');
      },
    };
    const early: MechanicHandler<{ trace: string[] }> = {
      code: 'early',
      version: '1.0.0',
      subscriptions: { ev: 5 },
      run({ context }) {
        context.trace.push('early');
      },
    };
    const unrelated: MechanicHandler<{ trace: string[] }> = {
      code: 'other',
      version: '1.0.0',
      subscriptions: { other: 0 },
      run({ context }) {
        context.trace.push('other');
      },
    };
    registry.register(late);
    registry.register(early);
    registry.register(unrelated);
    const engine = new MechanicEngine(registry);
    const mechanics = [
      mechanic({ id: 1, code: 'late', version: '1.0.0' }),
      mechanic({ id: 2, code: 'early', version: '1.0.0' }),
      mechanic({ id: 3, code: 'other', version: '1.0.0' }),
    ];
    const rules = [rule({ mechanicId: 1 }), rule({ mechanicId: 2 }), rule({ mechanicId: 3 })];
    const active = engine.resolveActive(rules, mechanics);
    const context = { trace: [] };

    engine.runEvent('ev', context, active);

    expect(context.trace).toEqual(['early', 'late']);
  });
});

describe('PurchaseSurchargeHandler (миграция на контекст)', () => {
  interface AbilitySeed {
    code: string;
    level: number;
    keywords: string[];
  }

  function contextOf(abilities: AbilitySeed[], racialCodes: string[] = []): CharacterMechanicContext {
    return {
      abilityLevels: new Map(abilities.map((ability) => [ability.code, ability.level])),
      abilityKeywords: new Map(abilities.map((ability) => [ability.code, new Set(ability.keywords)])),
      racialAbilityCodes: new Set(racialCodes),
      osSurchargeTotal: 0,
      surchargeItems: [],
    };
  }

  function run(abilities: AbilitySeed[], racialCodes: string[], payload: Rule['mechanic_payload']) {
    const registry = new MechanicHandlerRegistry();
    registry.register(purchaseSurchargeHandler);
    const engine = new MechanicEngine(registry);
    const rules = [rule({ mechanicId: 4, mechanic_payload: payload })];
    const mechanics = [mechanic({ id: 4, code: 'purchase_surcharge', version: '1.0.0' })];
    const context = contextOf(abilities, racialCodes);
    engine.runEvent(PURCHASE_SURCHARGE_EVENT, context, engine.resolveActive(rules, mechanics));

    return context;
  }

  it('доплачивает ОС за каждую способность сверх free_count (фильтр по признаку)', () => {
    const context = run(
      [
        { code: 'a', level: 1, keywords: ['common'] },
        { code: 'b', level: 1, keywords: ['common'] },
        { code: 'c', level: 1, keywords: ['other'] },
      ],
      [],
      { type: 'purchase_surcharge', filter: { keyword_code: 'common' }, free_count: 1, surcharge: 2 },
    );
    expect(context.osSurchargeTotal).toBe(2);
    expect(context.surchargeItems).toEqual([{ abilityCode: 'b', amount: 2 }]);
  });

  it('фильтр по расе учитывает расовые способности', () => {
    const context = run(
      [
        { code: 'a', level: 1, keywords: [] },
        { code: 'b', level: 1, keywords: [] },
      ],
      ['a', 'b', 'c'],
      { type: 'purchase_surcharge', filter: { race_code: 'beast' }, free_count: 0, surcharge: 1 },
    );
    expect(context.osSurchargeTotal).toBe(2);
  });

  it('без совпадений ничего не начисляет', () => {
    const context = run([{ code: 'a', level: 1, keywords: ['other'] }], [], {
      type: 'purchase_surcharge',
      filter: { keyword_code: 'common' },
      free_count: 0,
      surcharge: 2,
    });
    expect(context.osSurchargeTotal).toBe(0);
    expect(context.surchargeItems).toEqual([]);
  });
});
