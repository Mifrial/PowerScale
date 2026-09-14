import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { AbilitySpecBase } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecBase';
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { AbilityParameter } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityParameter';
import { damageTypeSpecService } from '@/modules/Roleplay/Rule/Service/Instance/damageTypeSpecService';
import { ATTRACTIVENESS_STATE_CODE } from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';
import {
  CHECK_FINE_MOTOR_CODE,
  CHECK_INSIGHT_CODE,
  CHECK_INTIMIDATION_CODE,
  CHECK_VOICE_MUSIC_CODE,
} from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import type { SenseStatus } from '@/modules/Roleplay/Rule/Enum/SenseStatus';
import type { LightingLevel } from '@/modules/Roleplay/Rule/Enum/LightingLevel';

/**
 * Импорт черт из docs/rule/AI.html (раздел «Создание основы», S2).
 * Данные ложатся на модель S3: параметры (kind 'parameter'), resistance-грант,
 * группы (группирующие правила type 'group' + group_code у участников, признак домена Внешность/Голос/Слух/Зрение),
 * признак «общая» (keyword common) + механика purchase_surcharge.
 * Отложено (вне этого набора): спеллы, черты монстра, Энергохранилище X, Однорукий, Зверолюди.
 */

const TRAIT_KEYWORD = 11; // trait
const FEATURE_KEYWORD = 12; // feature
const COMMON_KEYWORD = 20; // «Общая» — раздел «Общие черты», участвует в прогрессивной доплате
const RACIAL_KEYWORD = 31; // «Расовая» — доступна только если предоставляет раса
const GROUP_KEYWORD = 42; // «Группа» — тип группирующего правила
const APPEARANCE_KEYWORD = 43;
const INNATE_KEYWORD = 44; // «Врождённая» — врождённая черта (от тела/вида)
const CHARACTERISTIC_KEYWORD = 45; // «Характеристика» — черта характеристик (вкладка «Характеристики»)
const MODIFIER_KEYWORD = 46; // «Модификатор» — даёт модификатор ±X к характеристике
const SOCIABILITY_KEYWORD = 48; // «Общительность» — особенность личности
const ATTENTIVENESS_KEYWORD = 49; // «Внимательность» — особенность личности
const WEALTH_KEYWORD = 50; // «Богатство» — особенность богатства (не в лимите числа особенностей)
const MEMORY_KEYWORD = 51; // «Память» — особенность личности
const INSIGHT_KEYWORD = 52; // «Проницательность» — особенность личности
const VOICE_KEYWORD = 223;
const HEARING_KEYWORD = 224;
const VISION_KEYWORD = 225;

const GROUP_DOMAIN_KEYWORD: Record<string, number> = {
  appearance: APPEARANCE_KEYWORD,
  voice: VOICE_KEYWORD,
  hearing: HEARING_KEYWORD,
  vision: VISION_KEYWORD,
};

const dim = (base: number, size = 0) => ({ base, size });

let nextId = 73;

// Правила Фазы 4 (возраст/особенности) — отдельный счётчик после занятых диапазонов
// (каталог: nextId с 73, расы 116–135, 136–138 чувства/Совершенство; группы личности — olNextId).
let olNextId = 145;

interface TraitSpec {
  type: 'trait' | 'feature';
  zones: AbilitySpecBase['zones'];
  group_code?: string | null;
  requirements?: Requirement[];
  grants?: Grant[];
  parameters?: AbilityParameter[];
  keywordIds?: number[];
  movement_step_size_delta?: number;
  contentNote?: string;
  catalogSection?: string | null;
}

const traitRule = (
  code: string,
  name: string,
  description: string,
  spec: TraitSpec,
  common: boolean,
  racial = false,
): Rule => {
  const abilitySpec: AbilitySpec = {
    type: spec.type,
    zones: spec.zones,
    requirements: spec.requirements?.length ? [{ level: 1, requirements: spec.requirements }] : [],
    grants: spec.grants?.length ? [{ level: 1, grants: spec.grants }] : [],
    parent_ability_code: null,
    ...(spec.group_code ? { group_code: spec.group_code } : {}),
    ...(spec.parameters ? { parameters: spec.parameters } : {}),
    ...(spec.movement_step_size_delta !== undefined ? { movement_step_size_delta: spec.movement_step_size_delta } : {}),
  };
  const typeKeyword = spec.type === 'trait' ? TRAIT_KEYWORD : FEATURE_KEYWORD;
  const keywordIds = [
    typeKeyword,
    ...(common ? [COMMON_KEYWORD] : []),
    ...(racial ? [RACIAL_KEYWORD] : []),
    ...(spec.group_code && GROUP_DOMAIN_KEYWORD[spec.group_code] ? [GROUP_DOMAIN_KEYWORD[spec.group_code]] : []),
    ...(spec.keywordIds ?? []),
  ];

  return {
    id: nextId++,
    code,
    type: 'ability',
    name,
    description,
    spaceId: 1,
    spec: abilitySpec,
    keywordIds,
    mechanicId: null,
    mechanicPayload: null,
    createdAt: 1786096800,
    ...(spec.contentNote ? { contentNote: spec.contentNote } : {}),
    ...(spec.catalogSection !== undefined ? { catalogSection: spec.catalogSection } : {}),
  };
};

/** Группирующее правило (type 'group'): контейнер с лимитом выбора. */
const groupRule = (
  code: string,
  name: string,
  description: string,
  selectLimit: number,
  catalogSection?: string,
  id?: number,
): Rule => ({
  id: id ?? nextId++,
  code,
  type: 'ability',
  name,
  description,
  spaceId: 1,
  spec: { type: 'group', selectLimit },
  keywordIds: [GROUP_KEYWORD],
  mechanicId: null,
  mechanicPayload: null,
  createdAt: 1786096800,
  ...(catalogSection ? { catalogSection } : {}),
});

const attractivenessModify = (value: number, source_code: string): Grant => ({
  type: 'state_modify',
  state_code: ATTRACTIVENESS_STATE_CODE,
  amount: { type: 'fixed', value },
  source_code,
});

const modify = (characteristic_code: string, value: number): Grant => ({
  type: 'characteristic_modify',
  characteristic_code,
  amount: { type: 'fixed', value },
  source_code: 'innate',
});

const senseModify = (
  sense_code: string,
  value: number,
  status?: SenseStatus,
  treatAsGoodDownTo?: LightingLevel,
): Grant => ({
  type: 'sense_modify',
  sense_code,
  amount: { type: 'fixed', value },
  source_code: 'perfection',
  ...(status ? { status } : {}),
  ...(treatAsGoodDownTo ? { treat_as_good_down_to: treatAsGoodDownTo } : {}),
});

const sizeModify = (characteristic_code: string, value: number): Grant => ({
  type: 'characteristic_modify',
  characteristic_code,
  amount: { type: 'fixed', value },
  source_code: 'from-size',
});

const BASIC_SOURCES = 'basic-sources';
const BASIC_SENSES = 'basic-senses';

const sourceRule = (
  id: number,
  code: string,
  name: string,
  description: string,
  extra: { createdAt?: number; contentNote?: string } = {},
): Rule => ({
  id,
  code,
  type: 'source',
  name,
  description,
  spaceId: 1,
  keywordIds: [],
  mechanicId: null,
  mechanicPayload: null,
  createdAt: extra.createdAt ?? 1787824800,
  catalogSection: BASIC_SOURCES,
  ...(extra.contentNote ? { contentNote: extra.contentNote } : {}),
});
const INNATE_INDIVIDUAL = 'abilities-innate-individual';
const INNATE_CHARACTERISTICS = 'abilities-innate-characteristics';
const INNATE_COMMON = 'abilities-innate-common';
const INNATE_NOT_COMMON_NOTE = 'Не признак «общая»: не входит в прогрессивную доплату.';
const INNATE_COST_TEXT = 'Цена по таблице: −3/−2/−1 ОС за x −3/−2/−1 (возвращают ОС); 2/4/8 ОС за x +1/+2/+3.';

const SENSE_HEARING = 'sense-hearing';
const SENSE_VISION = 'sense-vision';

const osCost = (cost: number): AbilitySpecBase['zones'] => ({ os: { kind: 'array', levels_cost: [cost] } });

/** Табличная цена параметра «X» (S8, «Телосложение»): модификатор → ОС (отрицательные возвращают ОС). */
const INNATE_COSTS: Record<string, number> = { '-3': -3, '-2': -2, '-1': -1, '1': 2, '2': 4, '3': 8 };

/** Грант «модификатор характеристики от Телосложения/тела»: amount = параметр x (может быть отрицательным). */
const innateModify = (characteristic_code: string): Grant => ({
  type: 'characteristic_modify',
  characteristic_code,
  amount: { type: 'parameter', parameter_code: 'x', per_unit: 1 },
  source_code: 'innate',
});

/** Черта «Врождённая <Характеристика>»: табличная цена по параметру x (S8). */
const innateTrait = (
  code: string,
  name: string,
  characteristic_code: string,
  description: string,
  linked?: { ability_code: string; parameter_code: string; max_delta: number },
  limits?: { min: number; max: number },
): Rule =>
  traitRule(
    code,
    name,
    description,
    {
      type: 'trait',
      zones: { os: { kind: 'parameter_table', parameter_code: 'x', costs: INNATE_COSTS } },
      parameters: [
        {
          code: 'x',
          label: 'X',
          resolution: 'purchase',
          default: dim(0),
          min: dim(limits?.min ?? -3),
          max: dim(limits?.max ?? 3),
          ...(linked ? { linked } : {}),
        },
      ],
      grants: [innateModify(characteristic_code)],
      keywordIds: [INNATE_KEYWORD, CHARACTERISTIC_KEYWORD, MODIFIER_KEYWORD],
      catalogSection: INNATE_CHARACTERISTICS,
      contentNote: INNATE_NOT_COMMON_NOTE,
    },
    // Врождённые черты характеристик — НЕ «общие черты» (свой блок «Характеристики»): иначе
    // механика прогрессивной доплаты (purchase_surcharge, фильтр «общая») ошибочно доплачивает ОС.
    false,
  );

const olCost = (cost: number): AbilitySpecBase['zones'] => ({ ol: { kind: 'array', levels_cost: [cost] } });

const PERSONALITY_SECTION = 'abilities-personality';
const STRESS_NOTE = 'Системы стресса нет; текст про стресс не исполняется.';

/** Особенность личности (Фаза 4): зона ol, отрицательная стоимость даёт ОЛ. */
const personalityRule = (
  code: string,
  name: string,
  description: string,
  cost: number,
  options: { group_code?: string; grants?: Grant[]; keywordIds?: number[]; contentNote?: string } = {},
): Rule => ({
  id: olNextId++,
  code,
  type: 'ability',
  name,
  description,
  spaceId: 1,
  spec: {
    type: 'feature',
    zones: olCost(cost),
    requirements: [],
    grants: options.grants?.length ? [{ level: 1, grants: options.grants }] : [],
    parent_ability_code: null,
    ...(options.group_code ? { group_code: options.group_code } : {}),
  },
  keywordIds: [
    FEATURE_KEYWORD,
    ...(options.group_code && GROUP_DOMAIN_KEYWORD[options.group_code]
      ? [GROUP_DOMAIN_KEYWORD[options.group_code]]
      : []),
    ...(options.keywordIds ?? []),
  ],
  mechanicId: null,
  mechanicPayload: null,
  createdAt: 1786269600,
  catalogSection: PERSONALITY_SECTION,
  ...(options.contentNote ? { contentNote: options.contentNote } : {}),
});

/** Стартовый капитал от особенности богатства: значение = apply(fixed, percent% от лимита денег). */
const moneyGrant = (fixed: number, percent: number, apply: 'max' | 'min'): Grant => ({
  type: 'money',
  fixed,
  percent,
  apply,
});

/** Модификатор характеристики «от характера/алкоголизма» для особенности личности. */
const personalityModify = (characteristic_code: string, delta: number, source_code: string): Grant => ({
  type: 'characteristic_modify',
  characteristic_code,
  amount: { type: 'fixed', value: delta },
  source_code,
});

const grantAbility = (ability_code: string, level = 1): Grant => ({ type: 'ability', ability_code, level });

const importedRules: Rule[] = [
  // --- Уникальные черты (не «Общие»: без признака common, вне доплаты; доступны только от расы) ---
  traitRule(
    'fast-footed',
    'Быстроногий',
    'В беге каждый шаг — два шага.',
    {
      type: 'trait',
      zones: osCost(2),
      grants: [{ type: 'process_distance_multiplier', ability_code: 'run', multiplier: 2 }],
      contentNote:
        'Грант process_distance_multiplier ×2 на процесс «Бег». Бег грант пока не читает; шаг ходьбы не меняется.',
      catalogSection: 'abilities-innate-individual',
    },
    false,
    true,
  ),
  traitRule(
    'thick-fingers',
    'Толстые пальцы',
    'Черта даёт -6 от состояния для проверок на мелкую моторику.',
    {
      type: 'trait',
      zones: osCost(-1),
      grants: [
        {
          type: 'characteristic_modify',
          characteristic_code: 'dexterity',
          amount: { type: 'fixed', value: -6 },
          source_code: 'from-state',
          check_codes: [CHECK_FINE_MOTOR_CODE],
        },
      ],
      catalogSection: 'abilities-innate-individual',
    },
    false,
    true,
  ),
  {
    ...traitRule(
      'magic-resistance',
      'Сопротивление магии',
      '+X устойчивости к арканному урону. Это не только уменьшает входящий арканный урон, но и увеличивает сложность проверок на сотворение волшебства против вас на X.',
      {
        type: 'trait',
        zones: { os: { kind: 'parameter', parameter_code: 'x', per_unit: 2 } },
        parameters: [{ code: 'x', label: 'X', resolution: 'purchase', default: dim(1), min: dim(0), max: dim(10) }],
        grants: [
          {
            type: 'resistance',
            damage_type_code: 'arcane',
            value: { type: 'parameter', parameter_code: 'x', per_unit: 1 },
            source_code: 'innate',
          },
        ],
      },
      false,
      true,
    ),
    catalogSection: 'abilities-innate-magic-individual',
  },

  // --- Внешность (группа «1 из группы») → статус Привлекательность ---
  traitRule(
    'repulsive',
    'Омерзительная',
    'Черта даёт −2 к состоянию «Привлекательность» от внешности. Помехи и преимущества на убеждение, обман, торговлю и обольщение считает само состояние, не черта.',
    {
      type: 'trait',
      zones: osCost(-2),
      group_code: 'appearance',
      grants: [attractivenessModify(-2, 'from-appearance')],
    },
    true,
  ),
  traitRule(
    'ugly',
    'Уродливая',
    'Черта даёт −1 к состоянию «Привлекательность» от внешности. Помехи и преимущества на убеждение, обман, торговлю и обольщение считает само состояние, не черта.',
    {
      type: 'trait',
      zones: osCost(-1),
      group_code: 'appearance',
      grants: [attractivenessModify(-1, 'from-appearance')],
    },
    true,
  ),
  traitRule(
    'beautiful',
    'Красивая',
    'Черта даёт +1 к состоянию «Привлекательность» от внешности. Помехи и преимущества на убеждение, обман, торговлю и обольщение считает само состояние, не черта.',
    { type: 'trait', zones: osCost(2), group_code: 'appearance', grants: [attractivenessModify(1, 'from-appearance')] },
    true,
  ),
  traitRule(
    'gorgeous',
    'Восхитительная',
    'Черта даёт +2 к состоянию «Привлекательность» от внешности. Помехи и преимущества на убеждение, обман, торговлю и обольщение считает само состояние, не черта.',
    { type: 'trait', zones: osCost(4), group_code: 'appearance', grants: [attractivenessModify(2, 'from-appearance')] },
    true,
  ),

  // --- Голос (группа «1 из группы») ---
  traitRule(
    'mute',
    'Немой',
    'Вы не можете говорить: речь, пение и любые проверки, для которых нужен голос, недоступны.',
    {
      type: 'trait',
      zones: osCost(-3),
      group_code: 'voice',
      contentNote: 'Пока не реализовано. Ждёт действий с вербальным компонентом, чтобы получить спеку недоступности.',
    },
    true,
  ),
  traitRule(
    'wondrous-voice',
    'Чудесный голос',
    'Голос чарует: +1 к Привлекательности от голоса и одно преимущество на проверки музицирования голосом.',
    {
      type: 'trait',
      zones: osCost(2),
      group_code: 'voice',
      grants: [
        attractivenessModify(1, 'from-voice'),
        { type: 'check_advantage', amount: 1, check_codes: [CHECK_VOICE_MUSIC_CODE] },
      ],
    },
    true,
  ),

  // --- Слух (группа «1 из группы») → чувство ---
  traitRule(
    'deaf',
    'Глухота',
    'Вы не слышите: звуки для вас недоступны, проверки и действия, требующие слуха, невозможны.',
    {
      type: 'trait',
      zones: osCost(-4),
      group_code: 'hearing',
      grants: [senseModify(SENSE_HEARING, 0, 'absent')],
    },
    true,
  ),
  traitRule(
    'terrible-hearing',
    'Ужасный слух',
    'Слышите очень плохо: −6 к чувству Слух (вклад во Внимательность — по лучшему чувству).',
    { type: 'trait', zones: osCost(-2), group_code: 'hearing', grants: [senseModify(SENSE_HEARING, -6)] },
    true,
  ),
  traitRule(
    'weak-hearing',
    'Слабый слух',
    'Слышите хуже обычного: −3 к чувству Слух (вклад во Внимательность — по лучшему чувству).',
    { type: 'trait', zones: osCost(-1), group_code: 'hearing', grants: [senseModify(SENSE_HEARING, -3)] },
    true,
  ),
  traitRule(
    'sharp-hearing',
    'Острый слух',
    'Слышите лучше обычного: +1 к чувству Слух (вклад во Внимательность — по лучшему чувству).',
    { type: 'trait', zones: osCost(2), group_code: 'hearing', grants: [senseModify(SENSE_HEARING, 1)] },
    true,
  ),
  traitRule(
    'excellent-hearing',
    'Отличный слух',
    'Слышите отлично: +2 к чувству Слух (вклад во Внимательность — по лучшему чувству).',
    { type: 'trait', zones: osCost(3), group_code: 'hearing', grants: [senseModify(SENSE_HEARING, 2)] },
    true,
  ),
  traitRule(
    'incredible-hearing',
    'Невероятный слух',
    'Слышите значительно лучше обычного: +3 к чувству Слух (вклад во Внимательность — по лучшему чувству).',
    { type: 'trait', zones: osCost(4), group_code: 'hearing', grants: [senseModify(SENSE_HEARING, 3)] },
    true,
  ),

  // --- Зрение (группа «1 из группы») → чувство ---
  traitRule(
    'blind',
    'Слепота',
    'Вы не видите: зрение недоступно.',
    {
      type: 'trait',
      zones: osCost(-4),
      group_code: 'vision',
      grants: [senseModify(SENSE_VISION, 0, 'absent')],
    },
    true,
  ),
  traitRule(
    'terrible-vision',
    'Ужасное зрение',
    'Видите очень плохо: −6 к чувству Зрение (вклад во Внимательность — по лучшему чувству).',
    { type: 'trait', zones: osCost(-2), group_code: 'vision', grants: [senseModify(SENSE_VISION, -6)] },
    true,
  ),
  traitRule(
    'weak-vision',
    'Слабое зрение',
    'Видите хуже обычного: −3 к чувству Зрение (вклад во Внимательность — по лучшему чувству).',
    { type: 'trait', zones: osCost(-1), group_code: 'vision', grants: [senseModify(SENSE_VISION, -3)] },
    true,
  ),
  traitRule(
    'sharp-vision',
    'Острое зрение',
    'Видите лучше обычного: +1 к чувству Зрение (вклад во Внимательность — по лучшему чувству).',
    { type: 'trait', zones: osCost(2), group_code: 'vision', grants: [senseModify(SENSE_VISION, 1)] },
    true,
  ),
  traitRule(
    'excellent-vision',
    'Отличное зрение',
    'Видите отлично: +2 к чувству Зрение (вклад во Внимательность — по лучшему чувству).',
    { type: 'trait', zones: osCost(3), group_code: 'vision', grants: [senseModify(SENSE_VISION, 2)] },
    true,
  ),
  traitRule(
    'incredible-vision',
    'Невероятное зрение',
    'Видите значительно лучше обычного: +3 к чувству Зрение (вклад во Внимательность — по лучшему чувству).',
    { type: 'trait', zones: osCost(4), group_code: 'vision', grants: [senseModify(SENSE_VISION, 3)] },
    true,
  ),

  // --- Устрашающий вид (feature): требует Омерзительную или Уродливую ---
  traitRule(
    'intimidating',
    'Устрашающий вид',
    'Одно преимущество на проверки запугивания. Можно взять только вместе с Омерзительной или Уродливой внешностью.',
    {
      type: 'feature',
      zones: osCost(1),
      requirements: [
        {
          type: 'or',
          children: [
            { type: 'has_ability', ability_code: 'repulsive' },
            { type: 'has_ability', ability_code: 'ugly' },
          ],
        },
      ],
      grants: [{ type: 'check_advantage', amount: 1, check_codes: [CHECK_INTIMIDATION_CODE] }],
    },
    true,
  ),

  // --- Тип урона «магия» (для resistance-гранта «Сопротивление магии X») ---
  {
    id: nextId++,
    code: 'magic-damage',
    type: 'damage_type',
    name: 'Магия',
    description: 'Урон магией и волшебством.',
    spaceId: 1,
    spec: damageTypeSpecService.createEmpty('magic-damage'),
    keywordIds: [],
    mechanicId: null,
    mechanicPayload: null,
    createdAt: 1786096800,
  },

  // --- Тип урона «холод» (для resistance-гранта «Сопротивление холоду») ---
  {
    id: nextId++,
    code: 'cold',
    type: 'damage_type',
    name: 'Холод',
    description: 'Урон холодом и низкой температурой.',
    spaceId: 1,
    spec: { ...damageTypeSpecService.createEmpty('cold'), defense_ignored: true },
    keywordIds: [],
    mechanicId: null,
    mechanicPayload: null,
    createdAt: 1786096800,
    contentNote: 'Эффектов у урона холодом пока нет. Защита не помогает (defense_ignored).',
  },

  // --- Расовые черты (признак racial): доступны только от расы ---

  traitRule(
    'cold-resistance',
    'Сопротивление холоду',
    '+X устойчивости к урону холодом.',
    {
      type: 'trait',
      zones: { os: { kind: 'parameter', parameter_code: 'x', per_unit: 1 } },
      parameters: [{ code: 'x', label: 'X', resolution: 'purchase', default: dim(1), min: dim(0), max: dim(5) }],
      grants: [
        {
          type: 'resistance',
          damage_type_code: 'cold',
          value: { type: 'parameter', parameter_code: 'x', per_unit: 1 },
          source_code: 'innate',
        },
      ],
      catalogSection: INNATE_INDIVIDUAL,
    },
    false,
    true,
  ),

  traitRule(
    'dark-vision',
    'Ночное зрение',
    'При минимальном освещении (ночь в лесу) вы видите как при хорошем. Без освещения черта не помогает.',
    {
      type: 'trait',
      zones: osCost(2),
      grants: [senseModify(SENSE_VISION, 0, undefined, 'minimal')],
      catalogSection: INNATE_INDIVIDUAL,
      contentNote: 'Спека: treat_as_good_down_to=minimal на Зрении. Освещение сцены Game пока не читает.',
    },
    false,
    true,
  ),

  traitRule(
    'beerborn',
    'Пиворождённый',
    'Вы можете питаться исключительно пивом без последствий для здоровья.',
    {
      type: 'trait',
      zones: osCost(2),
      catalogSection: INNATE_INDIVIDUAL,
      contentNote: 'Пока не реализовано. Ждёт правила еды, яда и алкоголя.',
    },
    false,
    true,
  ),

  traitRule(
    'small-step',
    'Маленький шаг',
    'Размер вашего шага на 1 меньше.',
    { type: 'trait', zones: osCost(1), movement_step_size_delta: -1, catalogSection: INNATE_INDIVIDUAL },
    false,
    true,
  ),

  traitRule(
    'big-build',
    'Бугай',
    '+3 к Весу, +2 к Силе, +1 к Стойкости от размера.',
    {
      type: 'trait',
      zones: osCost(6),
      catalogSection: INNATE_INDIVIDUAL,
      grants: [sizeModify('weight', 3), sizeModify('strength', 2), sizeModify('endurance', 1)],
    },
    false,
    true,
  ),

  traitRule(
    'seeing',
    'Видящий',
    '+3 к Внимательности от Телосложения.',
    {
      type: 'trait',
      zones: osCost(2),
      grants: [modify('attention', 3)],
      catalogSection: INNATE_INDIVIDUAL,
    },
    false,
    true,
  ),

  traitRule(
    'scenting',
    'Чующий',
    '+3 к Реакции от Телосложения.',
    {
      type: 'trait',
      zones: osCost(2),
      grants: [modify('reaction', 3)],
      catalogSection: INNATE_INDIVIDUAL,
    },
    false,
    true,
  ),

  // --- Черты «Врождённая X» (S8 «Телосложение»): модификатор характеристики от тела по таблице цен ---
  // Сила и Стойкость связаны: |X_силы − X_стойкости| ≤ 3 (док: модификатор к Силе не выше Стойкости+3).
  innateTrait(
    'innate-strength',
    'Врождённая Сила',
    'strength',
    `Модификатор Силы от тела (источник «врождённая»). Параметр x: −3…+3; 0 — без модификатора. ${INNATE_COST_TEXT} Значение x не может отличаться от Врождённой Стойкости больше чем на 3.`,
    {
      ability_code: 'innate-endurance',
      parameter_code: 'x',
      max_delta: 3,
    },
  ),
  innateTrait(
    'innate-endurance',
    'Врождённая Стойкость',
    'endurance',
    `Модификатор Стойкости от тела (источник «врождённая»). Параметр x: −3…+3; 0 — без модификатора. ${INNATE_COST_TEXT} Значение x не может отличаться от Врождённой Силы больше чем на 3.`,
    {
      ability_code: 'innate-strength',
      parameter_code: 'x',
      max_delta: 3,
    },
  ),
  innateTrait(
    'innate-dexterity',
    'Врождённая Ловкость',
    'dexterity',
    `Модификатор Ловкости от тела (источник «врождённая»). Параметр x: −3…+3; 0 — без модификатора. ${INNATE_COST_TEXT}`,
  ),
  // Восприятие и Интеллект: максимальный модификатор от Телосложения = +1 (док) → диапазон ±1.
  innateTrait(
    'innate-intellect',
    'Врождённый Интеллект',
    'intellect',
    'Модификатор Интеллекта от тела (источник «врождённая»). Параметр x только −1…+1; 0 — без модификатора. Цена: −1 ОС за x −1, 2 ОС за x +1.',
    undefined,
    { min: -1, max: 1 },
  ),
  innateTrait(
    'innate-perception',
    'Врождённое Восприятие',
    'perception',
    'Модификатор Восприятия от тела (источник «врождённая»). Параметр x только −1…+1; 0 — без модификатора. Цена: −1 ОС за x −1, 2 ОС за x +1.',
    undefined,
    { min: -1, max: 1 },
  ),

  // --- Механика «Общие черты»: 3-я и каждая последующая общая черта +2 ОС ---
  {
    id: nextId++,
    code: 'common-traits-surcharge',
    type: 'simple',
    name: 'Общие черты: прогрессивная доплата',
    description:
      'Третья и каждая следующая черта с признаком «общая» доплачивает 2 ОС сверх своей цены. Первые две таких черты без доплаты. Врождённые характеристики (Сила, Стойкость, Ловкость, Интеллект, Восприятие) в этот счёт не входят.',
    spaceId: 1,
    keywordIds: [COMMON_KEYWORD],
    mechanicId: 4,
    mechanicPayload: {
      type: 'purchase_surcharge',
      filter: { keyword_code: 'common' },
      free_count: 2,
      surcharge: 2,
    },
    createdAt: 1786096800,
    catalogSection: INNATE_COMMON,
    contentNote: 'Считает keyword common; врождённые характеристики туда не входят.',
  },

  // --- Группирующие правила (type 'group'): контейнеры «1 из группы» ---
  groupRule(
    'appearance',
    'Внешность',
    'Один вариант внешности. Член группы задаёт вклад в состояние Привлекательность от внешности: Омерзительная −2, Уродливая −1, Красивая +1, Восхитительная +2. Помехи и преимущества на убеждение, обман, торговлю и обольщение считает состояние, не группа.',
    1,
    INNATE_COMMON,
  ),
  groupRule(
    'voice',
    'Голос',
    'Один вариант голоса: Немой (по тексту речь недоступна) или Чудесный голос (+1 к Привлекательности от голоса и одно преимущество на музицирование голосом).',
    1,
    INNATE_COMMON,
  ),
  groupRule(
    'hearing',
    'Слух',
    'Один вариант слуха: Глухота (Слух отсутствует) либо модификатор чувства Слух −6 / −3 / +1 / +2 / +3. Вклад во Внимательность — по лучшему чувству, как у членов группы.',
    1,
    INNATE_COMMON,
  ),
  groupRule(
    'vision',
    'Зрение',
    'Один вариант зрения: Слепота (Зрение отсутствует) либо модификатор чувства Зрение −6 / −3 / +1 / +2 / +3. Ночное зрение в эту группу не входит.',
    1,
    INNATE_COMMON,
  ),

  sourceRule(
    610,
    'from-appearance',
    'От внешности',
    'Источник вклада в Привлекательность от черты внешности. Это ярлык слота модификатора, не группа Внешность.',
  ),
  sourceRule(
    611,
    'from-voice',
    'От голоса',
    'Источник вклада в Привлекательность от черты голоса. Это ярлык слота модификатора, не группа Голос.',
  ),
  sourceRule(
    612,
    'from-state',
    'От состояния',
    'Источник модификатора характеристики от состояния, не от базы характеристики (например Толстые пальцы).',
  ),
  sourceRule(613, 'from-size', 'От размера', 'Источник модификаторов характеристик от размера тела (например Бугай).'),
  sourceRule(136, 'perfection', 'От совершенства', 'Источник модификаторов чувств (слух, зрение, глухота, слепота).', {
    createdAt: 1786183200,
  }),

  // --- Чувства (type 'sense'): значение — модификатор к Внимательности ---
  {
    id: 137,
    code: SENSE_HEARING,
    type: 'sense',
    name: 'Слух',
    description:
      'Чувство: восприятие звуков. База — неточное. Вклад во Внимательность. Глухота ставит статус «отсутствует». В спеке радиус 30; движок радиус не читает.',
    spaceId: 1,
    spec: { type: 'sense', status: 'imprecise', radius: dim(30) },
    keywordIds: [],
    mechanicId: null,
    mechanicPayload: null,
    createdAt: 1786183200,
    catalogSection: BASIC_SENSES,
    contentNote: 'Радиус чувства в Game не используется.',
  },
  {
    id: 138,
    code: SENSE_VISION,
    type: 'sense',
    name: 'Зрение',
    description:
      'Чувство: свет и формы. База — точное. Слепота ставит статус «отсутствует». Ночное зрение — грант на этом чувстве (минимальное освещение как хорошее), не отдельное чувство. В спеке радиус 30; движок радиус не читает.',
    spaceId: 1,
    spec: { type: 'sense', status: 'precise', radius: dim(30) },
    keywordIds: [],
    mechanicId: null,
    mechanicPayload: null,
    createdAt: 1786183200,
    catalogSection: BASIC_SENSES,
    contentNote: 'Радиус чувства в Game не используется.',
  },

  // ================= Фаза 4 (S11): Личность — возраст и особенности (2026-08-09) =================

  // --- Правило «Возраст» (type 'age'): ступени с ОЛ, лимитом особенностей и эффектами ---
  {
    id: olNextId++,
    code: 'age',
    type: 'age',
    name: 'Возраст',
    description:
      'Ступень по годам персонажа и таблице лет расы; за диапазоном — «Старый». Ступень даёт ОЛ и лимит особенностей личности (без богатства). Безусловные дельты характеристик входят в значение «от возраста». Условные (усвоение нового / наличные знания) только в попапе характеристики: в число и в проверки Game не входят.',
    spaceId: 1,
    spec: {
      type: 'age',
      ages: [
        {
          name: 'Младенец',
          ol: 0,
          featureLimit: 0,
          effects: [
            { characteristic_code: 'strength', delta: -9 },
            { characteristic_code: 'endurance', delta: -9 },
            { characteristic_code: 'dexterity', delta: -9 },
            { characteristic_code: 'intellect', delta: -9 },
            { characteristic_code: 'perception', delta: -3 },
          ],
        },
        {
          name: 'Малыш',
          ol: 1,
          featureLimit: 1,
          effects: [
            { characteristic_code: 'strength', delta: -6 },
            { characteristic_code: 'endurance', delta: -6 },
            { characteristic_code: 'dexterity', delta: -6 },
            { characteristic_code: 'intellect', delta: 3, scope: 'для проверок на усвоение нового' },
            { characteristic_code: 'intellect', delta: -6, scope: 'для проверок, основанных на наличных знаниях' },
          ],
        },
        {
          name: 'Ребёнок',
          ol: 2,
          featureLimit: 1,
          effects: [
            { characteristic_code: 'strength', delta: -3 },
            { characteristic_code: 'endurance', delta: -3 },
            { characteristic_code: 'dexterity', delta: 2 },
            { characteristic_code: 'perception', delta: 3 },
            { characteristic_code: 'intellect', delta: 3, scope: 'для проверок на усвоение нового' },
            { characteristic_code: 'intellect', delta: -3, scope: 'для проверок, основанных на наличных знаниях' },
          ],
        },
        {
          name: 'Подросток',
          ol: 3,
          featureLimit: 2,
          effects: [
            { characteristic_code: 'strength', delta: -1 },
            { characteristic_code: 'endurance', delta: -1 },
            { characteristic_code: 'dexterity', delta: 1 },
            { characteristic_code: 'perception', delta: 1 },
            { characteristic_code: 'intellect', delta: 3, scope: 'для проверок на усвоение нового' },
            { characteristic_code: 'intellect', delta: -1, scope: 'для проверок, основанных на наличных знаниях' },
          ],
        },
        { name: 'Молодой', ol: 3, featureLimit: 3, effects: [] },
        {
          name: 'Взрослый',
          ol: 4,
          featureLimit: 3,
          effects: [{ characteristic_code: 'intellect', delta: -1, scope: 'для проверок на усвоение нового' }],
        },
        {
          name: 'Зрелый',
          ol: 5,
          featureLimit: 4,
          effects: [
            { characteristic_code: 'perception', delta: -1 },
            { characteristic_code: 'dexterity', delta: -1 },
            { characteristic_code: 'intellect', delta: -1, scope: 'для проверок на усвоение нового' },
          ],
        },
        {
          name: 'Пожилой',
          ol: 6,
          featureLimit: 4,
          effects: [
            { characteristic_code: 'perception', delta: -2 },
            { characteristic_code: 'dexterity', delta: -2 },
            { characteristic_code: 'endurance', delta: -2 },
            { characteristic_code: 'strength', delta: -1 },
            { characteristic_code: 'intellect', delta: -1 },
          ],
        },
        {
          name: 'Старый',
          ol: 7,
          featureLimit: 4,
          effects: [
            { characteristic_code: 'perception', delta: -3 },
            { characteristic_code: 'dexterity', delta: -3 },
            { characteristic_code: 'endurance', delta: -3 },
            { characteristic_code: 'strength', delta: -2 },
            { characteristic_code: 'intellect', delta: -2 },
          ],
        },
      ],
    },
    keywordIds: [],
    mechanicId: null,
    mechanicPayload: null,
    createdAt: 1786269600,
  },

  // --- Источники модификаторов особенностей ---
  sourceRule(olNextId++, 'character', 'От личности', 'Источник модификаторов от особенностей личности.', {
    createdAt: 1786269600,
  }),
  sourceRule(
    olNextId++,
    'alcoholism',
    'От алкоголизма',
    'Источник −1 Стойкости и −1 Силы воли у особенности Алкоголик. Стадии зависимости, проверки отрезвления и снятие стадии не реализованы.',
    {
      createdAt: 1786269600,
      contentNote: 'Полноценный алкоголизм (стадии, проверки) не реализован; слот источника для штрафов есть.',
    },
  ),
  sourceRule(176, 'development', 'От развития', 'Источник модификаторов от развития и тренировки.', {
    createdAt: 1786269600,
  }),

  groupRule(
    'sociability',
    'Личность: Общительность',
    'Один из вариантов общительности персонажа.',
    1,
    PERSONALITY_SECTION,
    olNextId++,
  ),
  groupRule(
    'attentiveness',
    'Личность: Внимательность',
    'Один из вариантов внимательности персонажа.',
    1,
    PERSONALITY_SECTION,
    olNextId++,
  ),
  groupRule(
    'wealth',
    'Личность: Богатство',
    'Один из вариантов богатства персонажа.',
    1,
    PERSONALITY_SECTION,
    olNextId++,
  ),

  personalityRule(
    'sociable',
    'Общительный',
    'Вы бесплатно получаете Тренировку Красноречия 1 и Манеру общения 1. Снятие стресса общением получает одно преимущество от личности.',
    1,
    {
      group_code: 'sociability',
      grants: [grantAbility('krasnorechie', 1), grantAbility('manera-obscheniya', 1)],
      keywordIds: [SOCIABILITY_KEYWORD],
      contentNote: STRESS_NOTE,
    },
  ),
  personalityRule(
    'withdrawn',
    'Замкнутый',
    '−3 к Красноречию от личности. Штраф уменьшается на 1 за каждый уровень навыка Развитие общения.',
    -1,
    {
      group_code: 'sociability',
      grants: [
        {
          type: 'characteristic_modify',
          characteristic_code: 'communication',
          amount: { type: 'ability_level', ability_code: 'razvitie-obscheniya', multiplier: 1, offset: -3 },
          source_code: 'character',
        },
      ],
      keywordIds: [SOCIABILITY_KEYWORD],
    },
  ),
  personalityRule(
    'bookworm',
    'Книжный червь',
    'На создании: бесплатно Владение одним языком и Письменность этого языка; плюс Грамотность этого языка или один навык с признаком «Знание». Чтение книг может заменять общение, в том числе для снятия стресса.',
    1,
    {
      contentNote:
        'Грантов нет: нужен аналог magic_study — конкретный навык, произвольный домен, paid_cost 0 (Письменность языка; Грамотность или навык с признаком knowledge). Заглушки literacy/grammar удалены. Стресса нет.',
    },
  ),
  personalityRule('brave', 'Храбрец', 'Два преимущества от личности для проверок на силу воли против Ужаса.', 1, {
    contentNote: 'Проверки Ужаса и преимуществ от личности на них в Game нет.',
  }),
  personalityRule(
    'coward',
    'Трус',
    'Сила воли снижена на размер для проверок против Ужаса и Боли. Очки Храбрости и уменьшение штрафа за 5/10/20 — не реализованы.',
    -1,
    {
      contentNote: 'Нет гранта штрафа Воли на проверки Ужаса/Боли; нет Очков Храбрости и вдохновения.',
    },
  ),
  personalityRule(
    'resilient',
    'Неунывающий',
    'Преимущество от личности для проверок воли против отрицательных эмоций и для преодоления стресса.',
    1,
    { contentNote: `${STRESS_NOTE} Проверок отрицательных эмоций нет.` },
  ),
  personalityRule(
    'alcoholic',
    'Алкоголик',
    'Вы сильно зависимы от алкоголя (вторая стадия): −1 к Стойкости и Силе воли от алкоголизма. Когда вы трезвеете, вам необходимо пройти проверку на алкоголизм (силу воли) со сложностью 2 (простому способу выпить — 3, если вам предлагают — +1). Для избавления от стадии — не более половины дня в пьяном состоянии за три месяца.',
    -1,
    {
      grants: [personalityModify('endurance', -1, 'alcoholism'), personalityModify('willpower', -1, 'alcoholism')],
      contentNote: 'Штрафы характеристик есть. Стадии, проверки отрезвления и снятие стадии не реализованы.',
    },
  ),
  personalityRule(
    'empathic',
    'Чуткий',
    'Вы получаете Проницательность 1 и одно преимущество от личности на проверки проницательности. На 1 больше стресса от личности, если стресс от группы, которой вы сочувствуете.',
    1,
    {
      group_code: 'attentiveness',
      grants: [
        grantAbility('pronitsatelnost', 1),
        { type: 'check_advantage', amount: 1, check_codes: [CHECK_INSIGHT_CODE], source_code: 'character' },
      ],
      keywordIds: [ATTENTIVENESS_KEYWORD, INSIGHT_KEYWORD],
      contentNote: STRESS_NOTE,
    },
  ),
  personalityRule(
    'pedant',
    'Педант',
    'Вы получаете Тренировку внимательности 2. Вам важно, чтобы выполнялись даже незначительные требования; иначе можете испытать стресс.',
    1,
    {
      group_code: 'attentiveness',
      grants: [grantAbility('razvitie-vnimatelnosti', 2)],
      keywordIds: [ATTENTIVENESS_KEYWORD],
      contentNote: STRESS_NOTE,
    },
  ),
  personalityRule(
    'absent-minded',
    'Рассеянный',
    '−1 к Внимательности от личности. Канон: штраф пока нет Тренировки внимательности; первые три уровня навыка за вдохновение; с третьим уровнем особенность исчезает; нельзя концентрироваться. Сейчас только штраф −1.',
    -1,
    {
      group_code: 'attentiveness',
      grants: [personalityModify('attention', -1, 'character')],
      keywordIds: [ATTENTIVENESS_KEYWORD],
      contentNote:
        'Нет снятия штрафа уровнем Тренировки внимательности, нет вдохновения/исчезновения особенности, нет блока концентрации.',
    },
  ),
  personalityRule(
    'grudge-holder',
    'Злопамятный',
    'Вы получаете Развитие памяти 1 и два преимущества от личности для проверок памяти о неприятных событиях. Можно снять стресс, когда кара настигает записанного в книжечку обид.',
    1,
    {
      grants: [grantAbility('razvitie-pamyati')],
      keywordIds: [MEMORY_KEYWORD],
      contentNote: `${STRESS_NOTE} Преимуществ на проверки памяти нет (нет гранта check_advantage).`,
    },
  ),
  personalityRule(
    'pauper',
    'Нищий',
    'Стартовый капитал уменьшен до 10%, но не более чем до 10 гз. Особенность богатства не учитывается при подсчёте числа особенностей.',
    -1,
    { group_code: 'wealth', grants: [moneyGrant(10, 10, 'min')], keywordIds: [WEALTH_KEYWORD] },
  ),
  personalityRule(
    'well-off',
    'Обеспеченный',
    'Вы получаете дополнительно 50 гз или 50% от стартового капитала (что больше). Особенность богатства не учитывается при подсчёте числа особенностей.',
    1,
    { group_code: 'wealth', grants: [moneyGrant(50, 50, 'max')], keywordIds: [WEALTH_KEYWORD] },
  ),
  personalityRule(
    'prosperous',
    'Преуспевающий',
    'Вы получаете дополнительно 100 гз или 100% от стартового капитала (что больше). Особенность богатства не учитывается при подсчёте числа особенностей.',
    2,
    { group_code: 'wealth', grants: [moneyGrant(100, 100, 'max')], keywordIds: [WEALTH_KEYWORD] },
  ),
  personalityRule(
    'rich',
    'Богатый',
    'Вы получаете дополнительно 400 гз или 400% от стартового капитала (что больше). Особенность богатства не учитывается при подсчёте числа особенностей.',
    3,
    { group_code: 'wealth', grants: [moneyGrant(400, 400, 'max')], keywordIds: [WEALTH_KEYWORD] },
  ),
];

/** Черты импорта S2, готовые к врезке в ruleCatalog. */
export const mockRuleImport: Rule[] = importedRules;
