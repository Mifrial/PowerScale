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
    expect(medicine?.multiple).toBeUndefined();
    expect(medicine?.domain_ref).toBeUndefined();
    const knowledge = abilitySpec('znanie');
    expect(knowledge?.multiple).toBe(true);
    expect(knowledge?.domain_ref).toBeUndefined();
    const trade = abilitySpec('torgovlya');
    expect(trade?.multiple).toBe(true);
    expect(trade?.domain_ref).toBe('region');
    const writing = abilitySpec('pismennost');
    expect(writing?.multiple).toBe(true);
    expect(writing?.domain_ref).toBe('script');
    expect(writing?.parent_ability_code).toBeNull();
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
    const wide = abilitySpec('shirokiy-udar');
    const sweeping = abilitySpec('razmashistyy-udar');

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
    expect(sweeping?.action_effects).toContainEqual({
      type: 'current_action_attack_characteristic_modifier',
      delta: 2,
      scope: { components: ['strike'], hit_count: 1 },
    });
    expect(sweeping?.action_effects).toContainEqual({
      type: 'current_action_check_modifier',
      check_codes: ['check-hit'],
      delta: -2,
    });
    expect(sweeping?.action_effects).toContainEqual({
      type: 'after_action_until_resource_spent_check_modifier',
      resource_code: 'action-points',
      amount: 2,
      check_codes: ['check-hit'],
      delta: -2,
    });
    expect(wide?.attack_mode).toBe('wide');
    expect(wide?.max_targets).toBe(3);
    expect(sweeping?.attack_mode).toBeUndefined();
    expect(wide?.requirements?.[0]?.requirements).toContainEqual({
      type: 'and',
      children: expect.arrayContaining([
        {
          type: 'has_ability',
          ability_code: 'razmashistyy-udar',
          min_level: 1,
        },
      ]),
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

  it('Смертоносные удары — strike_upgrade с калечить/щадить', () => {
    const rule = mockDevelopmentImport.find((item) => item.code === 'smertonosnye-udary');
    const spec = rule?.type === 'ability' ? (rule.spec as AbilitySpecBase) : undefined;
    expect(spec?.parent_ability_code).toBe('khirurgiya');
    expect(spec?.strike_upgrade).toEqual({
      exclusive_group: 'smertonosnye-udary',
      requires_physiology: true,
      modes: [
        { code: 'cripple', label: 'Калечить', injury_check_advantage: 1 },
        { code: 'spare', label: 'Щадить', injury_check_advantage: -1 },
      ],
    });
  });

  it('Серия ударов описана как процесс с входом, повтором и эффектом завершения', () => {
    const rule = mockDevelopmentImport.find((item) => item.code === 'seriya-udarov');
    const spec =
      rule?.type === 'ability' && rule.spec && 'type' in rule.spec && rule.spec.type === 'process' ? rule.spec : null;

    expect(spec).not.toBeNull();
    if (!spec) return;

    expect(spec.process.start_step_code).toBe('part-1');
    expect(spec.process.transition).toEqual({
      mode: 'custom',
      edges: [
        { from: 'part-1', to: 'part-2' },
        { from: 'part-2', to: 'part-2' },
      ],
    });
    expect(spec.process.failure).toBe('end_action');
    expect(spec.process.steps.map((step) => step.code)).toEqual(['part-1', 'part-2']);
    expect(spec.process.completion_effects).toMatchObject([
      { type: 'after_action_until_resource_spent_check_modifier', amount: 1, delta: -1 },
    ]);
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
    expect(vnim?.name).toBe('Тренировка внимательности');
    expect(vnim?.keywordIds).toEqual(expect.arrayContaining([13, 232, 49]));
    expect(vnim?.keywordIds).not.toContain(56); // НЕ метод развития восприятия
    expect(reak?.keywordIds).toEqual(expect.arrayContaining([13, 232, 53]));
    expect(reak?.catalogSection).toBe('abilities-acquired-mental-perception');
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

  it('навыки «Знание *» несут признак knowledge (67)', () => {
    expect(byCode.get('znanie')?.keywordIds).toContain(67);
    for (const code of [
      'znanie-zakonov',
      'znanie-o-zhivotnykh',
      'znanie-o-rasteniyakh',
      'znanie-o-istorii',
      'znanie-bolezney',
      'fiziologiya',
    ]) {
      expect(byCode.get(code)?.keywordIds, code).toContain(67);
      expect(abilitySpec(code)?.knowledge_template_field, code).toBeTruthy();
    }
  });

  it('интеллектуальные навыки имеют требования и честные notes знаний', () => {
    expect(abilitySpec('matematika')?.requirements?.[0]?.requirements).toEqual(
      expect.arrayContaining([
        { type: 'has_ability', ability_code: 'schet', min_level: 1 },
        { type: 'has_ability', ability_code: 'pismennost', min_level: 1 },
      ]),
    );
    expect(abilitySpec('fizika')?.requirements?.[0]?.requirements).toEqual(
      expect.arrayContaining([
        { type: 'has_ability', ability_code: 'estestvoznanie', min_level: 1 },
        { type: 'has_ability', ability_code: 'matematika', min_level: 1 },
      ]),
    );
    expect(abilitySpec('torgovlya')?.requirements?.[0]?.requirements).toContainEqual({
      type: 'has_ability',
      ability_code: 'schet',
      min_level: 1,
    });

    for (const code of ['znanie-zakonov', 'znanie-o-zhivotnykh', 'znanie-o-rasteniyakh', 'znanie-o-istorii']) {
      const rule = byCode.get(code);
      expect(rule?.keywordIds).toContain(67);
      expect(rule?.contentNote).toBeTruthy();
    }
    expect(byCode.get('zaschita-ot-zakona')?.contentNote).toBeTruthy();
  });

  it('Шифр и медицина имеют согласованные требования и секции', () => {
    expect(abilitySpec('shifr')?.requirements?.[0]?.requirements).toEqual(
      expect.arrayContaining([
        { type: 'has_ability', ability_code: 'schet', min_level: 1 },
        { type: 'has_ability', ability_code: 'pismennost', min_level: 1 },
      ]),
    );
    for (const code of ['pervaya-pomosch', 'sporaya-perevyazka', 'ukhod', 'farmatsiya', 'khirurgiya', 'pitanie']) {
      expect(byCode.get(code)?.catalogSection, code).toBe('abilities-acquired-medicine');
      expect(abilitySpec(code)?.domain_ref, code).toBeUndefined();
      expect(abilitySpec(code)?.multiple, code).toBeUndefined();
    }
    expect(byCode.get('predpisaniya-o-lechenii')).toBeUndefined();
    expect(byCode.get('znanie')?.catalogSection).toBe('abilities-acquired-mental-intellect');
    expect(abilitySpec('znanie')?.multiple).toBe(true);
    expect(abilitySpec('znanie')?.zones?.or).toEqual({ kind: 'array', levels_cost: [1, 2, 2] });
    expect(abilitySpec('zaschita-ot-zakona')?.parent_ability_code).toBe('znanie');
    expect(abilitySpec('zaschita-ot-zakona')?.parent_knowledge_field).toBe('laws');
    expect(byCode.get('sporaya-perevyazka')?.contentNote).toBeTruthy();
    expect(byCode.get('znanie-bolezney')?.contentNote).toBeTruthy();
    expect(byCode.get('farmatsiya')?.contentNote).toBeTruthy();
    expect(byCode.get('khirurgiya')?.contentNote).toBeTruthy();
  });

  it('Воля и физическое развитие имеют согласованные секции и цены', () => {
    for (const code of [
      'trenirovka-voli',
      'podavlenie-somneniy',
      'nesgibaemyy-razum',
      'otkhodchivost',
      'adaptatsiya',
    ]) {
      expect(byCode.get(code)?.catalogSection, code).toBe('abilities-acquired-mental-will');
    }
    expect(byCode.get('fizicheskoe-razvitie')?.catalogSection).toBe('abilities-acquired-physical');
    expect(abilitySpec('trenirovka-skorosti')?.zones?.or).toEqual({ kind: 'array', levels_cost: [3] });
    expect(byCode.get('manevrennost')?.contentNote).toBeTruthy();
  });

  it('Скрытность даёт бонус к проверке, а физические улучшения имеют требования', () => {
    const stealthGrants = (abilitySpec('skrytnost')?.grants ?? []).flatMap((entry) => entry.grants ?? []);
    expect(stealthGrants).toHaveLength(3);
    expect(stealthGrants).toEqual(
      expect.arrayContaining([
        {
          type: 'check_advantage',
          amount: 2,
          check_codes: ['stealth'],
          source_code: 'training',
        },
      ]),
    );
    expect(stealthGrants.every((grant) => grant.type !== 'characteristic_modify')).toBe(true);
    expect(abilitySpec('polnoe-otstuplenie')?.requirements?.[0]?.requirements).toEqual(
      expect.arrayContaining([
        { type: 'has_ability', ability_code: 'boevaya-akrobatika', min_level: 1 },
        { type: 'has_ability', ability_code: 'bezoruzhnyy-boy', min_level: 1 },
      ]),
    );
    expect(abilitySpec('udar-nogami')?.requirements?.[0]?.requirements).toContainEqual({
      type: 'has_ability',
      ability_code: 'akrobatika',
      min_level: 2,
    });
  });

  it('Баланс и общие навыки имеют согласованные требования и секции', () => {
    expect(abilitySpec('soblyusti-balans')?.requirements?.[0]?.requirements).toContainEqual({
      type: 'has_ability',
      ability_code: 'akrobatika',
      min_level: 3,
    });
    expect(abilitySpec('kontrol-balansa')?.requirements?.[0]?.requirements).toContainEqual({
      type: 'has_ability',
      ability_code: 'akrobatika',
      min_level: 3,
    });
    expect(abilitySpec('trenirovka-melkoy-motoriki')?.grants?.[0]?.grants).toContainEqual({
      type: 'characteristic_modify',
      characteristic_code: 'fine-motor',
      amount: { type: 'ability_level', ability_code: 'trenirovka-melkoy-motoriki', multiplier: 1 },
      source_code: 'training',
    });
    for (const code of [
      'soblyusti-balans',
      'kontrol-balansa',
      'pryzhki-s-vysoty',
      'razdelka-tush',
      'vladenie-muzykalnym-instrumentom',
      'muzitsirovanie',
      'trenirovka-melkoy-motoriki',
      'vzlom',
      'vnimanie-k-detalyam',
      'opyt-vzloma',
    ]) {
      expect(byCode.get(code)?.catalogSection, code).toBe(
        code === 'soblyusti-balans' || code === 'kontrol-balansa' || code === 'pryzhki-s-vysoty'
          ? 'abilities-acquired-physical'
          : 'abilities-acquired-other',
      );
    }
  });

  it('Опыт общения считает методы по признаку method-communication', () => {
    const communicationDevelopment = abilitySpec('razvitie-obscheniya');
    expect(byCode.get('razvitie-obscheniya')?.name).toBe('Опыт общения');
    expect(communicationDevelopment?.aggregate).toEqual({
      characteristic_code: 'communication',
      method_keyword: 'method-communication',
      levels: [2, 2, 2],
    });
    const experienceGrant = communicationDevelopment?.grants?.[0]?.grants?.[0];
    expect(experienceGrant).toMatchObject({ source_code: 'experience' });
    expect(byCode.get('krasnorechie')?.keywordIds).not.toContain(58);
    expect(byCode.get('manera-obscheniya')?.keywordIds).not.toContain(58);
  });

  it('Социальные карточки пачки используют Красноречие и явную секцию', () => {
    for (const code of [
      'masterstvo-torga',
      'opytnyy-torgovets',
      'pronitsatelnyy-torgovets',
      'poverkhnostnaya-otsenka',
      'khvalebnye-rechi',
      'pronitsatelnost',
      'dobycha-informatsii',
      'kholodnyy-um',
      'moralnaya-podderzhka',
      'psikhologicheskaya-pomosch',
    ]) {
      expect(byCode.get(code)?.catalogSection, code).toBe('abilities-acquired-social');
    }
    expect(byCode.get('masterstvo-torga')?.description).toContain('Красноречию');
    expect(byCode.get('pronitsatelnost')?.description).toContain('Красноречию');
    expect(byCode.get('kholodnyy-um')?.description).toContain('Красноречию');
    expect(byCode.get('moralnaya-podderzhka')?.contentNote).toContain('стресса');
    expect(byCode.get('psikhologicheskaya-pomosch')?.contentNote).toContain('стресса');
  });

  it('Социальные карточки пачки 16 имеют секцию и очищенные формулировки', () => {
    for (const code of [
      'prorabotka-problem',
      'rabota-nad-soboy',
      'rabota-nad-soboy-2',
      'masterstvo-obmana',
      'bezuprechnyy-drug',
      'zapugivanie',
      'vnushenie-strakha',
      'obolschenie',
      'lstivye-rechi',
      'vedenie-doprosa',
    ]) {
      expect(byCode.get(code)?.catalogSection, code).toBe('abilities-acquired-social');
    }
    expect(byCode.get('masterstvo-obmana')?.description).toContain('Красноречию');
    expect(byCode.get('zapugivanie')?.description).toContain('Красноречию');
    expect(byCode.get('obolschenie')?.description).toContain('Красноречию');
    expect(byCode.get('prorabotka-problem')?.contentNote).toContain('стресс');
    expect(byCode.get('psikhologicheskaya-pomosch')?.contentNote).toContain('стресса');
  });

  it('Пачка 17 разделена на социальные действия и ближний бой', () => {
    for (const code of [
      'akterskoe-masterstvo',
      'igra-po-zhizni',
      'menyaya-maski',
      'otvlech-vnimanie',
      'prikinutsya-mertvym',
    ]) {
      expect(byCode.get(code)?.catalogSection, code).toBe('abilities-acquired-social');
    }
    for (const code of [
      'blizhniy-boy',
      'boevye-refleksy',
      'otstuplenie',
      'zaschita-znaniem',
      'adaptatsiya-k-protivniku',
    ]) {
      expect(byCode.get(code)?.catalogSection, code).toBe(
        code === 'blizhniy-boy' ? 'abilities-acquired-melee-mastery' : 'abilities-acquired-melee-combat-other',
      );
    }
    expect(byCode.get('akterskoe-masterstvo')?.description).toContain('Красноречию');
    expect(byCode.get('igra-po-zhizni')?.description).toContain('Красноречию');
    expect(byCode.get('otstuplenie')?.spec).toMatchObject({ type: 'action' });
    expect(byCode.get('otstuplenie')?.contentNote).toContain('Неустойчивости');
  });

  it('Пачка 18 содержит требования ближнего боя и корректное имя Борьбы', () => {
    expect(byCode.get('borba')?.name).toBe('Борьба');
    expect(byCode.get('borba')?.catalogSection).toBe('abilities-acquired-melee-combat-other');
    for (const code of ['podavlenie-ponimaniem', 'podderzhka', 'fekhtovanie', 'bezoruzhnyy-boy']) {
      expect(abilitySpec(code)?.requirements?.[0]?.requirements).toContainEqual({
        type: 'has_ability',
        ability_code: 'blizhniy-boy',
        min_level: 1,
      });
    }
    expect(abilitySpec('videnie-boya')?.requirements?.[0]?.requirements).toContainEqual({
      type: 'characteristic_value',
      characteristic_code: 'perception',
      min: { base: 0, size: 1 },
    });
    expect(abilitySpec('brosok-protivnikom')?.requirements?.[0]?.requirements).toContainEqual({
      type: 'has_ability',
      ability_code: 'borba',
      min_level: 1,
    });
  });

  it('Пачка 19 размещена в ближнем бою и уточняет требование Стремительного удара', () => {
    for (const code of [
      'boy-s-oruzhiem-v-neskolkikh-rukakh',
      'balans',
      'podgotovka',
      'bystryy-udar',
      'stremitelnyy-udar',
      'seriya-udarov',
      'kombinatsiya-udarov',
      'raskrytie',
      'oboerukaya-ataka',
    ]) {
      expect(byCode.get(code)?.catalogSection, code).toBe(
        ['boy-s-oruzhiem-v-neskolkikh-rukakh', 'balans', 'oboerukaya-ataka'].includes(code)
          ? 'abilities-acquired-melee-combat-quantity'
          : ['bystryy-udar', 'stremitelnyy-udar', 'seriya-udarov', 'kombinatsiya-udarov', 'raskrytie'].includes(code)
            ? 'abilities-acquired-melee-combat-speed'
            : 'abilities-acquired-melee-combat-other',
      );
    }
    expect(abilitySpec('stremitelnyy-udar')?.requirements?.[0]?.requirements).toContainEqual({
      type: 'characteristic_value',
      characteristic_code: 'reaction',
      min: { base: 3, size: 1 },
    });
    expect(byCode.get('seriya-udarov')?.description).not.toContain('внутреннее повреждение');
    expect(byCode.get('kombinatsiya-udarov')?.contentNote).toContain('Game');
    expect(byCode.get('oboerukaya-ataka')?.contentNote).toContain('обязательны');
  });

  it('Пачка 20 размещена в ближнем бою и описывает Множество ударов', () => {
    for (const code of [
      'sinkhronnaya-ataka',
      'sdvoennyy-udar',
      'mnozhestvo-ruk',
      'mnozhestvo-udarov',
      'razmashistyy-udar',
      'yarostnyy-ryvok',
      'udvoennaya-mosch',
      'tolkayuschiy-udar',
      'silovoy-udar',
      'shirokiy-udar',
    ]) {
      expect(byCode.get(code)?.catalogSection, code).toBe(
        ['sinkhronnaya-ataka', 'sdvoennyy-udar', 'mnozhestvo-ruk', 'mnozhestvo-udarov'].includes(code)
          ? 'abilities-acquired-melee-combat-quantity'
          : [
                'razmashistyy-udar',
                'yarostnyy-ryvok',
                'udvoennaya-mosch',
                'tolkayuschiy-udar',
                'silovoy-udar',
                'shirokiy-udar',
              ].includes(code)
            ? 'abilities-acquired-melee-combat-power'
            : 'abilities-acquired-melee-combat-other',
      );
    }
    expect(byCode.get('mnozhestvo-udarov')?.description).toContain('3 ОД');
    expect(byCode.get('mnozhestvo-udarov')?.description).toContain('2 ОД');
    expect(byCode.get('razmashistyy-udar')?.catalogSection).toBe('abilities-acquired-melee-combat-power');
    expect(byCode.get('yarostnyy-ryvok')?.catalogSection).toBe('abilities-acquired-melee-combat-power');
    expect(byCode.get('udvoennaya-mosch')?.catalogSection).toBe('abilities-acquired-melee-combat-power');
    expect(byCode.get('sinkhronnaya-ataka')?.contentNote).toContain('обязательны');
    expect(byCode.get('silovoy-udar')?.spec).toMatchObject({
      type: 'action',
      action_effects: [{ type: 'current_action_attack_characteristic_from_success_rating', floor_div: 2, cap: 3 }],
    });
    expect(abilitySpec('udvoennaya-mosch')?.action_effects).toEqual([
      {
        type: 'current_action_attack_characteristic_modifier',
        delta: 1,
        scope: { components: ['strike'], hit_count: 1 },
        min_occupy_hands: 2,
        damage_type_codes: ['slashing', 'blunt'],
      },
    ]);
    expect(abilitySpec('yarostnyy-ryvok')?.action_effects).toEqual([
      {
        type: 'optional_after_strike_check',
        check_code: 'check-willpower',
        difficulty: 3,
        skip_parent_pending: true,
        self_damage: { size_delta: -1, damage_type_code: 'blunt', internal: true },
      },
    ]);
    expect(byCode.get('tolkayuschiy-udar')?.spec).toMatchObject({
      type: 'action',
      push: {
        pool: 'weapon_damage',
        damage: 'weapon_times_sr',
        profiles: 'slashing_or_blunt_strike',
        posture_rating_divisor_by_damage_type: { slashing: 2 },
      },
    });
    expect(byCode.get('tolkayuschiy-udar')?.contentNote).toBeUndefined();
  });

  it('Пачка 21 размещена в ближнем бою и отмечает обязательный runtime', () => {
    for (const code of [
      'tochnyy-udar',
      'masterstvo-v-tochnosti',
      'napravlennyy-udar',
      'protivodeystvuyuschiy-udar',
      'udar-v-sochlenenie',
      'smertelnyy-udar',
      'kriticheskiy-udar',
      'vypad',
      'vyverennyy-udar',
      'riskovannyy-udar',
    ]) {
      expect(byCode.get(code)?.catalogSection, code).toBe(
        [
          'tochnyy-udar',
          'masterstvo-v-tochnosti',
          'napravlennyy-udar',
          'protivodeystvuyuschiy-udar',
          'udar-v-sochlenenie',
          'smertelnyy-udar',
          'kriticheskiy-udar',
        ].includes(code)
          ? 'abilities-acquired-melee-combat-accuracy'
          : 'abilities-acquired-melee-combat-other',
      );
      expect(byCode.get(code)?.contentNote, code).toContain('обязатель');
    }
    expect(byCode.get('smertelnyy-udar')?.name).toBe('Смертельный удар');
    expect(byCode.get('tochnyy-udar')?.contentNote).toContain('не отрабатывает');
  });

  it('Пачка 22 и карта боевых секций раскладывают прочее сражение отдельно', () => {
    for (const code of [
      'raschetlivaya-ataka',
      'obezoruzhivanie',
      'udar-v-padenii',
      'kontrudar-2',
      'prikrytie',
      'perestanovka',
      'podderzhka-v-boyu',
      'koordinatsiya',
      'obmannyy-manevr',
      'sovmestnaya-ataka',
    ]) {
      expect(byCode.get(code)?.catalogSection, code).toBe('abilities-acquired-melee-combat-other');
      expect(byCode.get(code)?.contentNote, code).toContain('обязатель');
    }
    expect(byCode.get('raschetlivaya-ataka')?.catalogSection).toBe('abilities-acquired-melee-combat-other');
    expect(byCode.get('tochnyy-udar')?.catalogSection).toBe('abilities-acquired-melee-combat-accuracy');
    expect(byCode.get('razmashistyy-udar')?.catalogSection).toBe('abilities-acquired-melee-combat-power');
    expect(byCode.get('mnozhestvo-udarov')?.catalogSection).toBe('abilities-acquired-melee-combat-quantity');
  });

  it('Пачка 23 разделяет прочее и боевые манёвры', () => {
    expect(byCode.get('opyt-koordinatsii-atak')?.catalogSection).toBe('abilities-acquired-melee-combat-other');
    for (const code of [
      'obezoruzhit-protivnika',
      'zastavit-otkrytsya',
      'razbit-zaschitu',
      'vskryt-slabost',
      'poymat-moment',
      'smenit-pozitsiyu',
    ]) {
      expect(byCode.get(code)?.catalogSection, code).toBe('abilities-acquired-melee-combat-maneuvers');
      expect(byCode.get(code)?.contentNote, code).toContain('Обязательная');
    }
  });

  it('Отложенные техники дальнего боя тоже отмечены обязательной очередью', () => {
    for (const code of [
      'mnogooborotnaya-tekhnika-metaniya',
      'moschnaya-zakrutka',
      'otrabotannaya-tekhnika',
      'bezoborotnaya-tekhnika-metaniya',
      'dalniy-brosok',
      'otrabotannaya-tekhnika-2',
      'ataka-po-nezaschischennym-mestam',
      'popadanie-po-sochleneniyam',
      'ataka-po-uyazvimym-mestam',
      'smertelnyy-vystrel',
      'riskovannaya-ataka',
      'popadanie-po-sochleneniyam-2',
    ]) {
      expect(byCode.get(code)?.contentNote, code).toContain('Обязательная');
    }
  });

  it('концентрация: требование or, грант жетонов, описание без Проворства и удержания', () => {
    const rule = byCode.get('kontsentratsiya');
    const spec = abilitySpec('kontsentratsiya');
    expect(rule?.contentNote).toBeUndefined();
    expect(rule?.description).not.toMatch(/Проворств|Телосложен|удержан|сохранен/i);
    expect(rule?.description).toContain('одного хода');
    expect(JSON.stringify(spec?.requirements)).toContain('"type":"or"');
    expect(spec?.grants?.[0]?.grants.some((grant) => grant.type === 'resource')).toBe(true);
    expect(byCode.get('concentration')?.name).toBe('Жетоны концентрации');
  });

  it('предельная концентрация: 2+2 ОР и требования размера; сосредоточение внимания удалено', () => {
    expect(byCode.get('sosredotochenie-vnimaniya')).toBeUndefined();
    const spec = abilitySpec('predelnaya-kontsentratsiya');
    expect(spec?.zones.or).toEqual({ kind: 'array', levels_cost: [2, 2] });
    expect(spec?.parent_ability_code).toBe('kontsentratsiya');
    expect(JSON.stringify(spec?.requirements)).toContain('"size":1');
    expect(JSON.stringify(spec?.requirements)).toContain('"size":2');
  });

  it('сосредоточение воли и длительное напряжение; дропнутые дети концентрации удалены', () => {
    for (const code of [
      'sosredotochenie-vnimaniya',
      'parallelnye-deystviya',
      'volevoe-usilie',
      'molnienosnaya-reaktsiya',
    ]) {
      expect(byCode.get(code), code).toBeUndefined();
    }
    const will = abilitySpec('sosredotochenie-voli');
    expect(will?.zones.or).toEqual({ kind: 'array', levels_cost: [2] });
    expect(JSON.stringify(will?.requirements)).toContain('willpower');
    expect(byCode.get('sosredotochenie-voli')?.contentNote).toBeUndefined();
    const long = abilitySpec('dlitelnoe-napryazhenie');
    expect(long?.zones.or).toEqual({ kind: 'array', levels_cost: [3] });
    expect(JSON.stringify(long?.requirements)).toContain('"size":1');
  });
});
