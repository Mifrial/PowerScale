import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { AbilitySpecBase } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecBase';
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { AbilityParameter } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityParameter';
import { damageTypeSpecService } from '@/modules/Roleplay/Rule/Service/Instance/damageTypeSpecService';
import { ATTRACTIVENESS_STATE_CODE } from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';
import { CHECK_VOICE_MUSIC_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';

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
const GIFT_KEYWORD = 47; // «Дар» — даёт значение характеристики (потолок)
const SKILL_KEYWORD = 13; // «Навык» — тип способности: навык
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
// (каталог: 1–115 nextId, 116–135 mockRaceImport, 136–138 чувства/Совершенство).
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
    id: `rule-${nextId++}`,
    code,
    type: 'ability',
    name,
    description,
    spaceId: 1,
    spec: abilitySpec,
    keywordIds,
    mechanicId: null,
    mechanic_payload: null,
    createdAt: '2026-08-07T10:00:00Z',
  };
};

/** Группирующее правило (type 'group'): контейнер с лимитом выбора. */
const groupRule = (code: string, name: string, description: string, selectLimit: number): Rule => ({
  id: `rule-${nextId++}`,
  code,
  type: 'ability',
  name,
  description,
  spaceId: 1,
  spec: { type: 'group', selectLimit },
  keywordIds: [GROUP_KEYWORD],
  mechanicId: null,
  mechanic_payload: null,
  createdAt: '2026-08-07T10:00:00Z',
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

const senseModify = (sense_code: string, value: number): Grant => ({
  type: 'sense_modify',
  sense_code,
  amount: { type: 'fixed', value },
  source_code: 'perfection',
});

const SENSE_HEARING = 'sense-hearing';
const SENSE_VISION = 'sense-vision';

const osCost = (cost: number): AbilitySpecBase['zones'] => ({ os: { kind: 'array', levels_cost: [cost] } });

/** Табличная цена параметра «X» (S8, «Телосложение»): модификатор → ОС (отрицательные возвращают ОС). */
const INNATE_COSTS: Record<string, number> = { '-3': -3, '-2': -2, '-1': -1, '1': 2, '2': 4, '3': 8 };

/** Таблица «Магия Х» (S9, док): размерное значение → ОС. Ключи — toString() размерного числа. */
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

/** Грант «модификатор характеристики от Телосложения/тела»: amount = параметр x (может быть отрицательным). */
const innateModify = (characteristic_code: string): Grant => ({
  type: 'characteristic_modify',
  characteristic_code,
  amount: { type: 'parameter', parameter_code: 'x', per_unit: 1 },
  source_code: 'innate',
});

/** Черта «Врождённая <Характеристика> X»: табличная цена по X (S8). */
const innateTrait = (
  code: string,
  name: string,
  characteristic_code: string,
  linked?: { ability_code: string; parameter_code: string; max_delta: number },
  limits?: { min: number; max: number },
): Rule =>
  traitRule(
    code,
    name,
    `Вы приобретаете модификатор +X к характеристике от тела (Телосложение).`,
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
    },
    // Врождённые черты характеристик — НЕ «общие черты» (свой блок «Характеристики»): иначе
    // механика прогрессивной доплаты (purchase_surcharge, фильтр «общая») ошибочно доплачивает ОС.
    false,
  );

const olCost = (cost: number): AbilitySpecBase['zones'] => ({ ol: { kind: 'array', levels_cost: [cost] } });

/** Особенность личности (Фаза 4): зона ol, отрицательная стоимость даёт ОЛ. */
const personalityRule = (
  code: string,
  name: string,
  description: string,
  cost: number,
  options: { group_code?: string; grants?: Grant[]; keywordIds?: number[] } = {},
): Rule => ({
  id: `rule-${olNextId++}`,
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
  mechanic_payload: null,
  createdAt: '2026-08-09T10:00:00Z',
});

/** Навык (тип 'skill') — даётся особенностью; в каталоге «Основа»/«Личность» не покупается напрямую. */
const skillRule = (code: string, name: string, description: string): Rule => ({
  id: `rule-${olNextId++}`,
  code,
  type: 'ability',
  name,
  description,
  spaceId: 1,
  spec: {
    type: 'skill',
    zones: {},
    requirements: [],
    grants: [],
    parent_ability_code: null,
  },
  keywordIds: [SKILL_KEYWORD],
  mechanicId: null,
  mechanic_payload: null,
  createdAt: '2026-08-09T10:00:00Z',
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
    { type: 'trait', zones: osCost(2) },
    false,
    true,
  ),
  traitRule(
    'thick-fingers',
    'Толстые пальцы',
    'Толстые, грубые пальцы.',
    { type: 'trait', zones: osCost(-1) },
    false,
    true,
  ),
  traitRule(
    'magic-resistance',
    'Сопротивление магии X',
    'Вы получаете +2X устойчивости к магии. Сложность проверок сотворения волшебства по вам увеличена на X.',
    {
      type: 'trait',
      zones: { os: { kind: 'parameter', parameter_code: 'x', per_unit: 2 } },
      parameters: [{ code: 'x', label: 'X', resolution: 'purchase', default: dim(1), min: dim(0), max: dim(10) }],
      grants: [
        {
          type: 'resistance',
          damage_type_code: 'magic-damage',
          value: { type: 'parameter', parameter_code: 'x', per_unit: 2 },
          source_code: 'innate',
        },
      ],
    },
    false,
    true,
  ),

  // --- Внешность (группа «1 из группы») → статус Привлекательность ---
  traitRule(
    'repulsive',
    'Омерзительная',
    'Внешность отталкивает: −2 к Привлекательности от внешности. Пока статус ниже нуля — одна помеха от внешности на убеждение, обман и торговлю и столько помех на обольщение, каково значение статуса.',
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
    'Внешность неприятна: −1 к Привлекательности от внешности. Пока статус ниже нуля — одна помеха от внешности на убеждение, обман и торговлю и столько помех на обольщение, каково значение статуса.',
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
    'Приятная внешность: +1 к Привлекательности от внешности. Пока статус выше нуля — одно преимущество от внешности на убеждение, обман и торговлю и столько преимуществ на обольщение, каково значение статуса.',
    { type: 'trait', zones: osCost(2), group_code: 'appearance', grants: [attractivenessModify(1, 'from-appearance')] },
    true,
  ),
  traitRule(
    'gorgeous',
    'Восхитительная',
    'Внешность восхищает: +2 к Привлекательности от внешности. Пока статус выше нуля — одно преимущество от внешности на убеждение, обман и торговлю и столько преимуществ на обольщение, каково значение статуса.',
    { type: 'trait', zones: osCost(4), group_code: 'appearance', grants: [attractivenessModify(2, 'from-appearance')] },
    true,
  ),

  // --- Голос (группа «1 из группы») ---
  traitRule(
    'mute',
    'Немой',
    'Вы не можете говорить: речь, пение и любые проверки, для которых нужен голос, недоступны.',
    { type: 'trait', zones: osCost(-3), group_code: 'voice' },
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
    { type: 'trait', zones: osCost(-4), group_code: 'hearing' },
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
    'Слышите почти всё: +3 к чувству Слух (вклад во Внимательность — по лучшему чувству).',
    { type: 'trait', zones: osCost(4), group_code: 'hearing', grants: [senseModify(SENSE_HEARING, 3)] },
    true,
  ),

  // --- Зрение (группа «1 из группы») → чувство ---
  traitRule(
    'blind',
    'Слепота',
    'Вы не видите: зрение недоступно, проверки и действия, требующие зрения, невозможны.',
    { type: 'trait', zones: osCost(-4), group_code: 'vision' },
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
    'Видите почти всё: +3 к чувству Зрение (вклад во Внимательность — по лучшему чувству).',
    { type: 'trait', zones: osCost(4), group_code: 'vision', grants: [senseModify(SENSE_VISION, 3)] },
    true,
  ),

  // --- Устрашающий вид (feature): требует Омерзительную или Уродливую ---
  traitRule(
    'intimidating',
    'Устрашающий вид',
    'Ваш вид пугает окружающих. Можно взять только вместе с Омерзительной или Уродливой внешностью.',
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
    },
    true,
  ),

  // --- Тип урона «магия» (для resistance-гранта «Сопротивление магии X») ---
  {
    id: `rule-${nextId++}`,
    code: 'magic-damage',
    type: 'damage_type',
    name: 'Магия',
    description: 'Урон магией и волшебством.',
    spaceId: 1,
    spec: damageTypeSpecService.createEmpty('magic-damage'),
    keywordIds: [],
    mechanicId: null,
    mechanic_payload: null,
    createdAt: '2026-08-07T10:00:00Z',
  },

  // --- Тип урона «холод» (для resistance-гранта «Сопротивление холоду») ---
  {
    id: `rule-${nextId++}`,
    code: 'cold',
    type: 'damage_type',
    name: 'Холод',
    description: 'Урон холодом и низкой температурой.',
    spaceId: 1,
    spec: damageTypeSpecService.createEmpty('cold'),
    keywordIds: [],
    mechanicId: null,
    mechanic_payload: null,
    createdAt: '2026-08-07T10:00:00Z',
  },

  // --- Расовые черты (признак racial): доступны только от расы ---

  traitRule(
    'cold-resistance',
    'Сопротивление холоду X',
    'Вы получаете X сопротивления урону холодом.',
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
    },
    false,
    true,
  ),

  traitRule(
    'dark-vision',
    'Темновидение',
    'Вы видите в полной темноте.',
    { type: 'trait', zones: osCost(2) },
    false,
    true,
  ),

  traitRule(
    'beerborn',
    'Пиворождённый',
    'Вы можете питаться исключительно пивом без последствий для здоровья.',
    { type: 'trait', zones: osCost(2) },
    false,
    true,
  ),

  traitRule(
    'small-step',
    'Маленький шаг',
    'Ваш шаг компактен и устойчив.',
    { type: 'trait', zones: osCost(1), movement_step_size_delta: -1 },
    false,
    true,
  ),

  traitRule(
    'big-build',
    'Большой',
    'Вы крупнее среднего существа вашего вида.',
    { type: 'trait', zones: osCost(3) },
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
    },
    false,
    true,
  ),

  // --- Черта характеристик «Врождённая Магия X» (дар: значение-потолок, размерная цена из дока) ---
  // Таблица «Магия Х»: значение (размерное) → ОС. «Стоимость значений от 3↑ и выше равна их значению
  // в эквиваленте маленького размера». Грант «characteristic» даёт базу характеристики Магия = X
  // (размерное значение), поэтому приобретённая Магия отображается среди характеристик.
  {
    ...traitRule(
      'magic-potential',
      'Врождённая Магия X',
      'Вы можете приобрести Магию со значением не выше X.',
      {
        type: 'trait',
        zones: {
          os: { kind: 'parameter_table', parameter_code: 'x', costs: MAGIC_COSTS },
        },
        parameters: [
          {
            code: 'x',
            label: 'X',
            resolution: 'purchase',
            default: dim(3, 0),
            min: dim(3, -1),
            max: dim(5, 1),
          },
        ],
        grants: [
          {
            type: 'characteristic_parameter',
            characteristic_code: 'magic',
            parameter_code: 'x',
            per_unit: 1,
          },
        ],
        keywordIds: [INNATE_KEYWORD, CHARACTERISTIC_KEYWORD, GIFT_KEYWORD],
      },
      true,
    ),
  },

  // --- Черты «Врождённая X» (S8 «Телосложение»): модификатор характеристики от тела по таблице цен ---
  // Сила и Стойкость связаны: |X_силы − X_стойкости| ≤ 3 (док: модификатор к Силе не выше Стойкости+3).
  innateTrait('innate-strength', 'Врождённая Сила X', 'strength', {
    ability_code: 'innate-endurance',
    parameter_code: 'x',
    max_delta: 3,
  }),
  innateTrait('innate-endurance', 'Врождённая Стойкость X', 'endurance', {
    ability_code: 'innate-strength',
    parameter_code: 'x',
    max_delta: 3,
  }),
  innateTrait('innate-dexterity', 'Врождённая Ловкость X', 'dexterity'),
  // Восприятие и Интеллект: максимальный модификатор от Телосложения = +1 (док) → диапазон ±1.
  innateTrait('innate-intellect', 'Врождённый Интеллект X', 'intellect', undefined, { min: -1, max: 1 }),
  innateTrait('innate-perception', 'Врождённое Восприятие X', 'perception', undefined, { min: -1, max: 1 }),

  // --- Механика «Общие черты»: 3-я и каждая последующая общая черта +2 ОС ---
  {
    id: `rule-${nextId++}`,
    code: 'common-traits-surcharge',
    type: 'simple',
    name: 'Общие черты: прогрессивная доплата',
    description: 'Третья и каждая последующая общая черта требует доплаты 2 ОС.',
    spaceId: 1,
    keywordIds: [COMMON_KEYWORD],
    mechanicId: 4,
    mechanic_payload: {
      type: 'purchase_surcharge',
      filter: { keyword_code: 'common' },
      free_count: 2,
      surcharge: 2,
    },
    createdAt: '2026-08-07T10:00:00Z',
  },

  // --- Группирующие правила (type 'group'): контейнеры «1 из группы» ---
  groupRule(
    'appearance',
    'Внешность',
    'Один вариант внешности. Черта задаёт вклад в статус Привлекательность от внешности (−2…+2).',
    1,
  ),
  groupRule('voice', 'Голос', 'Один вариант голоса: немой либо чудесный голос (+1 к Привлекательности от голоса).', 1),
  groupRule(
    'hearing',
    'Слух',
    'Один вариант слуха: от глухоты до невероятного слуха, со своим модификатором чувства.',
    1,
  ),
  groupRule(
    'vision',
    'Зрение',
    'Один вариант зрения: от слепоты до невероятного зрения, со своим модификатором чувства.',
    1,
  ),

  {
    id: 'rule-610',
    code: 'from-appearance',
    type: 'source',
    name: 'Внешность',
    description: 'Источник модификатора Привлекательности от черты внешности.',
    spaceId: 1,
    keywordIds: [],
    mechanicId: null,
    mechanic_payload: null,
    createdAt: '2026-08-27T10:00:00Z',
  },
  {
    id: 'rule-611',
    code: 'from-voice',
    type: 'source',
    name: 'Голос',
    description: 'Источник модификатора Привлекательности от черты голоса.',
    spaceId: 1,
    keywordIds: [],
    mechanicId: null,
    mechanic_payload: null,
    createdAt: '2026-08-27T10:00:00Z',
  },

  // --- Источник модификаторов чувств «Совершенство» ---
  {
    id: 'rule-136',
    code: 'perfection',
    type: 'source',
    name: 'Совершенство',
    description: 'Источник модификаторов: совершенствование чувств.',
    spaceId: 1,
    keywordIds: [],
    mechanicId: null,
    mechanic_payload: null,
    createdAt: '2026-08-08T10:00:00Z',
  },

  // --- Чувства (type 'sense'): значение — модификатор к Внимательности ---
  {
    id: 'rule-137',
    code: SENSE_HEARING,
    type: 'sense',
    name: 'Слух',
    description: 'Восприятие звуков.',
    spaceId: 1,
    spec: { type: 'sense' },
    keywordIds: [],
    mechanicId: null,
    mechanic_payload: null,
    createdAt: '2026-08-08T10:00:00Z',
  },
  {
    id: 'rule-138',
    code: SENSE_VISION,
    type: 'sense',
    name: 'Зрение',
    description: 'Восприятие света и форм.',
    spaceId: 1,
    spec: { type: 'sense' },
    keywordIds: [],
    mechanicId: null,
    mechanic_payload: null,
    createdAt: '2026-08-08T10:00:00Z',
  },

  // ================= Фаза 4 (S11): Личность — возраст и особенности (2026-08-09) =================

  // --- Правило «Возраст» (type 'age'): ступени с ОЛ, лимитом особенностей и эффектами ---
  {
    id: `rule-${olNextId++}`,
    code: 'age',
    type: 'age',
    name: 'Возраст',
    description:
      'Возрастные ступени: ОЛ и лимит числа особенностей личности. Ступень определяется годами персонажа и таблицей лет расы; за диапазонами — «Старый».',
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
    mechanic_payload: null,
    createdAt: '2026-08-09T10:00:00Z',
  },

  // --- Источники модификаторов особенностей ---
  {
    id: `rule-${olNextId++}`,
    code: 'character',
    type: 'source',
    name: 'Характер',
    description: 'Источник модификаторов: особенности личности.',
    spaceId: 1,
    keywordIds: [],
    mechanicId: null,
    mechanic_payload: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: `rule-${olNextId++}`,
    code: 'alcoholism',
    type: 'source',
    name: 'Алкоголизм',
    description: 'Источник модификаторов: зависимость от алкоголя.',
    spaceId: 1,
    keywordIds: [],
    mechanicId: null,
    mechanic_payload: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-176',
    code: 'development',
    type: 'source',
    name: 'Развитие',
    description: 'Источник модификаторов: развитие и тренировка.',
    spaceId: 1,
    keywordIds: [],
    mechanicId: null,
    mechanic_payload: null,
    createdAt: '2026-08-09T10:00:00Z',
  },

  // --- Навыки, которые дают особенности личности («даёт навык») ---
  skillRule('communication-mastery', 'Мастерство общения', 'Общение и переговоры.'),
  skillRule('literacy', 'Письменность', 'Письмо и чтение.'),
  skillRule('grammar', 'Грамотность', 'Грамотная речь и письмо.'),
  skillRule('knowledge', 'Знания', 'Общие знания.'),
  skillRule('attention-skill', 'Внимательность', 'Навык внимательности.'),
  skillRule('insight-skill', 'Проницательность', 'Навык проницательности.'),
  skillRule('attention-development', 'Развитие внимания', 'Тренированное внимание.'),
  skillRule('memory-development', 'Развитие памяти', 'Тренированная память.'),

  // --- Группы особенностей личности (type 'group', «1 из группы») ---
  {
    id: `rule-${olNextId++}`,
    code: 'sociability',
    type: 'ability',
    name: 'Общительность',
    description: 'Выберите один вариант общительности.',
    spaceId: 1,
    spec: { type: 'group', selectLimit: 1 },
    keywordIds: [GROUP_KEYWORD],
    mechanicId: null,
    mechanic_payload: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: `rule-${olNextId++}`,
    code: 'attentiveness',
    type: 'ability',
    name: 'Внимательность',
    description: 'Выберите один вариант внимательности.',
    spaceId: 1,
    spec: { type: 'group', selectLimit: 1 },
    keywordIds: [GROUP_KEYWORD],
    mechanicId: null,
    mechanic_payload: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: `rule-${olNextId++}`,
    code: 'wealth',
    type: 'ability',
    name: 'Богатство',
    description: 'Выберите одну особенность богатства.',
    spaceId: 1,
    spec: { type: 'group', selectLimit: 1 },
    keywordIds: [GROUP_KEYWORD],
    mechanicId: null,
    mechanic_payload: null,
    createdAt: '2026-08-09T10:00:00Z',
  },

  // --- 15 особенностей личности (зона ol; отрицательные стоимости дают ОЛ) ---
  personalityRule(
    'sociable',
    'Общительный',
    'Вы бесплатно приобретаете навык Тренировка Красноречия с уровнем 1 и навык Манера общения. Снятие стресса с помощью общения получает одно преимущество от характера.',
    1,
    {
      group_code: 'sociability',
      grants: [grantAbility('krasnorechie', 1), grantAbility('manera-obscheniya', 1)],
      keywordIds: [SOCIABILITY_KEYWORD],
    },
  ),
  personalityRule(
    'withdrawn',
    'Замкнутый',
    'Общение снижено на размер от характера, пока вы не приобретёте навык Мастерство общения. Первые три уровня навыка приобретаются только за очки вдохновения; с третьим уровнем особенность исчезает. Общение снимает на два размера меньше стресса.',
    -1,
    {
      group_code: 'sociability',
      grants: [personalityModify('communication', -1, 'character')],
      keywordIds: [SOCIABILITY_KEYWORD],
    },
  ),
  personalityRule(
    'bookworm',
    'Книжный червь',
    'Вы бесплатно приобретаете навык Письменность и один из навыков: Грамотность или любой навык Знаний. Чтение книг может для вас заменять общение, в том числе для снятия стресса.',
    1,
    { grants: [grantAbility('literacy'), grantAbility('grammar')] },
  ),
  personalityRule(
    'brave',
    'Храбрец',
    'Вы получаете два преимущества от характера для проверок на силу воли против Ужаса.',
    1,
  ),
  personalityRule(
    'coward',
    'Трус',
    'Ваша сила воли снижена на размер для проверок на силу воли против Ужаса и Боли. При успешном противостоянии страхам мастер может выдавать вам Очки Храбрости, при провале — отнимать. При достижении 5, 10 и 20 очков в первый раз штраф навсегда уменьшается на 1 и вы получаете 1 Очко Вдохновения на Силу воли.',
    -1,
  ),
  personalityRule(
    'resilient',
    'Неунывающий',
    'Вы получаете преимущество от характера для проверок на силу воли против отрицательных эмоций (скорбь, отчаяние и т.д.) и преимущество для проверок на преодоление стресса.',
    1,
  ),
  personalityRule(
    'alcoholic',
    'Алкоголик',
    'Вы сильно зависимы от алкоголя (вторая стадия): −1 к Телосложению и Силе воли от алкоголизма. Когда вы трезвеете, вам необходимо пройти проверку на алкоголизм (силу воли) со сложностью 2 (простому способу выпить — 3, если вам предлагают — +1). Для избавления от стадии — не более половины дня в пьяном состоянии за три месяца.',
    -1,
    {
      grants: [personalityModify('endurance', -1, 'alcoholism'), personalityModify('willpower', -1, 'alcoholism')],
    },
  ),
  personalityRule(
    'empathic',
    'Чуткий',
    'Вы получаете навыки Внимательность 1 и Проницательность 1, преимущество для проверок на проницательность. Вы получаете на 1 больше стресса от характера, если этот стресс получен от группы, которой вы сочувствуете.',
    1,
    {
      group_code: 'attentiveness',
      grants: [grantAbility('attention-skill'), grantAbility('insight-skill')],
      keywordIds: [ATTENTIVENESS_KEYWORD, INSIGHT_KEYWORD],
    },
  ),
  personalityRule(
    'pedant',
    'Педант',
    'Вы получаете навык Развитие внимания 2. Вам важно, чтобы выполнялись даже незначительные, порой формальные, требования; иначе вы можете испытать стресс.',
    1,
    {
      group_code: 'attentiveness',
      grants: [grantAbility('attention-development', 2)],
      keywordIds: [ATTENTIVENESS_KEYWORD],
    },
  ),
  personalityRule(
    'absent-minded',
    'Рассеянный',
    'Внимательность снижена на размер от особенности, пока вы не приобретёте навык Развитие внимания. Первые три уровня навыка приобретаются только за очки вдохновения; с третьим уровнем особенность исчезает. Вы не можете концентрироваться на чём-либо.',
    -1,
    {
      group_code: 'attentiveness',
      grants: [personalityModify('attention', -1, 'character')],
      keywordIds: [ATTENTIVENESS_KEYWORD],
    },
  ),
  personalityRule(
    'grudge-holder',
    'Злопамятный',
    'Вы получаете навык Развитие памяти 1 и два преимущества от характера для всех проверок на память, связанных с неприятными для вас событиями. Вы можете снять стресс, когда заслуженная кара настигает попавшего в вашу книжечку обид.',
    1,
    { grants: [grantAbility('memory-development')], keywordIds: [MEMORY_KEYWORD] },
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
