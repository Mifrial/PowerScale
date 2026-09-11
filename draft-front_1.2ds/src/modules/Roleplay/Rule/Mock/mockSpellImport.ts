import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { SpellSpec } from '@/modules/Roleplay/Rule/Dto/Ability/SpellSpec';
import type { SpellValue } from '@/modules/Roleplay/Rule/Dto/Ability/SpellValue';
import type { AbilityParameter } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityParameter';
import type { HitResolution } from '@/modules/Roleplay/Rule/Dto/Ability/HitResolution';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { ACTION_POINTS_RESOURCE_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/ACTION_POINTS_RESOURCE_CODE';
import { damageTypeSpecService } from '@/modules/Roleplay/Rule/Service/Instance/damageTypeSpecService';

/**
 * Импорт representative slice магии (M4). Источник наблюдений: docs/rule/spell/AI.html,
 * канон — spell-plan-02-slice / spell-plan-04-import. Не парсер HTML.
 */

const MAGIC_KEYWORD = 3;
const SKILL_KEYWORD = 13;
const ACTION_KEYWORD = 14;
const SPELL_KEYWORD = 16;
const TRAIT_KEYWORD = 11;
const INNATE_KEYWORD = 44;
const CHARACTERISTIC_KEYWORD = 45;
const GIFT_KEYWORD = 47;
const ELECTROMANCY_KEYWORD = 227;
const MAGIC_PATH_KEYWORD = 228;
const ARCANIST_KEYWORD = 229;
const PSIONIC_KEYWORD = 230;
const SHAMAN_KEYWORD = 231;
const METHOD_INTELLECT_KEYWORD = 57;
const METHOD_COMMUNICATION_KEYWORD = 141;
const SECTION_WILLPOWER_KEYWORD = 60;

const MAGIC_COSTS: Record<string, number> = {
  '3↓': 1,
  '4↓': 2,
  '5↓': 3,
  '3': 4,
  '4': 6,
  '5': 8,
  '3↑': 12,
  '4↑': 16,
  '5↑': 20,
};

const CREATED_AT = 1788775200;
let nextId = 9400;

const dim = (base: number, size = 0) => ({ base, size });
const orCost = (cost: number) => ({ or: { kind: 'array' as const, levels_cost: [cost] } });

const activationPower: AbilityParameter = {
  code: 'x',
  label: 'X',
  resolution: 'activation',
  default: dim(3, 0),
  min: dim(3, -1),
  max: dim(5, 1),
};

function rule(partial: Omit<Rule, 'spaceId' | 'createdAt' | 'mechanicId' | 'mechanicPayload'>): Rule {
  return {
    ...partial,
    spaceId: 1,
    mechanicId: null,
    mechanicPayload: null,
    createdAt: CREATED_AT,
  };
}

function spellRule(
  code: string,
  name: string,
  description: string,
  spec: Extract<AbilitySpec, { type: 'spell' }>,
  extraKeywords: number[] = [],
): Rule {
  return rule({
    id: nextId++,
    code,
    type: 'ability',
    name,
    description,
    spec,
    catalogSection: 'abilities-acquired-magic-spells-electromancy',
    keywordIds: [SKILL_KEYWORD, MAGIC_KEYWORD, ACTION_KEYWORD, SPELL_KEYWORD, ELECTROMANCY_KEYWORD, ...extraKeywords],
  });
}

const attackHit: HitResolution = { type: 'attack' };

function spellSpec(
  power: SpellValue,
  control: SpellValue,
  duration: SpellSpec['duration'],
  damage?: SpellSpec['damage'],
): SpellSpec {
  return damage ? { power, control, duration, damage } : { power, control, duration };
}

const touchComponent = { type: 'somatic' as const, note: 'Касание цели' };

function spellStudyFields(parentAbilityCode: string | null): { multiple: true; domain_ref?: 'magic-path' } {
  return parentAbilityCode ? { multiple: true } : { multiple: true, domain_ref: 'magic-path' };
}

export const mockSpellImport: Rule[] = [
  rule({
    id: nextId++,
    code: 'magic-power',
    type: 'characteristic',
    name: 'Магическая мощь',
    description: 'Базовая мощь сотворения: насколько сильное волшебство персонаж может провести через источник.',
    spec: { type: 'characteristic', group: 'magic' },
    catalogSection: 'magic-rules-characteristics',
    keywordIds: [],
  }),
  rule({
    id: nextId++,
    code: 'magic-control',
    type: 'characteristic',
    name: 'Контроль магии',
    description: 'Характеристика сотворения: насколько точно персонаж удерживает волшебство.',
    spec: { type: 'characteristic', group: 'magic' },
    catalogSection: 'magic-rules-characteristics',
    keywordIds: [],
  }),
  rule({
    id: nextId++,
    code: 'spirituality',
    type: 'characteristic',
    name: 'Духовность',
    description:
      'Характеристика пути Шамана. Старт 3↑ от Потустороннего контакта. Рост от вкладов Общения, Силы воли и Внимательности относительно 5 средних описан у контакта; сама формула в характеристике ещё не считается автоматически.',
    spec: { type: 'characteristic', group: 'important' },
    catalogSection: 'magic-rules-characteristics',
    keywordIds: [],
  }),
  rule({
    id: nextId++,
    code: 'magic-core',
    type: 'item',
    name: 'Магическое ядро',
    description:
      'Врождённый источник волшебства. Неизвлекаемый item: без отдельной механики его нельзя извлечь; после смерти разрушается примерно за пять минут. Выбирается при сотворении. Не является RuleType.source.',
    spec: {
      category: 'equipment',
      cost_gm: null,
      weight: null,
      innate: true,
      special_rule_codes: [],
    },
    catalogSection: 'items-other',
    keywordIds: [],
  }),
  rule({
    id: nextId++,
    code: 'magic-core-capacity',
    type: 'ability',
    name: 'Врождённое магическое ядро X',
    description: 'Право приобрести одно врождённое магическое ядро. X — потолок базовой магической мощи ядра.',
    spec: {
      type: 'trait',
      zones: {
        os: { kind: 'parameter_table', parameter_code: 'x', costs: MAGIC_COSTS },
      },
      requirements: [],
      grants: [
        {
          level: 1,
          grants: [
            { type: 'item', item_code: 'magic-core', quantity: 1 },
            {
              type: 'characteristic_parameter',
              characteristic_code: 'magic-power',
              parameter_code: 'x',
              per_unit: 1,
            },
          ],
        },
      ],
      parent_ability_code: null,
      parameters: [
        {
          code: 'x',
          label: 'Базовая магическая мощь',
          resolution: 'purchase',
          default: dim(3, 0),
          min: dim(3, -1),
          max: dim(5, 1),
        },
      ],
    },
    catalogSection: 'abilities-acquired-magic-sources-core',
    keywordIds: [TRAIT_KEYWORD, INNATE_KEYWORD, CHARACTERISTIC_KEYWORD, GIFT_KEYWORD],
  }),
  rule({
    id: nextId++,
    code: 'arcanist',
    type: 'magic_path',
    name: 'Арканист',
    description:
      'Путь волшебства: сотворение — проверка Интеллекта. Изучение заклинаний в рамках пути стоит наполовину меньше; заклинания за 1 ОР берутся два за одно очко.',
    spec: {
      type: 'magic_path',
      check_code: 'check-intellect',
      study_cost: { discount_fraction: 0.5, pair_base_cost: 1 },
      includes_path_codes: [],
    },
    catalogSection: 'magic-rules-paths',
    keywordIds: [],
  }),
  rule({
    id: nextId++,
    code: 'spell-sustaining',
    type: 'simple',
    name: 'Поддержание заклинаний',
    description:
      '<p>Поддерживаемое заклинание после успешного сотворения продолжает действовать, пока его поддерживают. Поддержание использует тот же источник магии, что и сотворение: занятый источник нельзя применить к другому заклинанию. Само поддержание не тратит ОД. Поддерживаемый эффект можно оборвать вручную; он сразу прекращается, если источник перестаёт быть доступен.</p><p>У поддержания своя мощь, отдельно от мощи сотворения. В первый ход мощь поддержания равна мощи сотворения; в начале каждого следующего хода её можно изменить. Стабильность поддерживаемого эффекта считается от превышения используемой Магической мощи над мощью поддержания и фиксируется после появления эффекта.</p>',
    catalogSection: 'magic-rules-casting',
    keywordIds: [],
  }),
  rule({
    id: nextId++,
    code: 'becoming-arcanist',
    type: 'ability',
    name: 'Становление Арканиста',
    description: 'Открывает изучение заклинаний со стоимостью 2 и меньше. Даёт путь «Арканист» и Контроль магии 3↓.',
    spec: {
      type: 'skill',
      zones: orCost(2),
      requirements: [
        {
          level: 1,
          requirements: [
            { type: 'has_ability', ability_code: 'estestvoznanie', min_level: 1 },
            { type: 'characteristic_value', characteristic_code: 'magic-power', min: dim(3, -1) },
          ],
        },
      ],
      grants: [
        {
          level: 1,
          grants: [
            { type: 'magic_path', path_code: 'arcanist' },
            { type: 'magic_study', scope: 'spell', max_cost: 2, path_code: 'arcanist' },
            { type: 'characteristic', characteristic_code: 'magic-control', value: dim(3, -1) },
          ],
        },
      ],
      parent_ability_code: null,
    },
    catalogSection: 'abilities-acquired-magic-paths-arcanist',
    keywordIds: [SKILL_KEYWORD, MAGIC_KEYWORD, METHOD_INTELLECT_KEYWORD, MAGIC_PATH_KEYWORD, ARCANIST_KEYWORD],
  }),
  rule({
    id: nextId++,
    code: 'structure-substitution',
    type: 'ability',
    name: 'Подмена структур волшебства',
    description: 'Открывает изучение навыков волшебства (не заклинаний) со стоимостью 2 и меньше.',
    spec: {
      type: 'skill',
      zones: orCost(2),
      requirements: [
        {
          level: 1,
          requirements: [{ type: 'has_ability', ability_code: 'becoming-arcanist', min_level: 1 }],
        },
      ],
      grants: [{ level: 1, grants: [{ type: 'magic_study', scope: 'non_spell', max_cost: 2, path_code: 'arcanist' }] }],
      parent_ability_code: null,
    },
    catalogSection: 'abilities-acquired-magic-paths-arcanist',
    keywordIds: [SKILL_KEYWORD, MAGIC_KEYWORD, METHOD_INTELLECT_KEYWORD, MAGIC_PATH_KEYWORD, ARCANIST_KEYWORD],
  }),
  rule({
    id: nextId++,
    code: 'psionic',
    type: 'magic_path',
    name: 'Псионик',
    description:
      'Путь волшебства: сотворение — проверка Силы воли. Одновременно можно держать только одно активное волшебство. Отдельной скидки изучения и пары «два за 1 ОР» нет.',
    spec: { type: 'magic_path', check_code: 'check-willpower', study_cost: null, includes_path_codes: [] },
    catalogSection: 'magic-rules-paths',
    keywordIds: [],
  }),
  rule({
    id: nextId++,
    code: 'psionic-awakening',
    type: 'ability',
    name: 'Пробуждение псионических сил',
    description:
      '<p>Во время пробуждения вы бесплатно осваиваете одно заклинание в рамках пути псионика со стоимостью 1, для которого вам хватает <a data-rule-code="magic-power">Магической мощи</a> и с требуемым <a data-rule-code="magic-control">Контролем магии</a> 3↓. При пробуждении вы немедленно его творите, вызывая эффект и успеха и провала.</p><p>Вы не можете контролировать свои пробуждённые магические силы — вы можете пытаться повторить сотворение полученного волшебства в любое время, но пока не возьмёте под контроль свои магические силы, сложность проверки будет увеличена на 3. Ваш Контроль магии для такой проверки будет считаться 3↓. Помимо этого, при сильном эмоциональном напряжении или порыве вы можете по усмотрению мастера непроизвольно сотворить волшебство — в таком случае, если вы не хотите этого, то вам необходимо пройти проверку на сотворение этого волшебства с обычной для этого сложностью (не увеличенной на 3) и тем же Контролем магии 3↓.</p><p><span class="description-example">Навык в настоящее время не реализован игромеханически и отдан на откуп игрока и мастера. Игрок выбирает заклинание в редакторе; сотворение и автокаст по решению мастера в движке не исполняются.</span></p>',
    spec: {
      type: 'skill',
      zones: orCost(0),
      requirements: [
        {
          level: 1,
          requirements: [{ type: 'characteristic_value', characteristic_code: 'magic-power', min: dim(4, -1) }],
        },
      ],
      grants: [
        {
          level: 1,
          grants: [
            {
              type: 'magic_study',
              scope: 'spell',
              max_cost: 1,
              path_code: 'psionic',
              max_instances: 1,
              paid_cost: 0,
            },
          ],
        },
      ],
      parent_ability_code: null,
    },
    catalogSection: 'abilities-acquired-magic-paths-psionic',
    keywordIds: [SKILL_KEYWORD, MAGIC_KEYWORD, SECTION_WILLPOWER_KEYWORD, MAGIC_PATH_KEYWORD, PSIONIC_KEYWORD],
  }),
  rule({
    id: nextId++,
    code: 'psionic-control',
    type: 'ability',
    name: 'Контроль Псионики',
    description:
      'Даёт путь «Псионик» и Контроль магии 3↓. Открывает изучение заклинаний и прочих навыков волшебства со стоимостью 1 и меньше.',
    spec: {
      type: 'skill',
      zones: orCost(2),
      requirements: [
        {
          level: 1,
          requirements: [
            { type: 'has_ability', ability_code: 'psionic-awakening', min_level: 1 },
            { type: 'characteristic_value', characteristic_code: 'willpower', min: dim(5) },
          ],
        },
      ],
      grants: [
        {
          level: 1,
          grants: [
            { type: 'magic_path', path_code: 'psionic' },
            { type: 'characteristic', characteristic_code: 'magic-control', value: dim(3, -1) },
            { type: 'magic_study', scope: 'spell', max_cost: 1, path_code: 'psionic' },
            { type: 'magic_study', scope: 'non_spell', max_cost: 1, path_code: 'psionic' },
          ],
        },
      ],
      parent_ability_code: null,
    },
    catalogSection: 'abilities-acquired-magic-paths-psionic',
    keywordIds: [SKILL_KEYWORD, MAGIC_KEYWORD, SECTION_WILLPOWER_KEYWORD, MAGIC_PATH_KEYWORD, PSIONIC_KEYWORD],
  }),
  rule({
    id: nextId++,
    code: 'shaman',
    type: 'magic_path',
    name: 'Шаман',
    description:
      'Путь волшебства: сотворение — проверка Духовности. Включает псионика: его волшебство считается шаманским, обратно — нет. Отдельной скидки изучения и пары «два за 1 ОР» нет.',
    spec: {
      type: 'magic_path',
      check_code: 'check-spirituality',
      study_cost: null,
      includes_path_codes: ['psionic'],
    },
    catalogSection: 'magic-rules-paths',
    keywordIds: [],
  }),
  rule({
    id: nextId++,
    code: 'otherworldly-contact',
    type: 'ability',
    name: 'Потусторонний контакт',
    description:
      '<p>Даёт путь «Шаман» и Духовность 3↑. Открывает изучение заклинаний и прочих навыков волшебства со стоимостью не выше Духовности: значение уменьшают на один размер, затем приводят к простому числу. Пока формула не считается в редакторе, потолок зафиксирован стартовым значением (3).</p>',
    spec: {
      type: 'skill',
      zones: orCost(2),
      requirements: [
        {
          level: 1,
          requirements: [
            { type: 'has_magic_path', path_code: 'psionic' },
            { type: 'magic_path_experience', path_code: 'psionic', min: 6 },
            { type: 'characteristic_value', characteristic_code: 'willpower', min: dim(5) },
            { type: 'characteristic_value', characteristic_code: 'attention', min: dim(5) },
            { type: 'characteristic_value', characteristic_code: 'communication', min: dim(5) },
          ],
        },
      ],
      grants: [
        {
          level: 1,
          grants: [
            { type: 'magic_path', path_code: 'shaman' },
            { type: 'characteristic', characteristic_code: 'spirituality', value: dim(3, 1) },
            { type: 'magic_study', scope: 'spell', max_cost: 3, path_code: 'shaman' },
            { type: 'magic_study', scope: 'non_spell', max_cost: 3, path_code: 'shaman' },
          ],
        },
      ],
      parent_ability_code: null,
    },
    catalogSection: 'abilities-acquired-magic-paths-shaman',
    keywordIds: [SKILL_KEYWORD, MAGIC_KEYWORD, METHOD_COMMUNICATION_KEYWORD, MAGIC_PATH_KEYWORD, SHAMAN_KEYWORD],
  }),
  rule({
    id: nextId++,
    code: 'careful-magic',
    type: 'ability',
    name: 'Аккуратное волшебство',
    description:
      '<p>При сотворении известного заклинания можно потратить на 1 ОД больше и получить преимущество на проверку сотворения.</p>',
    spec: {
      type: 'skill',
      zones: orCost(1),
      requirements: [],
      grants: [],
      parent_ability_code: null,
      multiple: true,
      domain_ref: 'magic-path',
      spell_upgrade: { action_point_delta: 1, check_advantage: 1 },
    },
    catalogSection: 'abilities-acquired-magic-common',
    keywordIds: [SKILL_KEYWORD, MAGIC_KEYWORD],
  }),
  rule({
    id: nextId++,
    code: 'arcane',
    type: 'damage_type',
    name: 'Арканный',
    description: 'Арканный урон. Сопротивление этому типу увеличивает Сложность сотворения.',
    spec: {
      ...damageTypeSpecService.createEmpty('arcane'),
      modifies_spell_difficulty: true,
      defense_ignored: true,
    },
    catalogSection: 'magic-rules-damage-types',
    keywordIds: [],
  }),
  rule({
    id: nextId++,
    code: 'electrocharge',
    type: 'state',
    name: 'Электрозаряд',
    description:
      'Заряд, накопленный поддерживаемым Генератором молний. Привязан к этому поддержанию; тратится на сотворение подходящего заклинания электромансии.',
    spec: {
      icon_code: 'mdi-lightning-bolt',
      value_type: 'number',
      aggregation: 'independent',
    },
    catalogSection: 'magic-rules-states',
    keywordIds: [],
  }),
  rule({
    id: nextId++,
    code: 'core-magic-deviation',
    type: 'state',
    name: 'Малое магическое отклонение',
    description:
      'Отклонение на конкретном ядре после провала сотворения. Сила снижает Магию этого ядра; в конце хода кастера сила уменьшается на 1.',
    spec: {
      icon_code: 'mdi-sine-wave',
      value_type: 'number',
      aggregation: 'independent',
    },
    catalogSection: 'magic-rules-states',
    keywordIds: [],
  }),
  rule({
    id: nextId++,
    code: 'shock',
    type: 'state',
    name: 'Шок',
    description:
      'Накопительная сила электрического поражения, снижает Ловкость. Накладывается при получении истощения от урона электричеством.',
    spec: {
      icon_code: 'mdi-flash',
      value_type: 'number',
      aggregation: 'sum',
      effects: [{ type: 'characteristic_modify', characteristic_code: 'dexterity', amount: -1, per_unit: true }],
    },
    catalogSection: 'magic-rules-states',
    keywordIds: [],
  }),
  spellRule(
    'discharge',
    'Разряд',
    '<p><strong>Вы наносите касанием [x + 3] <a data-rule-code="electricity">урона электричеством</a> цели.</strong> +4 за 10 опыта электромансии; +5 за 20 опыта; +6 за 30 опыта.</p>',
    {
      type: 'spell',
      zones: orCost(1),
      requirements: [],
      grants: [],
      parent_ability_code: null,
      ...spellStudyFields(null),
      action_components: [
        { type: 'resource', resource_code: ACTION_POINTS_RESOURCE_CODE, amount: 4, label: 'Сотворение' },
        touchComponent,
      ],
      hit_resolution: attackHit,
      parameters: [activationPower],
      spell: spellSpec(
        { type: 'parameter', parameter_code: 'x' },
        dim(3, -1),
        { type: 'instant' },
        {
          damage_type_code: 'electricity',
          experience_keyword_code: 'electromancy',
          power_modify_steps: [
            { min_experience: 0, modify: 3 },
            { min_experience: 10, modify: 4 },
            { min_experience: 20, modify: 5 },
            { min_experience: 30, modify: 6 },
          ],
        },
      ),
    },
  ),
  spellRule(
    'lightning-strike',
    'Удар молнии',
    `<p>С указующей руки срывается электрический разряд до цели, автоматически попадающий с 2 РУ атаки и наносящий [x] <a data-rule-code="electricity">урона электричеством</a>. Урон снижается на размер за каждый ипари до цели после 2. Если он должен стать меньше ${new DimensionalNumber(dim(3, -1)).toString()}, урон не наносится. При 10 опыта электромансии урон увеличивается до [x+1]; при 20 до [x+2], при 30 до [x+3].</p>`,
    {
      type: 'spell',
      zones: orCost(1),
      requirements: [
        {
          level: 1,
          requirements: [{ type: 'has_ability', ability_code: 'discharge' }],
        },
      ],
      grants: [],
      parent_ability_code: null,
      ...spellStudyFields(null),
      action_components: [
        { type: 'resource', resource_code: ACTION_POINTS_RESOURCE_CODE, amount: 4, label: 'Сотворение' },
        { type: 'somatic', note: 'указующая цель рука', occupy_hands: 1 },
      ],
      hit_resolution: { type: 'auto', rating: 2 },
      parameters: [activationPower],
      spell: spellSpec(
        { type: 'parameter', parameter_code: 'x' },
        dim(4, -1),
        { type: 'instant' },
        {
          damage_type_code: 'electricity',
          experience_keyword_code: 'electromancy',
          power_modify_steps: [
            { min_experience: 0, modify: 0 },
            { min_experience: 10, modify: 1 },
            { min_experience: 20, modify: 2 },
            { min_experience: 30, modify: 3 },
          ],
          falloff: { free_ipari: 2, size_per_extra_ipari: 1, min: dim(3, -1) },
        },
      ),
    },
  ),
  spellRule(
    'lightning-generator',
    'Генератор молний',
    '<p>Пока заклинание поддерживается, вы получаете состояние <a data-rule-code="electrocharge">Электрозаряд</a>, привязанное к этому поддержанию. При успешном сотворении и при каждом обновлении поддержания вы получаете 1 электрозаряд, но не выше капа. Кап без улучшения — 1. Оборвали Генератор — связанные электрозаряды снимаются.</p><p>Пока есть хотя бы 1 электрозаряд этого поддержания, сопутствующим действием за 2 ОД можно потратить 1 заряд и сотворить известное вам заклинание с признаком электромансии, которое не является поддерживаемым, с Созданием не больше 1 хода (стоимость сотворения в ОД не выше вашего лимита ОД на ход). Мгновенное, длительное и обновляемое — можно. Сам Генератор и другие поддерживаемые — нельзя. Требуемая мощь выбранного не выше x, контроль — ваш обычный. Каст — обычное сотворение; оплата — эти 2 ОД и заряд, не ОД создания выбранного заклинания; источник и путь — с этого Генератора.</p><p>См. <a data-rule-code="spell-sustaining">Поддержание заклинаний</a>.</p>',
    {
      type: 'spell',
      zones: orCost(1),
      requirements: [],
      grants: [],
      parent_ability_code: null,
      ...spellStudyFields(null),
      action_components: [
        { type: 'resource', resource_code: ACTION_POINTS_RESOURCE_CODE, amount: 4, label: 'Сотворение' },
      ],
      hit_resolution: { type: 'none' },
      parameters: [activationPower],
      spell: {
        ...spellSpec(dim(4, -1), dim(5, -1), {
          type: 'sustained',
          power: { type: 'parameter', parameter_code: 'x' },
        }),
        charge: {
          state_code: 'electrocharge',
          grant: 1,
          default_cap: 1,
          spend: {
            action_points: 2,
            amount: 1,
            keyword_code: 'electromancy',
            exclude_duration_types: ['sustained'],
            creation_max: 'turn_ap',
          },
        },
      },
    },
  ),
  rule({
    id: nextId++,
    code: 'chain-lightning',
    type: 'ability',
    name: 'Цепная молния',
    description: `<p>Улучшение <a data-rule-code="lightning-strike">Удара молнии</a>. При применении вместе с ним сотворение стоит на 1 ОД больше. После автопопадания, если оно нанесло повреждения, можно сделать ещё одно такое же автопопадание: урон на размер меньше, дистанция считается от последнего поражённого (снижение Удара молнии за ипари складывается с этим −1 размер). Если урон стал меньше ${new DimensionalNumber(dim(3, -1)).toString()} или повреждений не было, цепь обрывается. В ту же цель снова можно бить только через другую. Пока цепь жива, прыжок можно повторять.</p>`,
    spec: {
      type: 'skill',
      zones: orCost(1),
      requirements: [],
      grants: [],
      parent_ability_code: 'lightning-strike',
      multiple: true,
      spell_upgrade: {
        action_point_delta: 1,
        chain: {
          damage_size_per_hop: 1,
          min: dim(3, -1),
          retarget: 'from_last_hit',
          same_target: 'via_other',
        },
      },
    },
    catalogSection: 'abilities-acquired-magic-spells-electromancy',
    keywordIds: [SKILL_KEYWORD, MAGIC_KEYWORD, ELECTROMANCY_KEYWORD],
  }),
  rule({
    id: nextId++,
    code: 'charge-accumulation',
    type: 'ability',
    name: 'Накопление зарядов',
    description:
      '<p>Улучшение <a data-rule-code="lightning-generator">Генератора молний</a>. Требует 5 опыта электромансии. Кап электрозарядов этого Генератора: 2; при 20 опыта электромансии — 3; при 40 — 1 заряд за каждые 10 опыта электромансии. Два Генератора на разных источниках — две отдельные кучи.</p>',
    spec: {
      type: 'skill',
      zones: orCost(2),
      requirements: [],
      grants: [],
      parent_ability_code: 'lightning-generator',
      multiple: true,
      spell_upgrade: {
        action_point_delta: 0,
        charge_cap: {
          base: 2,
          experience_keyword_code: 'electromancy',
          steps: [
            { min_experience: 20, cap: 3 },
            { min_experience: 40, per_experience: 10 },
          ],
        },
      },
    },
    catalogSection: 'abilities-acquired-magic-spells-electromancy',
    keywordIds: [SKILL_KEYWORD, MAGIC_KEYWORD, ELECTROMANCY_KEYWORD],
  }),
];
