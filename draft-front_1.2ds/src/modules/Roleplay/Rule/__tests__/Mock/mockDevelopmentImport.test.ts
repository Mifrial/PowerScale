import { describe, it, expect } from 'vitest';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { mockDevelopmentImport } from '@/modules/Roleplay/Rule/Mock/mockDevelopmentImport';
import type { AbilitySpecBase } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecBase';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const byCode = new Map(ruleCatalog.map((r) => [r.code, r]));

const abilitySpec = (code: string): AbilitySpecBase | undefined => {
  const rule = byCode.get(code);

  return rule?.type === 'ability' ? (rule.spec as AbilitySpecBase | undefined) : undefined;
};

describe('mockDevelopmentImport (S14)', () => {
  it('каталог «Развития» импортирован, id/code уникальны, зона or', () => {
    expect(mockDevelopmentImport.length).toBeGreaterThan(180);
    const ids = mockDevelopmentImport.map((r) => r.id);
    const codes = mockDevelopmentImport.map((r) => r.code);
    expect(new Set(ids).size).toBe(ids.length);
    expect(new Set(codes).size).toBe(codes.length);
    // Покупаемые способности — зона or; информационные (агрегаты/производные/без цены) без зоны.
    for (const rule of mockDevelopmentImport) {
      const spec = rule.spec as AbilitySpecBase | undefined;
      if (!spec) continue;
      const hasZones = Object.keys(spec.zones ?? {}).length > 0;
      if (hasZones) expect(spec.zones?.or).toBeDefined();
    }
  });

  it('множественные навыки: флаг multiple + домен-справочник', () => {
    const language = abilitySpec('vladenie-yazykom');
    expect(language?.multiple).toBe(true);
    expect(language?.domain_ref).toBe('language');
    const medicine = abilitySpec('pervaya-pomosch');
    expect(medicine?.multiple).toBe(true);
    expect(medicine?.domain_ref).toBe('species');
    const trade = abilitySpec('torgovlya');
    expect(trade?.multiple).toBe(true);
    expect(trade?.domain_ref).toBe('region');
  });

  it('«Стоимость N, Трудность M» = progression; поуровневые — array', () => {
    const stealth = abilitySpec('skrytnost');
    expect(stealth?.zones?.or).toEqual({ kind: 'progression', max_level: 3, base_cost: 1, step: 1 });
    const acrobatics = abilitySpec('akrobatika');
    expect(acrobatics?.zones?.or).toEqual({ kind: 'array', levels_cost: [1, 1, 1, 2, 2, 3] });
  });

  it('Акробатика связана с отдельной проверкой и даёт бонус по уровню', () => {
    const acrobatics = byCode.get('akrobatika');
    const acrobaticsCheck = byCode.get('acrobatics');
    const grants = (acrobatics?.spec as AbilitySpecBase)?.grants ?? [];

    expect(acrobaticsCheck?.type).toBe('check');
    expect((acrobaticsCheck?.spec as { characteristic_code?: string }).characteristic_code).toBe('dexterity');
    expect((acrobaticsCheck?.spec as { allow_characteristic_override?: boolean }).allow_characteristic_override).toBe(
      true,
    );
    expect(grants).toHaveLength(1);
    expect(grants.every((entry) => entry.grants[0]?.type === 'characteristic_modify')).toBe(true);
    expect(
      grants.every((entry) =>
        entry.grants.every(
          (grant) => grant.type === 'characteristic_modify' && grant.check_codes?.includes('acrobatics'),
        ),
      ),
    ).toBe(true);
  });

  it('агрегат «Развитие восприятия» и производный «Ближний бой»', () => {
    const aggregate = abilitySpec('razvitie-vospriyatiya');
    expect(aggregate?.aggregate).toEqual({
      characteristic_code: 'perception',
      method_keyword: 'method-perception',
      levels: [2, 2, 2, 2, 2],
    });
    const melee = abilitySpec('blizhniy-boy');
    expect(melee?.derived_level).toEqual({ source_keyword: 'section-melee', thresholds: [2, 8, 16] });
  });

  it('требования: навык (двойной удар), характеристика с размером', () => {
    const sdvoenny = abilitySpec('sdvoennyy-udar');
    expect(sdvoenny?.requirements?.[0]?.requirements).toContainEqual({
      type: 'has_ability',
      ability_code: 'blizhniy-boy',
      min_level: 1,
    });
    const neitralizaciya = abilitySpec('neytralizatsiya-pomekh');
    expect(neitralizaciya?.requirements?.[0]?.requirements).toContainEqual({
      type: 'characteristic_value',
      characteristic_code: 'intellect',
      min: { base: 4, size: 1 },
    });
  });

  it('Реакция/Манёвр — действия с признаком', () => {
    const specType = (rule: { spec?: unknown } | undefined): string => {
      if (!rule || typeof rule.spec !== 'object' || rule.spec === null) return '';
      const spec = rule.spec as { type?: string };

      return spec.type ?? '';
    };
    const otstuplenie = byCode.get('otstuplenie');
    expect(specType(otstuplenie)).toBe('action');
    expect(otstuplenie?.keywordIds).toContain(53); // reaction
    const obezoruzhit = byCode.get('obezoruzhit-protivnika');
    expect(specType(obezoruzhit)).toBe('action');
    expect(obezoruzhit?.keywordIds).toContain(54); // maneuver
  });

  it('разделяет Быстрый и Стремительный удар и декларирует эффекты', () => {
    const fast = abilitySpec('bystryy-udar');
    const swift = abilitySpec('stremitelnyy-udar');

    expect(fast?.action_effects).toEqual([
      {
        type: 'current_action_attack_accuracy',
        delta: -1,
        scope: { components: ['strike'], hit_count: 1 },
      },
      {
        type: 'next_action_attack_cost',
        resource_code: 'action-points',
        delta: 1,
      },
    ]);
    expect(swift?.action_effects?.[0]).toMatchObject({
      type: 'next_action_attack_target_characteristic_modifier',
      max_total_action_cost: 2,
      delta: -3,
      min: 0,
    });
    expect(swift?.requirements?.[0]?.requirements).toContainEqual({
      type: 'has_ability',
      ability_code: 'bystryy-udar',
      min_level: 1,
    });
  });

  it('атакующие способности помечены признаком «Атака» (keyword 71), остальные — нет', () => {
    const attack = (code: string): boolean => (byCode.get(code)?.keywordIds ?? []).includes(71);
    // Атакующие карточки каталога (признак «атака» в выгрузке).
    expect(attack('bystryy-udar')).toBe(true);
    expect(attack('sdvoennyy-udar')).toBe(true);
    expect(attack('oboerukaya-ataka')).toBe(true);
    // Не-атакующая способность раздела ближнего боя (реакция «Отступление»).
    expect(attack('otstuplenie')).toBe(false);
    // Счёт: 20 атакующих карточек (все — раздел ближнего боя). «Продолжение атаки» удалена из каталога.
    const withAttack = mockDevelopmentImport.filter((r) => (r.keywordIds ?? []).includes(71)).map((r) => r.code);
    expect(withAttack).toHaveLength(19);
  });

  it('«Эффект», «Группа навыков», «Черта развития» не импортируются', () => {
    const codes = mockDevelopmentImport.map((r) => r.code);
    expect(codes.some((c) => c.includes('poterya-ravnovesiya'))).toBe(false);
    expect(codes.some((c) => c.includes('vladenie-oruzhiem'))).toBe(false);
    expect(codes.some((c) => c.includes('schetnyy-razum'))).toBe(false);
    // Макияж — обычный навык (Клич вырезан как тип).
    expect(byCode.has('makiyazh')).toBe(true);
  });

  it('признаки типов разданы по данным: skill/action/process/trait несут keyword типа', () => {
    // Только skill (13), у action — Навык+Действие (13+14), process — Навык+Действие+Процесс (13+14+15).
    for (const rule of mockDevelopmentImport) {
      const spec = rule.spec as (AbilitySpecBase & { type?: string }) | undefined;
      if (!spec) continue;
      const kw = rule.keywordIds ?? [];
      switch (spec.type) {
        case 'skill':
          expect(kw).toContain(13);
          break;
        case 'action':
          expect(kw).toContain(13);
          expect(kw).toContain(14);
          break;
        case 'process':
          expect(kw).toContain(13);
          expect(kw).toContain(14);
          expect(kw).toContain(15);
          break;
        case 'trait':
          expect(kw).toContain(11);
          break;
        default:
          break;
      }
    }
  });

  it('все способности каталога «Развития» находятся в зоне or', () => {
    for (const rule of mockDevelopmentImport) {
      const spec = rule.spec as AbilitySpecBase | undefined;
      expect(spec?.zones?.or).toBeDefined();
    }
  });

  it('«Развитие внимательности/реакции» — навыки, не методы развития восприятия', () => {
    const vnim = byCode.get('razvitie-vnimatelnosti');
    const reak = byCode.get('razvitie-reaktsii');
    expect(vnim?.keywordIds).toContain(13); // навык
    expect(vnim?.keywordIds).not.toContain(56); // НЕ метод развития восприятия
    expect(reak?.keywordIds).toContain(13);
    expect(reak?.keywordIds).not.toContain(56);
  });

  it('«Развитие внимательности/реакции» дают модификатор от тренировки к базам Восприятия', () => {
    const vnim = byCode.get('razvitie-vnimatelnosti') as Rule | undefined;
    const reak = byCode.get('razvitie-reaktsii') as Rule | undefined;
    const grantTargets = (rule: Rule | undefined): string[] =>
      ((rule?.spec as AbilitySpecBase | undefined)?.grants ?? []).flatMap((entry) =>
        (entry.grants ?? []).map((grant) => (grant as { characteristic_code?: string }).characteristic_code ?? ''),
      );
    expect(grantTargets(vnim)).toContain('attention');
    expect(grantTargets(reak)).toContain('reaction');
  });

  it('«Развитие памяти/мышления» — навыки, не методы развития интеллекта', () => {
    const pamyat = byCode.get('razvitie-pamyati');
    const myshlenie = byCode.get('razvitie-myshleniya');
    expect(pamyat?.keywordIds).toContain(13); // навык
    expect(pamyat?.keywordIds).not.toContain(57); // НЕ метод развития интеллекта
    expect(myshlenie?.keywordIds).toContain(13);
    expect(myshlenie?.keywordIds).not.toContain(57);
  });

  it('«Развитие памяти/мышления» дают модификатор от тренировки к Памяти/Мышлению', () => {
    const grantTargets = (rule: Rule | undefined): string[] =>
      ((rule?.spec as AbilitySpecBase | undefined)?.grants ?? []).flatMap((entry) =>
        (entry.grants ?? []).map((grant) => (grant as { characteristic_code?: string }).characteristic_code ?? ''),
      );
    expect(grantTargets(byCode.get('razvitie-pamyati'))).toContain('memory');
    expect(grantTargets(byCode.get('razvitie-myshleniya'))).toContain('reasoning');
  });

  it('агрегаты «Развитие X» не несут method-признак и описывают уровни данных', () => {
    const cases = [
      ['razvitie-vospriyatiya', 56],
      ['razvitie-intellekta', 57],
      ['razvitie-obscheniya', 58],
    ] as const;
    for (const [code, methodKeyword] of cases) {
      const rule = byCode.get(code);
      expect(rule?.keywordIds).not.toContain(methodKeyword);
      const aggregate = (rule?.spec as AbilitySpecBase | undefined)?.aggregate;
      expect(aggregate?.levels.length).toBeGreaterThan(0);
    }
  });

  it('агрегат «Развитие X» имеет дар characteristic_modify от своего уровня', () => {
    const vosp = byCode.get('razvitie-vospriyatiya') as Rule | undefined;
    const grants = ((vosp?.spec as AbilitySpecBase | undefined)?.grants ?? []).flatMap((entry) => entry.grants ?? []);
    const modify = grants.find((grant) => grant.type === 'characteristic_modify') as
      { characteristic_code?: string; amount?: { type?: string; ability_code?: string } } | undefined;
    expect(modify?.characteristic_code).toBe('perception');
    expect(modify?.amount).toMatchObject({ type: 'ability_level', ability_code: 'razvitie-vospriyatiya' });
  });

  it('«Физическое развитие» — один навык с пулом 9, без основ', () => {
    expect(byCode.has('osnovy-fizicheskogo-razvitiya')).toBe(false);
    const spec = abilitySpec('fizicheskoe-razvitie');
    expect(spec?.zones?.or).toMatchObject({ kind: 'parameter_sum_tables', max_level: 9 });
    expect(spec?.parameters?.map((parameter) => parameter.code)).toEqual(['strength', 'endurance', 'dexterity']);
    const speed = abilitySpec('trenirovka-skorosti');
    expect(speed?.requirements?.[0]?.requirements).toContainEqual({
      type: 'has_ability',
      ability_code: 'fizicheskoe-razvitie',
      min_level: 1,
    });
  });
});
