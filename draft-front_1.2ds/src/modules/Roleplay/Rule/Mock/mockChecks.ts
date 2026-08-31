import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CheckSpec } from '@/modules/Roleplay/Rule/Dto/Check/CheckSpec';
import {
  CHECK_SIMPLE_CODE,
  CHECK_HIT_CODE,
  CHECK_EXHAUSTION_CODE,
  CHECK_INITIATIVE_CODE,
  CHECK_INJURY_CODE,
  CHECK_COMMUNICATION_CODE,
  CHECK_STEALTH_CODE,
  CHECK_ACROBATICS_CODE,
  CHECK_SIMPLE_ATTACHED_RULE_CODES,
  CHECK_INJURY_ATTACHED_RULE_CODES,
} from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';

function checkRule(id: number, code: string, name: string, description: string, spec: CheckSpec): Rule {
  return {
    id,
    code,
    type: 'check',
    name,
    description,
    spaceId: 1,
    spec,
    keywordIds: [],
    mechanicId: null,
    createdAt: '2026-08-22T12:00:00Z',
  };
}

const askBoth: Pick<CheckSpec, 'difficulty_input' | 'allowed_modes'> = {
  difficulty_input: { kind: 'ask' },
  allowed_modes: 'both',
};

function characteristicCheck(idSuffix: number, characteristicCode: string, name: string): Rule {
  return checkRule(9000 + idSuffix, `check-${characteristicCode}`, `Проверка на ${name}`, `Проверка на ${name}.`, {
    type: 'check',
    parent_check_code: CHECK_SIMPLE_CODE,
    characteristic_code: characteristicCode,
    ...askBoth,
  });
}

/**
 * Каталог проверок (type check). Коды листьев общения совпадают со словарём communication-check.
 */
export const mockChecks: Rule[] = [
  checkRule(
    9001,
    CHECK_SIMPLE_CODE,
    'Простая проверка',
    'Корень обычных проверок. Правила броска (6 и 1, преимущества) наследуются потомкам.',
    {
      type: 'check',
      ...askBoth,
      attached_rule_codes: CHECK_SIMPLE_ATTACHED_RULE_CODES,
    },
  ),
  checkRule(
    9002,
    CHECK_INJURY_CODE,
    'Проверка на увечье',
    'Отдельный корень: те же правила броска, что у простой проверки; сложность и сила — процедура увечья.',
    {
      type: 'check',
      difficulty_input: { kind: 'ask' },
      allowed_modes: 'solo',
      attached_rule_codes: CHECK_INJURY_ATTACHED_RULE_CODES,
    },
  ),
  checkRule(
    9003,
    CHECK_HIT_CODE,
    'Проверка на попадание',
    'Обычно совместная. Эффективность атакующего — точность оружия. Процедура — карточки Удар / Бросок / Выстрел в ревизии.',
    {
      type: 'check',
      parent_check_code: CHECK_SIMPLE_CODE,
      ...askBoth,
    },
  ),
  checkRule(
    9004,
    CHECK_EXHAUSTION_CODE,
    'Проверка на истощение',
    'Сила воли против Истощения. Пул — характеристика, сложность — значение состояния.',
    {
      type: 'check',
      parent_check_code: CHECK_SIMPLE_CODE,
      characteristic_code: 'willpower',
      difficulty_input: { kind: 'from_state', state_code: 'exhaustion' },
      allowed_modes: 'solo',
    },
  ),
  checkRule(9005, CHECK_INITIATIVE_CODE, 'Проверка на инициативу', 'Совместная. Характеристику называет мастер.', {
    type: 'check',
    parent_check_code: CHECK_SIMPLE_CODE,
    characteristic_code: 'perception',
    allow_characteristic_override: true,
    difficulty_input: { kind: 'none' },
    allowed_modes: 'joint',
  }),
  checkRule(
    9006,
    CHECK_COMMUNICATION_CODE,
    'Проверка на общение',
    'Пул по умолчанию — Красноречие; на запуске можно подменить характеристику.',
    {
      type: 'check',
      parent_check_code: CHECK_SIMPLE_CODE,
      characteristic_code: 'communication',
      allow_characteristic_override: true,
      ...askBoth,
    },
  ),
  checkRule(9007, 'intimidation', 'Запугивание', 'Проверка на общение: запугивание.', {
    type: 'check',
    parent_check_code: CHECK_COMMUNICATION_CODE,
    ...askBoth,
  }),
  checkRule(9008, 'persuasion', 'Убеждение', 'Проверка на общение: убеждение.', {
    type: 'check',
    parent_check_code: CHECK_COMMUNICATION_CODE,
    ...askBoth,
  }),
  checkRule(9009, 'deception', 'Обман', 'Проверка на общение: обман.', {
    type: 'check',
    parent_check_code: CHECK_COMMUNICATION_CODE,
    ...askBoth,
  }),
  checkRule(9010, 'seduction', 'Обольщение', 'Проверка на общение: обольщение.', {
    type: 'check',
    parent_check_code: CHECK_COMMUNICATION_CODE,
    ...askBoth,
  }),
  checkRule(9011, 'trade', 'Торговля', 'Проверка на общение: торговля.', {
    type: 'check',
    parent_check_code: CHECK_COMMUNICATION_CODE,
    ...askBoth,
  }),
  checkRule(9012, CHECK_STEALTH_CODE, 'Проверка на скрытность', 'Проверка на скрытность.', {
    type: 'check',
    parent_check_code: CHECK_SIMPLE_CODE,
    characteristic_code: 'dexterity',
    ...askBoth,
  }),
  checkRule(9013, CHECK_ACROBATICS_CODE, 'Проверка на Акробатику', 'Проверка на Акробатику.', {
    type: 'check',
    parent_check_code: CHECK_SIMPLE_CODE,
    characteristic_code: 'dexterity',
    allow_characteristic_override: true,
    ...askBoth,
  }),
  characteristicCheck(20, 'strength', 'Силу'),
  characteristicCheck(21, 'dexterity', 'Ловкость'),
  characteristicCheck(22, 'memory', 'Память'),
  characteristicCheck(23, 'reasoning', 'Мышление'),
  characteristicCheck(24, 'intellect', 'Интеллект'),
  characteristicCheck(25, 'endurance', 'Стойкость'),
  characteristicCheck(26, 'attention', 'Внимательность'),
  characteristicCheck(27, 'reaction', 'Реакция'),
  characteristicCheck(28, 'perception', 'Восприятие'),
  characteristicCheck(29, 'magic', 'Магию'),
  characteristicCheck(30, 'willpower', 'Силу воли'),
  characteristicCheck(31, 'melee-combat', 'Мастерство ближнего боя'),
  characteristicCheck(32, 'ranged-combat', 'Мастерство дальнего боя'),
  characteristicCheck(33, 'qi-control', 'Контроль Ци'),
  characteristicCheck(34, 'medicine', 'Медицину'),
  characteristicCheck(35, 'fine-motor', 'Мелкую моторику'),
  characteristicCheck(36, 'music', 'Музицирование'),
  characteristicCheck(37, 'weight', 'Вес'),
];
