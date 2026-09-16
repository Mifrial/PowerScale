import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { importedRuleNameService } from '@/modules/Roleplay/Rule/Service/Instance/importedRuleNameService';
import { PHYSICAL_DEVELOPMENT_PARAM_COSTS } from '@/modules/Roleplay/Rule/Constant/Ability/PHYSICAL_DEVELOPMENT_PARAM_COSTS';
import { mockWeaponSkillsImport } from './mockWeaponSkillsImport';

/**
 * Импорт каталога навыков этапа «Развитие» (Фаза 6, S14).
 * Источник: docs/rule/skills/catalog.md (из docs/rule/skills/AI.html).
 * Типы по D106: Реакция/Манёвр — признаки действия; Множественный — skill + multiple + domain_ref;
 * Техника метания/Клич — навыки с признаком; «Эффект» — state; «Группа навыков» (владение оружием) — отложено.
 * Агрегаты «Развитие X» и производный «Ближний бой» — вычисляются (D108/D109).
 * Оружейные навыки из docs/rule/battle/weapon-skills.md — импортируются отдельно.
 */

const mockDevelopmentImportRaw: Rule[] = [
  {
    id: 200,
    code: 'razvitie-vospriyatiya',
    type: 'ability',
    name: 'Развитие восприятия',
    description:
      'Изучение методов развития восприятия развивает ваше восприятие.\nВы получаете +1 к Восприятию от методов развития восприятия , если владеете минимум двумя методами развития восприятия со Стоимостью 1 или больше.\nБонус увеличивается до 2, если помимо этого вы владеете минимум двумя методами развития восприятия со Стоимостью 2 или больше; до 3 - если помимо этого двумя со стоимостью 3 или больше; до 4, если помимо этого двумя со стоимостью 4 или больше; до 5, если помимо этого двумя со стоимостью 5 или больше.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [0, 0, 0, 0, 0],
        },
      },
      requirements: [],
      grants: [
        {
          level: 1,
          grants: [
            {
              type: 'characteristic_modify',
              characteristic_code: 'perception',
              amount: { type: 'ability_level', ability_code: 'razvitie-vospriyatiya', multiplier: 1, offset: 0 },
              source_code: 'development',
            },
          ],
        },
      ],
      parent_ability_code: null,
      aggregate: {
        characteristic_code: 'perception',
        method_keyword: 'method-perception',
        levels: [2, 2, 2, 2, 2],
      },
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: 1786269600,
    catalogSection: 'abilities-acquired-mental-intellect',
  },
  {
    id: 201,
    code: 'primenenie-ot-uma',
    type: 'ability',
    name: 'Применение от ума',
    description: 'Вы получаете +1 к Восприятию за каждый размер Базового Интеллекта выше среднего.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'razvitie-vospriyatiya',
    },
    keywordIds: [13, 56],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 202,
    code: 'razvitie-vnimatelnosti',
    type: 'ability',
    name: 'Тренировка внимательности',
    description: 'Вы получаете + х к Базовому Восприятию для проверок на внимательность от тренировок .',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 1, 2],
        },
      },
      requirements: [],
      grants: [
        {
          level: 1,
          grants: [
            {
              type: 'characteristic_modify',
              characteristic_code: 'attention',
              amount: { type: 'ability_level', ability_code: 'razvitie-vnimatelnosti', multiplier: 1, offset: 0 },
              source_code: 'training',
            },
          ],
        },
      ],
      parent_ability_code: null,
    },
    keywordIds: [13, 232, 49],
    mechanicId: null,
    createdAt: 1786269600,
    catalogSection: 'abilities-acquired-mental-perception',
  },
  {
    id: 203,
    code: 'razvitie-reaktsii',
    type: 'ability',
    name: 'Развитие реакции( х из 3)',
    description: '+X к Реакции от тренировок для проверок на реакцию.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 1, 2],
        },
      },
      requirements: [],
      grants: [
        {
          level: 1,
          grants: [
            {
              type: 'characteristic_modify',
              characteristic_code: 'reaction',
              amount: { type: 'ability_level', ability_code: 'razvitie-reaktsii', multiplier: 1, offset: 0 },
              source_code: 'training',
            },
          ],
        },
      ],
      parent_ability_code: null,
    },
    keywordIds: [13, 232, 53],
    mechanicId: null,
    createdAt: 1786269600,
    catalogSection: 'abilities-acquired-mental-perception',
  },
  {
    id: 204,
    code: 'orientirovanie',
    type: 'ability',
    name: 'Ориентирование',
    description:
      '+3 к Внимательности от мастерства для проверок ориентирования на знакомом типе местности или при наличии известных ориентирова. В противном случае вы просто получаете преимущество от мастерства для такой проверки.\nСложность проверки на ориентирование устанавливается ведущим. В недалеко от родного леса со знакомыми тропками - 1. В неизвестных равнинах, когда известно, что нужно идти на север, держа заметный горный пик по правую сторону - сложность будет равна 2. Путешествие же в густом холмистом лесу с ориентацией лишь по солнцу будет иметь сложность 4↑',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            { type: 'has_ability', ability_code: 'schet', min_level: 1 },
            { type: 'has_ability', ability_code: 'pismennost', min_level: 1 },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 56],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Бонусы от мастерства и обстоятельств для ориентирования пока не исполняются Game.',
  },
  {
    id: 205,
    code: 'chtenie-sledov',
    type: 'ability',
    name: 'Чтение следов',
    description:
      'Вы обучены подмечать следы и извлекать из них полезную информацию. В любом знакомом вам типе местности вы получаете +3 к Внимательности от мастерства для проверок на чтение следов. Для того, чтобы тип местности стал вам знакомым, необходимо прожить в его условиях год, либо потратить неделю на адаптацию навыка к применению в новом типе местности.\nСложность проверки зависит от того, как хорошо сохранились следы, и на сколько хорошо вы знаете местность. Чем больше результат проверки, тем больше информации вы сможете получить. Вы, конечно, не можете получить ту информацию, которой эти самые следы не несут от их изучения.\nКак пример, можно получить информацию о проходящем 3-5 часов назад волке в обычном лесу. Чтобы определить направление движения животного, нужно пройти проверку на Восприятие со сложностью 3, со сложностью 3↑ чтобы определить примерный вес и размер животного а также то, что оно прихрамывало на левую ногу. Персонаж знает о волках - поэтому определить, что следы принадлежат ему тоже имеет сложность 2↑. Так как лес - знакомая вам местность, вы можете определить примерное время, когда этот волк пробегал со сложностью 2↑.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 56],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Бонус от мастерства для чтения следов пока не исполняется Game.',
  },
  {
    id: 206,
    code: 'orientirovanie-po-sledam',
    type: 'ability',
    name: 'Ориентирование по следам',
    description:
      'В населённой животными местностями вы получаете преимущество для проверок на ориентирование на местности от обстоятельств .',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'orientirovanie',
    },
    keywordIds: [13, 56],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Преимущество от обстоятельств для ориентирования по следам пока не исполняется Game.',
  },
  {
    id: 207,
    code: 'poisk-i-sokrytie-ulik',
    type: 'ability',
    name: 'Поиск и сокрытие улик',
    description:
      'Вы знаете, что может послужить уликой и обучены их поиску. Вы получаете +3 к Внимательности от мастерства при поиске улик.\nВместе с тем, вы знаете, как не оставлять за собой следы, что увеличивает сложность поиска неосознанно оставленных вами улик на размер.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 56],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Бонусы поиска и сокрытия улик пока не исполняются Game.',
  },
  {
    id: 208,
    code: 'sopostavlenie-ulik',
    type: 'ability',
    name: 'Сопоставление улик',
    description:
      '+1 к Интеллекту от обстоятельств за каждый размер Внимательности выше среднего при проверках на проницательность при анализе улик на месте.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'poisk-i-sokrytie-ulik',
    },
    keywordIds: [13, 56],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Бонус от обстоятельств при сопоставлении улик пока не исполняется Game.',
  },
  {
    id: 209,
    code: 'rabota-s-lovushkami',
    type: 'ability',
    name: 'Работа с ловушками',
    description:
      'Вы обучены устанавливать ловушки и получаете +3 к проверкам на установку и сокрытие ловушек.\nВы знаете, где можно спрятать ловушки и обучены их поиску. В любом знакомом вам типе местности вы получаете +3 к Внимательности от мастерства для любых проверок против ловушек. Для того, чтобы тип местности стал вам знакомым, необходимо прожить в его условиях год, либо потратить неделю на адаптацию навыка к применению в новом типе местности.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 56],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Бонусы установки, сокрытия и поиска ловушек пока не исполняются Game.',
  },
  {
    id: 210,
    code: 'postoyannaya-bditelnost',
    type: 'ability',
    name: 'Постоянная бдительность',
    description: 'Вы получаете преимущество от состояния для всех проверок на Реакцию против ловушек.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'rabota-s-lovushkami',
    },
    keywordIds: [13, 56],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Преимущество от состояния для реакции против ловушек пока не исполняется Game.',
  },
  {
    id: 211,
    code: 'poisk-trav',
    type: 'ability',
    name: 'Поиск трав',
    description: 'Вы обучены поиску и сбору трав в регионе, растения которого знаете. Бонуса +3 к Внимательности нет.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 56],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Сбор трав читает знание растений региона; проверка пока не исполняется Game.',
  },
  {
    id: 212,
    code: 'obnaruzhenie-dobavok',
    type: 'ability',
    name: 'Обнаружение добавок',
    description:
      'Вы обучены обнаруживать добавки в еду и напитки трав и зелий, что даёт +3 к Внимательности от мастерства для проверок на обнаружение. Однако, для того, чтобы распознать траву или зелье, вам по прежнему необходимы соответствующие знания. Например - Знания о растениях .',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 56],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Бонус обнаружения добавок пока не исполняется Game; зависимость от знаний остаётся текстовой.',
  },
  {
    id: 213,
    code: 'razvitie-intellekta',
    type: 'ability',
    name: 'Развитие интеллекта',
    description:
      'Изучение методов развития интеллекта развивает ваш интеллект.\nВы получаете +1 к Интеллекту от методов развития интеллекта , если владеете минимум двумя методами развития интеллекта со Стоимостью 1 или больше.\nБонус увеличивается до 2, если помимо этого вы владеете минимум двумя методами развития интеллекта со Стоимостью 2 или больше; до 3 - если помимо этого двумя со стоимостью 3 или больше; до 4, если помимо этого двумя со стоимостью 4 или больше; до 5, если помимо этого двумя со стоимостью 5 или больше.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [0, 0, 0, 0, 0],
        },
      },
      requirements: [],
      grants: [
        {
          level: 1,
          grants: [
            {
              type: 'characteristic_modify',
              characteristic_code: 'intellect',
              amount: { type: 'ability_level', ability_code: 'razvitie-intellekta', multiplier: 1, offset: 0 },
              source_code: 'development',
            },
          ],
        },
      ],
      parent_ability_code: null,
      aggregate: {
        characteristic_code: 'intellect',
        method_keyword: 'method-intellect',
        levels: [2, 2, 2, 2, 2],
      },
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: 1786269600,
    catalogSection: 'abilities-acquired-mental-intellect',
  },
  {
    id: 214,
    code: 'razvitie-pamyati',
    type: 'ability',
    name: 'Развитие памяти( х из 3)',
    description: '+X к Памяти от тренировок для проверок памяти.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 1, 2],
        },
      },
      requirements: [],
      grants: [
        {
          level: 1,
          grants: [
            {
              type: 'characteristic_modify',
              characteristic_code: 'memory',
              amount: { type: 'ability_level', ability_code: 'razvitie-pamyati', multiplier: 1, offset: 0 },
              source_code: 'training',
            },
          ],
        },
      ],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 215,
    code: 'razvitie-myshleniya',
    type: 'ability',
    name: 'Развитие мышления( х из 3)',
    description: '+X к Мышлению от тренировок для проверок мышления.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 1, 2],
        },
      },
      requirements: [],
      grants: [
        {
          level: 1,
          grants: [
            {
              type: 'characteristic_modify',
              characteristic_code: 'reasoning',
              amount: { type: 'ability_level', ability_code: 'razvitie-myshleniya', multiplier: 1, offset: 0 },
              source_code: 'training',
            },
          ],
        },
      ],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 216,
    code: 'neytralizatsiya-pomekh',
    type: 'ability',
    name: 'Нейтрализация помех',
    description:
      'За каждый размер Интеллекта выше среднего вы можете после совершения броска сами убрать один кубик помехи, если только это не 6.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'characteristic_value',
              characteristic_code: 'intellect',
              min: {
                base: 4,
                size: 1,
              },
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Удаление кубиков помех после броска пока не исполняется Game.',
  },
  {
    id: 217,
    code: 'neytralizatsiya-pomekh-2',
    type: 'ability',
    name: 'Усиленная нейтрализация помех',
    description:
      'Каждый размер интеллекта выше среднего позволяет получить вам получить в два раза больше - превратить до двух кубиков преимущества в обычные, или, если у вас есть навык Нейтрализация помех , убрать два кубика помехи (если это только не 6) .',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [5],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'characteristic_value',
              characteristic_code: 'intellect',
              min: {
                base: 5,
                size: 1,
              },
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Механика удаления кубиков помех пока не исполняется Game. Карточку следует отличать от базовой Нейтрализации помех.',
  },
  {
    id: 218,
    code: 'kontsentratsiya',
    type: 'ability',
    name: 'Концентрация',
    description:
      'Требуется Интеллект 5 или Восприятие 5. Пока ни одно из требований не выполняется, навык выключен: жетонов нет.\nВы получаете ресурс «Жетоны концентрации». Базовый максимум — 1. Каждый размер Интеллекта и каждый размер Восприятия выше среднего увеличивают максимум на 1.\nПеред проверкой на попадание, Восприятие, Внимательность, Реакцию, Интеллект, Память, Мышление или Красноречие (включая наследников этих проверок и проверки, которые фактически используют эти характеристики) вы можете потратить жетон, чтобы получить преимущество для этого броска.\nСконцентрироваться можно только на действии не длиннее одного хода.\nЕсли с конца предыдущего своего хода до конца текущего вы не тратили жетоны, в конце хода запас восстанавливается до максимума.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'or',
              children: [
                {
                  type: 'characteristic_value',
                  characteristic_code: 'intellect',
                  min: { base: 5, size: 0 },
                },
                {
                  type: 'characteristic_value',
                  characteristic_code: 'perception',
                  min: { base: 5, size: 0 },
                },
              ],
            },
          ],
        },
      ],
      grants: [
        {
          level: 1,
          grants: [
            { type: 'resource', resource_code: 'concentration', limit: 1 },
            {
              type: 'resource_limit_change',
              resource_code: 'concentration',
              amount: { type: 'characteristic_size_positive', characteristic_code: 'intellect' },
              source_code: 'intellect',
            },
            {
              type: 'resource_limit_change',
              resource_code: 'concentration',
              amount: { type: 'characteristic_size_positive', characteristic_code: 'perception' },
              source_code: 'perception',
            },
          ],
        },
      ],
      parent_ability_code: null,
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 219,
    code: 'predelnaya-kontsentratsiya',
    type: 'ability',
    name: 'Предельная концентрация (x из 2)',
    description:
      'Требуется Интеллект 5↑ или Восприятие 5↑; второй уровень — Интеллект 5↑↑ или Восприятие 5↑↑.\nВы можете потратить сразу несколько жетонов концентрации на одну проверку. Вы получаете столько преимуществ, сколько жетонов потратили, вплоть до [уровень + 1].',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2, 2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'or',
              children: [
                {
                  type: 'characteristic_value',
                  characteristic_code: 'intellect',
                  min: { base: 5, size: 1 },
                },
                {
                  type: 'characteristic_value',
                  characteristic_code: 'perception',
                  min: { base: 5, size: 1 },
                },
              ],
            },
          ],
        },
        {
          level: 2,
          requirements: [
            {
              type: 'or',
              children: [
                {
                  type: 'characteristic_value',
                  characteristic_code: 'intellect',
                  min: { base: 5, size: 2 },
                },
                {
                  type: 'characteristic_value',
                  characteristic_code: 'perception',
                  min: { base: 5, size: 2 },
                },
              ],
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: 'kontsentratsiya',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 222,
    code: 'sosredotochenie-voli',
    type: 'ability',
    name: 'Сосредоточение воли',
    description:
      'Требуется Сила воли 5.\nВы можете тратить жетоны концентрации на проверки, в которых бросается Сила воли, включая проверку на истощение и потерю сознания.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'characteristic_value',
              characteristic_code: 'willpower',
              min: { base: 5, size: 0 },
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: 'kontsentratsiya',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 225,
    code: 'dlitelnoe-napryazhenie',
    type: 'ability',
    name: 'Длительное напряжение',
    description:
      'Требуется Интеллект 4↑ или Восприятие 4↑.\nВы можете концентрироваться на действии длительностью до 10 ходов. Без этого навыка концентрация возможна только на действии не длиннее одного хода.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [3],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'or',
              children: [
                {
                  type: 'characteristic_value',
                  characteristic_code: 'intellect',
                  min: { base: 4, size: 1 },
                },
                {
                  type: 'characteristic_value',
                  characteristic_code: 'perception',
                  min: { base: 4, size: 1 },
                },
              ],
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: 'kontsentratsiya',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 226,
    code: 'vladenie-yazykom',
    type: 'ability',
    name: 'Владение языком( Язык , х из 3)',
    description: 'Множественный навык: владение выбранным языком на трёх уровнях.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2, 2, 2],
        },
      },
      requirements: [
        {
          level: 3,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'gramotnost',
              min_level: 1,
            },
            {
              type: 'has_ability',
              ability_code: 'pismennost',
              min_level: 1,
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
      multiple: true,
      domain_ref: 'language',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Нужен автоэкземпляр родного языка 2 из 3. Уровень 3: грамотность того же языка и письменность из script_codes языка, не любая.',
  },
  {
    id: 227,
    code: 'pravilnoe-proiznoshenie',
    type: 'ability',
    name: 'Правильное произношение',
    description: 'Улучшение Владения языком для выбранного языка.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'vladenie-yazykom',
      multiple: true,
      domain_ref: 'language',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Домен улучшения должен совпадать с экземпляром языка; корректный выбор домена пока не поддержан.',
  },
  {
    id: 228,
    code: 'pismennost',
    type: 'ability',
    name: 'Письменность',
    description:
      'Навык знаков выбранной письменности, не языка. Алфавит — один уровень 1 ОР; иероглифы — 1/1/1. Речь для покупки не нужна.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      multiple: true,
      domain_ref: 'script',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Лестница с kind карты script. Читать язык = речь + эта письменность в script_codes. Книжный червь — отдельный грант.',
  },
  {
    id: 229,
    code: 'chtenie-po-gubam',
    type: 'ability',
    name: 'Чтение по губам( Вид )',
    description: 'Улучшение Владения языком для чтения по губам на выбранном языке.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'vladenie-yazykom',
              min_level: 1,
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: 'vladenie-yazykom',
      multiple: true,
      domain_ref: 'language',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Домен языка и runtime чтения по губам пока не поддержаны Game.',
  },
  {
    id: 230,
    code: 'gramotnost',
    type: 'ability',
    name: 'Грамотность',
    description: 'Улучшение Владения языком: грамотная речь и письмо на выбранном языке.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'vladenie-yazykom',
              min_level: 2,
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: 'vladenie-yazykom',
      multiple: true,
      domain_ref: 'language',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Домен — тот же язык, что у купленной речи. Нужна письменность из script_codes этого языка, не любой алфавит. Книжный червь грамотность не выдаёт.',
  },
  {
    id: 231,
    code: 'etiket',
    type: 'ability',
    name: 'Этикет( Культура )',
    description: 'Навык Этикета для выбранной культуры.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      multiple: true,
      domain_ref: 'culture',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Словарь доменов культур и runtime Этикета пока не подтверждены.',
  },
  {
    id: 232,
    code: 'manernost',
    type: 'ability',
    name: 'Манерность',
    description:
      'Вести себя так, как подобает благородному человеку для вас - естественно. Об этом говорит множество мелочей - осанка, манера речи и т.д. Этот навык может быть только одним в этом множественном навыком - при изучении он заменяет все остальные.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'etiket',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Домен выбранной культуры и ограничение «только одно улучшение» пока не выражены в spec/runtime.',
  },
  {
    id: 233,
    code: 'schet',
    type: 'ability',
    name: 'Счёт',
    description: 'Навык выполнения обычных вычислений и операций счёта.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 234,
    code: 'matematika',
    type: 'ability',
    name: 'Математика',
    description:
      'Вы можете письменно выполнять операции сложения, вычитания, деления и умножения любой сложности, выполнив проверку на Интеллект со сложностью 2. Операция сложения или вычитания с числами до 1000 требует 2 хода; до 10000 требует 3 хода и т.д. Умножение или деление требуют в два раза больше времени.\nПомимо этого вы знаете ряд математических правил и формул и умеете ими пользоваться. Сложность тех или иных выражений определяется мастером, как и время их решения.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            { type: 'has_ability', ability_code: 'schet', min_level: 1 },
            { type: 'has_ability', ability_code: 'pismennost', min_level: 1 },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 235,
    code: 'estestvoznanie',
    type: 'ability',
    name: 'Естествознание',
    description: 'Базовые естественно-научные знания о мире.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 236,
    code: 'fizika',
    type: 'ability',
    name: 'Физика',
    description: 'Знание физических закономерностей и умение применять их на практике.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [3],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'estestvoznanie',
              min_level: 1,
            },
            {
              type: 'has_ability',
              ability_code: 'matematika',
              min_level: 1,
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 237,
    code: 'torgovlya',
    type: 'ability',
    name: 'Торговля( регион )',
    description: 'Множественный навык торговли для выбранного региона.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [{ type: 'has_ability', ability_code: 'schet', min_level: 1 }],
        },
      ],
      grants: [],
      parent_ability_code: null,
      multiple: true,
      domain_ref: 'region',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 1199,
    code: 'znanie',
    type: 'ability',
    name: 'Знание о…',
    description:
      'Множественный навык знания в выбранной сфере. Уровни осведомлён / эксперт / мастер. Не лечит, не варит и не оперирует: вспоминает факты. Проверка — Интеллект против сложности факта; нехватка уровня повышает сложность на столько размеров.',
    catalogSection: 'abilities-acquired-mental-intellect',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 2, 2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      multiple: true,
    },
    keywordIds: [13, 57, 67],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Запуск — соло check-knowledge: полоса, слоты, нехватка размера DC.',
  },
  {
    id: 238,
    code: 'znanie-zakonov',
    type: 'ability',
    name: 'Знание законов',
    description: 'Шаблон знания: тип «Законы», слот региона. На лист покупается как экземпляр «Знание о…».',
    catalogSection: 'abilities-acquired-mental-intellect',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 2, 2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      knowledge_template_field: 'laws',
    },
    keywordIds: [13, 57, 67],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Шаблон `znanie` / laws. Запуск — check-knowledge.',
  },
  {
    id: 239,
    code: 'zaschita-ot-zakona',
    type: 'ability',
    name: 'Защита от закона( x из 3)',
    description: 'Вы получаете + х ко всем проверкам на знание законов для известных вам субъектов .',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 2, 3],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'znanie',
      parent_knowledge_field: 'laws',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Ситуационный бонус к check-knowledge законов известного региона (уровень навыка).',
  },
  {
    id: 240,
    code: 'znanie-o-zhivotnykh',
    type: 'ability',
    name: 'Знание о животных',
    description: 'Шаблон знания: тип «Животные», слот региона.',
    catalogSection: 'abilities-acquired-mental-intellect',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 2, 2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      knowledge_template_field: 'animals',
    },
    keywordIds: [13, 57, 67],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Шаблон `znanie`. Запуск — check-knowledge.',
  },
  {
    id: 241,
    code: 'znanie-o-rasteniyakh',
    type: 'ability',
    name: 'Знание о растениях',
    description: 'Шаблон знания: тип «Растения», слот региона.',
    catalogSection: 'abilities-acquired-mental-intellect',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 2, 2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      knowledge_template_field: 'plants',
    },
    keywordIds: [13, 57, 67],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Шаблон `znanie`. Запуск — check-knowledge.',
  },
  {
    id: 242,
    code: 'znanie-o-istorii',
    type: 'ability',
    name: 'Знание о истории',
    description: 'Шаблон знания: тип «История», слот региона.',
    catalogSection: 'abilities-acquired-mental-intellect',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 2, 2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      knowledge_template_field: 'history',
    },
    keywordIds: [13, 57, 67],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Шаблон `znanie`. Запуск — check-knowledge.',
  },
  {
    id: 243,
    code: 'shifr',
    type: 'ability',
    name: 'Шифр',
    description: 'Навык создания и использования шифров.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'pismennost',
              min_level: 1,
            },
            {
              type: 'has_ability',
              ability_code: 'schet',
              min_level: 1,
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 244,
    code: 'slozhnyy-shifr',
    type: 'ability',
    name: 'Сложный шифр',
    description:
      'Вы узнаёте конкретный способ сложного шифра. Сложность его записи и расшифровки на размер выше обычной. Запись и расшифровка занимают в десять раз больше времени.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'shifr',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 246,
    code: 'perevyazat',
    type: 'ability',
    name: 'Перевязать',
    description:
      'Автоматическое действие: +2 к вкладу перевязки одной внешней раны. База 8 ОД. Первая помощь снижает до 4 ОД и снимает потолок +4 на этой ране; спорая после первой перевязки цели — 2 ОД. Вид не нужен.',
    catalogSection: 'abilities-acquired-medicine',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: { or: { kind: 'automatic' } },
      requirements: [],
      grants: [],
      action_components: [{ type: 'resource', resource_code: 'action-points', amount: 8, label: 'Действие' }],
      parent_ability_code: null,
    },
    keywordIds: [14, 59],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 245,
    code: 'pervaya-pomosch',
    type: 'ability',
    name: 'Первая помощь',
    description:
      'Модифицирует перевязку: 4 ОД за +2 закрытости вместо 8 и снимает потолок +4 с перевязки на этой ране. Вид не нужен.',
    catalogSection: 'abilities-acquired-medicine',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 59],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 247,
    code: 'sporaya-perevyazka',
    type: 'ability',
    name: 'Спорая перевязка',
    description:
      'После первой перевязки этого персонажа следующие перевязки любых его ран стоят 2 ОД. Без первой помощи не взять.',
    catalogSection: 'abilities-acquired-medicine',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'pervaya-pomosch',
    },
    keywordIds: [13, 59],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Сутки −2 силы раны в бою пока не тикают.',
  },
  {
    id: 918,
    code: 'zazhat',
    type: 'ability',
    name: 'Зажать',
    description:
      'Автоматическое действие: зажать 1 или 2 внешние раны одной цели. 2 ОД за рану. Пока держат, тик крови этой раны не ниже чем сила − 1. Отпустить — 0 ОД, с карточки раны.',
    catalogSection: 'abilities-acquired-other',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: { or: { kind: 'automatic' } },
      requirements: [],
      grants: [],
      action_components: [{ type: 'resource', resource_code: 'action-points', amount: 2, label: 'Действие' }],
      parent_ability_code: null,
    },
    keywordIds: [14],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 248,
    code: 'ukhod',
    type: 'ability',
    name: 'Уход',
    description:
      'На длинном отдыхе даёт ещё −1 истощения цели, чью физиологию вы знаете. Именованный курс — знание болезней или назначение, не отдельный навык.',
    catalogSection: 'abilities-acquired-medicine',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 59],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 249,
    code: 'znanie-bolezney',
    type: 'ability',
    name: 'Знание болезней',
    description:
      'Шаблон знания: тип «Болезни», слот вида. Опознание и название лечения; не варка и не курс. База сложности 2 / 4 / 8. Уровни знания не поднимают Интеллект.',
    catalogSection: 'abilities-acquired-mental-intellect',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 2, 2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      knowledge_template_field: 'diseases',
    },
    keywordIds: [13, 57, 59, 67],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Шаблон `znanie` / diseases. Запуск — check-knowledge; посадка в тело — физиология цели.',
  },
  {
    id: 250,
    code: 'fiziologiya',
    type: 'ability',
    name: 'Физиология',
    description: 'Шаблон знания: тип «Физиология», слот вида. Лестница 1/2/2. Не лечит.',
    catalogSection: 'abilities-acquired-mental-intellect',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 2, 2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      knowledge_template_field: 'physiology',
    },
    keywordIds: [13, 57, 59, 67],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 251,
    code: 'farmatsiya',
    type: 'ability',
    name: 'Фармация',
    description:
      'Приготовление средств. Для вида пациента нужна физиология (или близкий вид), для травы — знание растений региона. Именной недуг — знание болезней или назначение.',
    catalogSection: 'abilities-acquired-medicine',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 2, 4],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 59],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Варка и требования знаний цели/региона пока не исполняются Game.',
  },
  {
    id: 252,
    code: 'khirurgiya',
    type: 'ability',
    name: 'Хирургия',
    description:
      'Операции. Проверка: мелкая моторика + уровень хирургии + физиология цели против сложности операции. Первую помощь на покупку не требует. Успех делает внутреннюю рану обычной; силу не снимает.',
    catalogSection: 'abilities-acquired-medicine',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 2, 3],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 57, 59],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Операции и пул проверки пока не исполняются Game.',
  },
  {
    id: 253,
    code: 'smertonosnye-udary',
    type: 'ability',
    name: 'Смертоносные удары',
    description:
      'Когда вы наносите кому-либо, относящемуся к известному вам виду увечье, вы можете использовать этот навык, чтобы дать ему помеху или преимущество на совершение проверки на получение увечья.',
    catalogSection: 'abilities-acquired-medicine',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'khirurgiya',
      strike_upgrade: {
        exclusive_group: 'smertonosnye-udary',
        requires_physiology: true,
        modes: [
          { code: 'cripple', label: 'Калечить', injury_check_advantage: 1 },
          { code: 'spare', label: 'Щадить', injury_check_advantage: -1 },
        ],
      },
    },
    keywordIds: [13, 59],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Ручное увечье и кровь без удара это поле не читают.',
  },
  {
    id: 254,
    code: 'pitanie',
    type: 'ability',
    name: 'Питание',
    description:
      'Программы питания для вида, чью физиологию знаете (близкие виды — общий тип тела вроде гуманоида, уровень − 1).',
    catalogSection: 'abilities-acquired-medicine',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 57, 59],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Программы питания и их влияние на еду, здоровье и стоимость Физического развития пока не исполняются Game.',
  },
  {
    id: 255,
    code: 'trenirovka-voli',
    type: 'ability',
    name: 'Тренировка воли',
    description: 'Вы получаете +уровень навыка к Силе воли от тренировок.',
    catalogSection: 'abilities-acquired-mental-will',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2, 2, 3, 3, 3, 4],
        },
      },
      requirements: [],
      grants: [
        {
          level: 1,
          grants: [
            {
              type: 'characteristic_modify',
              characteristic_code: 'willpower',
              amount: { type: 'ability_level', ability_code: 'trenirovka-voli', multiplier: 1, offset: 0 },
              source_code: 'training',
            },
          ],
        },
      ],
      parent_ability_code: null,
    },
    keywordIds: [13, 60],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 256,
    code: 'podavlenie-somneniy',
    type: 'ability',
    name: 'Подавление сомнений',
    description: 'При броске на силу воли вы можете убрать одну выпавшую 6 до применения каких-либо эффектов.',
    catalogSection: 'abilities-acquired-mental-will',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'trenirovka-voli',
    },
    keywordIds: [13, 60],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Удаление выпавшей шестёрки при проверке Воли пока не исполняется Game.',
  },
  {
    id: 257,
    code: 'nesgibaemyy-razum',
    type: 'ability',
    name: 'Несгибаемый разум',
    description: 'Вы получаете сопротивление психическому урону от разума в размере Силы воли.',
    catalogSection: 'abilities-acquired-mental-will',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'trenirovka-voli',
    },
    keywordIds: [13, 60],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Сопротивление психическому урону пока не исполняется Game.',
  },
  {
    id: 258,
    code: 'otkhodchivost',
    type: 'ability',
    name: 'Отходчивость',
    description: '+1 к эффективности проверок на восстановление воли.',
    catalogSection: 'abilities-acquired-mental-will',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'trenirovka-voli',
    },
    keywordIds: [13, 60],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Модификатор эффективности восстановления Воли пока не исполняется Game.',
  },
  {
    id: 259,
    code: 'adaptatsiya',
    type: 'ability',
    name: 'Адаптация',
    description: 'Вы бросаете на 1 кубик больше для проверки на восстановление силы воли.',
    catalogSection: 'abilities-acquired-mental-will',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'trenirovka-voli',
    },
    keywordIds: [13, 60],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Дополнительный кубик на восстановление Воли пока не исполняется Game.',
  },
  {
    id: 261,
    code: 'fizicheskoe-razvitie',
    type: 'ability',
    name: 'Физическое развитие',
    description:
      'Распределите до 9 пунктов между Силой, Стойкостью и Ловкостью, не более 6 на одну характеристику. Каждый пункт даёт +1 к выбранной характеристике от тренировок. Стоимость пунктов внутри характеристики: 1–2 по 2 ОР, 3–4 по 3 ОР, 5–6 по 4 ОР. Каждый второй пункт Силы даёт +1 к Весу от тренировок.',
    catalogSection: 'abilities-acquired-physical',
    spaceId: 1,
    spec: {
      type: 'trait',
      zones: {
        or: {
          kind: 'parameter_sum_tables',
          max_level: 9,
          tables: {
            strength: PHYSICAL_DEVELOPMENT_PARAM_COSTS,
            endurance: PHYSICAL_DEVELOPMENT_PARAM_COSTS,
            dexterity: PHYSICAL_DEVELOPMENT_PARAM_COSTS,
          },
        },
      },
      parameters: [
        {
          code: 'strength',
          label: 'Сила',
          description: 'от тренировки. Каждые 2 пункта дают +1 к Весу от тренировки.',
          resolution: 'purchase',
          default: { base: 0, size: 0 },
          min: { base: 0, size: 0 },
          max: { base: 6, size: 0 },
        },
        {
          code: 'endurance',
          label: 'Стойкость',
          description: 'от тренировки.',
          resolution: 'purchase',
          default: { base: 0, size: 0 },
          min: { base: 0, size: 0 },
          max: { base: 6, size: 0 },
        },
        {
          code: 'dexterity',
          label: 'Ловкость',
          description: 'от тренировки.',
          resolution: 'purchase',
          default: { base: 0, size: 0 },
          min: { base: 0, size: 0 },
          max: { base: 6, size: 0 },
        },
      ],
      requirements: [],
      grants: [
        {
          level: 1,
          grants: [
            {
              type: 'characteristic_modify',
              characteristic_code: 'strength',
              amount: { type: 'parameter', parameter_code: 'strength', per_unit: 1 },
              source_code: 'training',
            },
            {
              type: 'characteristic_modify',
              characteristic_code: 'endurance',
              amount: { type: 'parameter', parameter_code: 'endurance', per_unit: 1 },
              source_code: 'training',
            },
            {
              type: 'characteristic_modify',
              characteristic_code: 'dexterity',
              amount: { type: 'parameter', parameter_code: 'dexterity', per_unit: 1 },
              source_code: 'training',
            },
            {
              type: 'characteristic_modify',
              characteristic_code: 'weight',
              amount: { type: 'parameter_floor_div', parameter_code: 'strength', divisor: 2 },
              source_code: 'training',
            },
          ],
        },
      ],
      parent_ability_code: null,
    },
    keywordIds: [11, 61],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 262,
    code: 'trenirovka-skorosti',
    type: 'ability',
    name: 'Тренировка скорости',
    description: '+1 к лимиту ОД от тренировок.',
    catalogSection: 'abilities-acquired-physical',
    spaceId: 1,
    spec: {
      type: 'trait',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [3],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'fizicheskoe-razvitie',
              min_level: 1,
            },
          ],
        },
      ],
      grants: [
        {
          level: 1,
          grants: [
            {
              type: 'resource_limit_change',
              resource_code: 'action-points',
              amount: { type: 'fixed', value: 1 },
              source_code: 'training',
            },
          ],
        },
      ],
      parent_ability_code: null,
    },
    keywordIds: [11, 61],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 263,
    code: 'manevrennost',
    type: 'ability',
    name: 'Манёвренность( х из 2)',
    description: '',
    catalogSection: 'abilities-acquired-physical',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2, 2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'characteristic_value',
              characteristic_code: 'perception',
              min: {
                base: 5,
                size: 0,
              },
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 61],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Текст эффекта Манёвренности отсутствует в выгрузке; требование Восприятия 5 сохранено.',
  },
  {
    id: 264,
    code: 'begun',
    type: 'ability',
    name: 'Бегун',
    description: 'Каждая часть бега позволяет вам переместиться на 1 шаг больше.',
    catalogSection: 'abilities-acquired-physical',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 61],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Требование Силы и Стойкости не ниже Веса персонажа пока не выражено в spec; эффект Бега уже описан текстом.',
  },
  {
    id: 265,
    code: 'skrytnost',
    type: 'ability',
    name: 'Скрытность( х из 3)',
    description: 'Вы получаете +[2 × X] к проверке на Скрытность от тренировки.',
    catalogSection: 'abilities-acquired-physical',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'progression',
          max_level: 3,
          base_cost: 1,
          step: 1,
        },
      },
      requirements: [],
      grants: [
        {
          level: 1,
          grants: [
            {
              type: 'check_advantage',
              amount: 2,
              check_codes: ['stealth'],
              source_code: 'training',
            },
          ],
        },
        {
          level: 2,
          grants: [
            {
              type: 'check_advantage',
              amount: 2,
              check_codes: ['stealth'],
              source_code: 'training',
            },
          ],
        },
        {
          level: 3,
          grants: [
            {
              type: 'check_advantage',
              amount: 2,
              check_codes: ['stealth'],
              source_code: 'training',
            },
          ],
        },
      ],
      parent_ability_code: null,
    },
    keywordIds: [13, 61],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 266,
    code: 'ottochennyy-navyk',
    type: 'ability',
    name: 'Отточенный навык',
    description: '',
    catalogSection: 'abilities-acquired-physical',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [4],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'skrytnost',
    },
    keywordIds: [13, 61],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Описание эффекта улучшения отсутствует в выгрузке; механику не выдумываем.',
  },
  {
    id: 267,
    code: 'akrobatika',
    type: 'ability',
    name: 'Акробатика( х из 3)',
    description:
      '<p>Вы получаете + х к <a data-rule-code="dexterity">проворству</a> от <a data-rule-code="mastery">мастерства</a> для проверок на <a data-rule-code="acrobatics">Акробатику</a>.</p><p>Проверки на <a data-rule-code="acrobatics">Акробатику</a> могут основываться на <a data-rule-code="reaction">Реакции</a>, <a data-rule-code="strength">Силе</a> или <a data-rule-code="dexterity">Ловкости</a>. Если для проверки важны несколько характеристик - для проверки выбирается меньшая.</p><p><span class="description-example">Пример: Прыжок от стены. Это проверка на <a data-rule-code="acrobatics">Акробатику</a> по <a data-rule-code="strength">Силе</a> со сложностью 3 для первого прыжка. Для второго прыжка сложность возрастает до 3↑. Сложность последующих прыжков равна 3↑↑</span></p>',
    catalogSection: 'abilities-acquired-physical',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 1, 1, 2, 2, 3],
        },
      },
      requirements: [],
      grants: [
        {
          level: 1,
          grants: [
            {
              type: 'characteristic_modify',
              characteristic_code: 'dexterity',
              amount: { type: 'ability_level', ability_code: 'akrobatika', multiplier: 1 },
              source_code: 'mastery',
              check_codes: ['acrobatics'],
            },
          ],
        },
      ],
      parent_ability_code: null,
    },
    keywordIds: [13, 61],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 268,
    code: 'ottochennyy-navyk-2',
    type: 'ability',
    name: 'Отточенный навык',
    description: '',
    catalogSection: 'abilities-acquired-physical',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [4],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'akrobatika',
    },
    keywordIds: [13, 61],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Описание эффекта улучшения отсутствует в выгрузке; механику не выдумываем.',
  },
  {
    id: 269,
    code: 'boevaya-akrobatika',
    type: 'ability',
    name: 'Боевая акробатика',
    description:
      '+[ х / 2] к Ловкости от техники для реакции на атаку по вам Избежать, если ваша Сила превышает минимальную каждого используемого оружия минимум на размер. Если вы не используете при защите ни оружие, ни щит, то вашим оружием считаются ваши руки.',
    catalogSection: 'abilities-acquired-physical',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'akrobatika',
    },
    keywordIds: [13, 61],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Бонус к реакции «Избежать» и ситуативное требование по Силе пока не исполняются Game.',
  },
  {
    id: 270,
    code: 'smertonosnye-tryuki',
    type: 'ability',
    name: 'Смертоносные трюки',
    description:
      'Бонус от улучшения «Боевая акробатика» распространяется на все совместные проверки попадания ударом, а не только на реакцию «Избежать», в том числе на ваши удары.',
    catalogSection: 'abilities-acquired-physical',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'boevaya-akrobatika',
    },
    keywordIds: [13, 61],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Расширение области действия бонуса Боевой акробатики пока не исполняется Game.',
  },
  {
    id: 271,
    code: 'smertonosnye-tryuki-2',
    type: 'ability',
    name: 'Смертоносные трюки',
    description: 'Если у вас есть минимум две свободные руки, бонус от навыка «Боевая акробатика» увеличен вдвое.',
    catalogSection: 'abilities-acquired-physical',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [4],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'boevaya-akrobatika',
    },
    keywordIds: [13, 61],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Удвоение бонуса Боевой акробатики при наличии двух свободных рук пока не исполняется Game.',
  },
  {
    id: 272,
    code: 'polnoe-otstuplenie',
    type: 'ability',
    name: 'Полное отступление',
    description: 'Вы можете совершать отступление и падение одновременно.',
    catalogSection: 'abilities-acquired-physical',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            { type: 'has_ability', ability_code: 'boevaya-akrobatika', min_level: 1 },
            { type: 'has_ability', ability_code: 'bezoruzhnyy-boy', min_level: 1 },
          ],
        },
      ],
      grants: [],
      parent_ability_code: 'boevaya-akrobatika',
    },
    keywordIds: [13, 61],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Совместное отступление и падение пока не исполняется Game.',
  },
  {
    id: 273,
    code: 'udar-nogami',
    type: 'ability',
    name: 'Удар ногами',
    description:
      'Вы можете бить ногами без обычного ограничения по их количеству, доплатив 1ОД. Однако, в таком случае, вы получите дополнительно 2 неустойчивости на всё время ударов.',
    catalogSection: 'abilities-acquired-physical',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [{ type: 'has_ability', ability_code: 'akrobatika', min_level: 2 }],
        },
      ],
      grants: [],
      parent_ability_code: 'akrobatika',
    },
    keywordIds: [13, 61],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Доплата ОД и дополнительная неустойчивость при ударах ногами пока не исполняются Game.',
  },
  {
    id: 274,
    code: 'soblyusti-balans',
    type: 'ability',
    name: 'Соблюсти баланс',
    description:
      'Если вы должны совершить проверку, для которой неустойчивость даёт помехи, вы можете совершить проверку на неустойчивость перед этим с увеличенной на размер сложностью. В случае успеха, неустойчивость больше не будет давать помех для этой проверки, но в случае провала вы падаете.',
    catalogSection: 'abilities-acquired-physical',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [{ type: 'has_ability', ability_code: 'akrobatika', min_level: 3 }],
        },
      ],
      grants: [],
      parent_ability_code: 'akrobatika',
    },
    keywordIds: [13, 61],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Проверка Неустойчивости и снятие помехи пока не исполняются Game.',
  },
  {
    id: 275,
    code: 'kontrol-balansa',
    type: 'ability',
    name: 'Контроль баланса',
    description: 'Вы получаете +[X / 2] к Ловкости от техники для проверок против Неустойчивости.',
    catalogSection: 'abilities-acquired-physical',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [{ type: 'has_ability', ability_code: 'akrobatika', min_level: 3 }],
        },
      ],
      grants: [],
      parent_ability_code: 'akrobatika',
    },
    keywordIds: [13, 61],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'В источнике указана Акробатика 4, но у Акробатики максимум 3 уровня; принято требование уровня 3. Бонус к проверкам против Неустойчивости пока не исполняется Game.',
  },
  {
    id: 276,
    code: 'pryzhki-s-vysoty',
    type: 'ability',
    name: 'Прыжки с высоты',
    description:
      'При приземлении на ноги вы можете перебросить брошенные для определения повреждений от падения кубики, чей результат вас не устраивает.',
    catalogSection: 'abilities-acquired-physical',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 61],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Требование «Выживание или Акробатика» не внесено: код навыка Выживание отсутствует. Переброс кубиков урона от падения пока не исполняется Game.',
  },
  {
    id: 277,
    code: 'razdelka-tush',
    type: 'ability',
    name: 'Разделка туш',
    description: '',
    catalogSection: 'abilities-acquired-other',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 62],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Описание эффекта отсутствует в выгрузке; механику не выдумываем.',
  },
  {
    id: 278,
    code: 'vladenie-muzykalnym-instrumentom',
    type: 'ability',
    name: 'Владение музыкальным инструментом ( x из 3, инструмент )',
    description:
      'Инструментом может выступать голос.\nВы получаете характеристику Музицирование на инструменте , равную 3.\nВы получаете +[ х - 1] к Музицированию на инструменте от владения инструментом .',
    catalogSection: 'abilities-acquired-other',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 1, 2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 62],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Домен выбранного инструмента и связанные с ним бонусы пока не поддержаны; новую ось domain_ref не вводим.',
  },
  {
    id: 279,
    code: 'muzitsirovanie',
    type: 'ability',
    name: 'Музицирование( x из 3)',
    description: 'Вы получаете +X к Музицированию.',
    catalogSection: 'abilities-acquired-other',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 2, 3],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 62],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Требование Владения музыкальным инструментом и доменная связь инструмента пока не выражены в spec.',
  },
  {
    id: 280,
    code: 'trenirovka-melkoy-motoriki',
    type: 'ability',
    name: 'Тренировка мелкой моторики( х из 3)',
    description:
      'Эта черта отображает то, насколько хорошо у вас развита мелкая моторика. Этот навык может понадобиться, например, в хирургии или для краж. Обычно базовая Мелкая моторика равна 3. Однако, некоторые черты могут это изменить. Так у орков она существенно хуже от рождения.\n+ х к Мелкой моторике .',
    catalogSection: 'abilities-acquired-other',
    spaceId: 1,
    spec: {
      type: 'trait',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 1, 2],
        },
      },
      requirements: [],
      grants: [
        {
          level: 1,
          grants: [
            {
              type: 'characteristic_modify',
              characteristic_code: 'fine-motor',
              amount: { type: 'ability_level', ability_code: 'trenirovka-melkoy-motoriki', multiplier: 1 },
              source_code: 'training',
            },
          ],
        },
      ],
      parent_ability_code: null,
    },
    keywordIds: [11, 62],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Grant мелкой моторики добавлен; базовое значение 3 и расовые изменения остаются логикой характеристик.',
  },
  {
    id: 281,
    code: 'vzlom',
    type: 'ability',
    name: 'Взлом',
    description:
      'Вы теперь умеете взламывать обыкновенные замки, используя предназначенные для этого инструменты. Обычно это отмычки. Каждый замок имеет определённую Сложность и Надёжность и может иметь дополнительные эффекты, влияющие на взлом. Сложность - это сложность проверок для взлома замка. Для простейшего замка она равна 1, однако сложность наиболее распространённых простых замков равна 3. Надёжность - это суммарное количество РУ успешных проверок, необходимого для взлома замка. Минимальная надёжность равна 1, однако Надёжность наиболее распространённых простых замков равна 5. Для взлома замка, используя подходящие инструменты для взлома, совершите действие со стоимостью 5ОД - Взлом. В её рамках совершите проверку на взлом: Мелкая моторика со сложностью Сложность взлома замка . В случае успеха вы получаете [ РУ проверки ] прогресса взлома. Если вы накопили Надёжность замка или больше прогресса взлома, то вы его успешно взломали. В случае провала вы теряете [ -РУ проверки ] прогресса взлома. Если при провале вы не выбросили хотя бы один маленький успех, то вы ломаете свой инструмент для взлома и прекращаете эту попытку взлома. По окончании действия взлом вы можете его Продолжить как процесс, стоимостью 5ОД или прекратить попытку взлома. При прекращении Взлома, вы теряете весь прогресс взлома.',
    catalogSection: 'abilities-acquired-other',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 62],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Действие/процесс Взлома, прогресс, Сложность и Надёжность пока не исполняются Game.',
  },
  {
    id: 282,
    code: 'vnimanie-k-detalyam',
    type: 'ability',
    name: 'Внимание к деталям',
    description:
      'Вы получаете +1 к проверке на взлом от восприятия за каждый размер Восприятия выше среднего, вплоть до +3.',
    catalogSection: 'abilities-acquired-other',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'vzlom',
    },
    keywordIds: [13, 62],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Бонус к проверкам Взлома от Восприятия пока не исполняется Game.',
  },
  {
    id: 283,
    code: 'opyt-vzloma',
    type: 'ability',
    name: 'Опыт взлома( х из 5)',
    description: 'Вы получаете +X к Мелкой моторике от мастерства для проверок на Взлом.',
    catalogSection: 'abilities-acquired-other',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 1, 2, 2, 3],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'vzlom',
    },
    keywordIds: [13, 62],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Код проверки Взлома не подтверждён; бонус оставлен текстовым до появления соответствующего runtime.',
  },
  {
    id: 284,
    code: 'karmannye-krazhi',
    type: 'ability',
    name: 'Карманные кражи( х из 5)',
    description: 'Вы получаете +X к Мелкой моторике от мастерства для проверок на карманные кражи.',
    catalogSection: 'abilities-acquired-other',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 1, 2, 2, 3],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 62],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Код проверки карманной кражи не подтверждён; бонус оставлен текстовым до появления соответствующего runtime.',
  },
  {
    id: 285,
    code: 'lovkost-ruk',
    type: 'ability',
    name: 'Ловкость рук',
    description:
      'Вы получаете +1 к проверке на карманную кражу от ловкости за каждый размер Ловкости выше среднего, вплоть до +3.',
    catalogSection: 'abilities-acquired-other',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'karmannye-krazhi',
    },
    keywordIds: [13, 62],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Бонус от Ловкости к проверке карманной кражи пока не исполняется Game.',
  },
  {
    id: 286,
    code: 'razrez',
    type: 'ability',
    name: 'Разрез',
    description:
      'Вы научились скрытно подрезать вещи прямо на людях, чтобы незаметно вытащить их содержимое. Для этого вы используете режущее оружие. В зависимости от того, насколько ваше оружие скрытно, мастер может увеличить сложность проверки. Помимо этого, если Пробитие оружия без учёта силы не превышает защиту разрезаемого материала, сложность проверки увеличивается на 1. Если оно меньше - увеличивается на столько, на сколько оно меньше. Если защита превышает урон, вы не можете разрезать предмет.',
    catalogSection: 'abilities-acquired-other',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'karmannye-krazhi',
    },
    keywordIds: [13, 62],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Действие и проверка Разреза, включая ограничения по Пробитию и защите материала, пока не исполняются Game.',
  },
  {
    id: 287,
    code: 'makiyazh',
    type: 'ability',
    name: 'Макияж',
    description:
      'Этот навык позволяет вам вносить корректировки во внешность с помощью косметических материалов, которые могут улучшить цели [Внешность] на 1, вплоть до 1 или ухудшить на произвольное значение, вплоть до -2. Время, которое необходимо потратить на макияж и время, в течении которого он будет оказывать эффект, зависит от используемых материалов. Важно отметить, что понятия о красоте у разных существ отличается, и вы можете сделать цель более красивой только для тех существ, чьи понятия о красоте вы понимаете.',
    catalogSection: 'abilities-acquired-other',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 62],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Временное изменение Внешности, длительность и зависимость от понимания культуры пока не исполняются Game.',
  },
  {
    id: 288,
    code: 'professionalnyy-makiyazh',
    type: 'ability',
    name: 'Профессиональный макияж',
    description:
      'Это улучшение позволяет вам улучшать внешность цели на 2, если она меньше 0. В таком случае время наложения макияжа увеличивается в два раза.',
    catalogSection: 'abilities-acquired-other',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [3],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'makiyazh',
    },
    keywordIds: [13, 62],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Эффект улучшения Внешности и удвоение времени нанесения пока не исполняются Game.',
  },
  {
    id: 289,
    code: 'masterstvo-makiyazha',
    type: 'ability',
    name: 'Мастерство макияжа',
    description:
      'Это улучшение позволяет вам улучшать внешность цели вплоть до 2, увеличив время наложения макияжа в 5 раз.',
    catalogSection: 'abilities-acquired-other',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [3],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'makiyazh',
    },
    keywordIds: [13, 62],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Эффект улучшения Внешности и увеличение времени нанесения в 5 раз пока не исполняются Game.',
  },
  {
    id: 290,
    code: 'nanesenie-grima',
    type: 'ability',
    name: 'Нанесение грима( х из 3)',
    description:
      'Чтобы нанести проверку на нанесение грима, потратьте комплект для наложения грима и пройдите проверку на нанесение грима: [Качество набора для грима * х ] со сложностью 1, и преимуществом за каждые 6 проворства и восприятия. РУ проверки - это качество получившегося грима. Если не приглядываться, то грим не заметен для существ с восприятием равным или меньше [Качество грима * 2]. Если существо приглядывается, то оно должно потратить 3 секунды и пройти проверку со сложностью, равной РУ вашей проверки. Время накладывания грима зависит от используемых инструментов для грима и уменьшается в х раз. Каждый комплект для грима имеет ограниченные варианты, в кого позволяет замаскироваться. При этом, если у вас не части комплекта для грима, мастер может позволить вам его наложить, увеличив сложность проверки. Если вы хотите загримироваться под конкретного человека, то сложность проверки увеличивается на 1.',
    catalogSection: 'abilities-acquired-other',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'progression',
          max_level: 3,
          base_cost: 1,
          step: 1,
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'makiyazh',
    },
    keywordIds: [13, 62],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Проверки грима, качество, материалы, обнаружение и время маскировки пока не исполняются Game.',
  },
  {
    id: 291,
    code: 'razvitie-obscheniya',
    type: 'ability',
    name: 'Опыт общения',
    description:
      'Изучение методов развития общения увеличивает ваш Опыт общения.\nЗа каждые два метода развития общения со Стоимостью 1 или больше вы получаете +1 к Красноречию от опыта общения. Бонус увеличивается до 2, если помимо этого вы владеете минимум двумя методами со Стоимостью 2 или больше; до 3 — если помимо этого двумя методами со Стоимостью 3 или больше.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [0, 0, 0],
        },
      },
      requirements: [],
      grants: [
        {
          level: 1,
          grants: [
            {
              type: 'characteristic_modify',
              characteristic_code: 'communication',
              amount: { type: 'ability_level', ability_code: 'razvitie-obscheniya', multiplier: 1, offset: 0 },
              source_code: 'experience',
            },
          ],
        },
      ],
      parent_ability_code: null,
      aggregate: {
        characteristic_code: 'communication',
        method_keyword: 'method-communication',
        levels: [2, 2, 2],
      },
    },
    keywordIds: [13, 63],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Агрегат настроен на признак `method-communication`; подсчёт методов по стоимости и бонус к Красноречию пока не исполняются полностью.',
  },
  {
    id: 292,
    code: 'krasnorechie',
    type: 'ability',
    name: 'Тренировка Красноречия',
    description: 'Вы получаете +уровень навыка к Красноречию от тренировок.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 2, 3],
        },
      },
      requirements: [],
      grants: [
        {
          level: 1,
          grants: [
            {
              type: 'characteristic_modify',
              characteristic_code: 'communication',
              amount: { type: 'ability_level', ability_code: 'krasnorechie', multiplier: 1, offset: 0 },
              source_code: 'training',
            },
          ],
        },
      ],
      parent_ability_code: null,
    },
    keywordIds: [13, 63],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 293,
    code: 'manera-obscheniya',
    type: 'ability',
    name: 'Манера общения',
    description:
      'Вам свойственна манера общения, которая предоставляет вам преимущество от манеры для одного из видов проверок общения: на запугивание, на убеждение, на обман, на обольщение или на торговлю.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      domain_ref: 'communication-check',
    },
    keywordIds: [13, 63],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Преимущество от выбранной Манеры общения пока не исполняется Game.',
  },
  {
    id: 294,
    code: 'masterstvo-torga',
    type: 'ability',
    name: 'Мастерство торга( х из 2)',
    description:
      '+X к Красноречию от техники при проверках на торговлю.\n+1 к Красноречию от техники при проверках на торговлю за каждый размер Интеллекта или Восприятия выше среднего, вплоть до максимального бонуса +3.\nЭтот навык не даёт знаний о ценности монет и стоимости товаров. Для этого существует навык «Торговля».',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2, 2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 58, 63],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Бонусы техники к Красноречию при торговле и зависимость от Интеллекта/Восприятия пока не исполняются Game.',
  },
  {
    id: 295,
    code: 'opytnyy-torgovets',
    type: 'ability',
    name: 'Опытный торговец',
    description: '',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [3],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'masterstvo-torga',
    },
    keywordIds: [13, 58, 63],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Описание эффекта отсутствует в выгрузке; механику не выдумываем.',
  },
  {
    id: 296,
    code: 'pronitsatelnyy-torgovets',
    type: 'ability',
    name: 'Проницательный торговец',
    description:
      'Даже не зная стоимости цели торга, вы можете пытаться торговаться как ни в чём ни бывало. Вместо обычных штрафов (повышения на два размера сложности) вы получаете 3 помехи на проверку.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'masterstvo-torga',
    },
    keywordIds: [13, 58, 63],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Проверки торга, стоимость цели и замена штрафа на 3 помехи пока не исполняются Game.',
  },
  {
    id: 297,
    code: 'poverkhnostnaya-otsenka',
    type: 'ability',
    name: 'Поверхностная оценка',
    description:
      'Вы всегда можете определить примерную цену товара, если знаете достаточно о самом товаре и о месте продажи. Для последнего можно потратить время на то, чтобы узнать о местных ценах, если вы вдруг оказались в новом для себя месте. В зависимости от трудности ситуации и ваших знаний мастер может назначить вам проверку [Восприятие + Модификаторы для проверок на торговлю] против установленной им сложности.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'characteristic_value',
              characteristic_code: 'communication',
              min: {
                base: 4,
                size: 0,
              },
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: 'masterstvo-torga',
    },
    keywordIds: [13, 58, 63],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Проверка оценки стоимости и модификаторы торговли пока не исполняются Game.',
  },
  {
    id: 298,
    code: 'khvalebnye-rechi',
    type: 'ability',
    name: 'Хвалебные речи',
    description:
      'Если вы лжёте при торговле, то можете вместо того, чтобы выбрать лучший модификатор, добавить половину модификатора обмана сверх модификатора торговли.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'characteristic_value',
              characteristic_code: 'perception',
              min: {
                base: 4,
                size: 0,
              },
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: 'masterstvo-torga',
    },
    keywordIds: [13, 58, 63, 70],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Сочетание модификаторов Обмана и торговли при лжи пока не исполняется Game.',
  },
  {
    id: 299,
    code: 'pronitsatelnost',
    type: 'ability',
    name: 'Проницательность( х из 2)',
    description:
      '+X к Красноречию от техники при проверках на Проницательность: попытках распознать ложь, намерения, настроение собеседника и т.д.\n+1 к Красноречию от техники при проверках на Проницательность за каждый размер Интеллекта или Восприятия выше среднего, вплоть до максимального бонуса +3.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2, 2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 58, 63],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Бонусы техники к Красноречию при Проницательности пока не исполняются Game.',
  },
  {
    id: 300,
    code: 'dobycha-informatsii',
    type: 'ability',
    name: 'Добыча информации',
    description:
      'Вы понимаете, как и что нужно сказать и сделать, чтобы спровоцировать собеседника выдать словом, мимикой или делом свои намерения. Вы можете совершить проверку на общение любым удобным вам способом(убеждение, обман и т.д.), чтобы это сделать. В случае успеха вы получите [РУ проверки] преимуществ от техники для немедленной проверки на проницательность на тему, согласно которой вы провоцировали, вплоть до 3.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'pronitsatelnost',
    },
    keywordIds: [13, 58, 63],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Провокация собеседника и передача преимуществ на проверку Проницательности пока не исполняются Game.',
  },
  {
    id: 301,
    code: 'kholodnyy-um',
    type: 'ability',
    name: 'Холодный ум',
    description:
      '+1 к Красноречию от техники при проверках на Проницательность во время попытки вас запугать за каждый размер Силы воли выше среднего, вплоть до максимального бонуса +3.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'pronitsatelnost',
    },
    keywordIds: [13, 58, 63],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Бонус к Проницательности против запугивания от Силы воли пока не исполняется Game.',
  },
  {
    id: 302,
    code: 'moralnaya-podderzhka',
    type: 'ability',
    name: 'Моральная поддержка',
    description:
      'При длительном общении (от часа) вы можете пройти проверку на оказание психологической помощи, аналогичную проверке в навыке Психологическая помощь. В случае успеха, собеседник получит преимущество от поддержки для проверки на снятие стресса. При использовании навыка Психологическая помощь это улучшение используется без дополнительных проверок.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'pronitsatelnost',
    },
    keywordIds: [13, 58, 63],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Система стресса, снятие стресса и преимущество от поддержки пока не реализованы Game.',
  },
  {
    id: 303,
    code: 'psikhologicheskaya-pomosch',
    type: 'ability',
    name: 'Психологическая помощь ( х из 2)',
    description:
      'Вы можете оказывать моральную поддержку другим, способным вас понимать существам. Проведите часовой сеанс и пройдите проверку на оказание психологической помощи : Общение со сложностью 1. За каждый размер выше маленького, сложность проверки возрастает на размер. В случае успеха, если это первый сеанс за день, то цель снимает 4 стресса.\n+ х к Общению от знаний для проверок на оказание психологической помощи.\nДлительная терапия : при длительной терапии раз в неделю, после первой недели, вы можете снять не 4, а 5 стресса в случае успеха. А раз в месяц - 6, а не 4 в случае успеха.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2, 1, 2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 57, 58, 63],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Система стресса, снятие стресса, длительная терапия и бонус к Общению от знаний пока не реализованы Game.',
  },
  {
    id: 304,
    code: 'prorabotka-problem',
    type: 'ability',
    name: 'Проработка проблем',
    description:
      'В случае успеха проверки на оказание психологической помощи с 2 РУ или больше цель получает преимущество на проверки против стресса от подготовки на неделю или до перехода на следующий уровень стресса.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'psikhologicheskaya-pomosch',
    },
    keywordIds: [13, 58, 63],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Стресс и преимущество против стресса пока не реализованы Game.',
  },
  {
    id: 305,
    code: 'rabota-nad-soboy',
    type: 'ability',
    name: 'Работа над собой',
    description:
      'Вы можете провести часовой сеанс для себя. Он не снижает стресс, но может оказать эффект от «Проработки проблем».',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'psikhologicheskaya-pomosch',
    },
    keywordIds: [13, 58, 63],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Система стресса и эффект Проработки проблем пока не реализованы Game.',
  },
  {
    id: 306,
    code: 'rabota-nad-soboy-2',
    type: 'ability',
    name: 'Работа над собой',
    description: 'Увеличьте количество преимуществ от поддержки до РУ проверки, вплоть до 4.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'prorabotka-problem',
              min_level: 1,
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: 'psikhologicheskaya-pomosch',
    },
    keywordIds: [13, 58, 63],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Поддержка и передача преимуществ пока не исполняются Game.',
  },
  {
    id: 307,
    code: 'masterstvo-obmana',
    type: 'ability',
    name: 'Мастерство обмана( х из 2)',
    description:
      '+X к Красноречию от техники при попытках обмануть.\n+1 к Красноречию от техники при попытках обмануть за каждый размер Интеллекта выше среднего, вплоть до максимального бонуса +3.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2, 2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 58, 63, 70],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Модификаторы Красноречия к проверкам Обмана пока не исполняются Game.',
  },
  {
    id: 308,
    code: 'bezuprechnyy-drug',
    type: 'ability',
    name: 'Безупречный друг',
    description:
      'Вы легко понимаете людей - их мысли, то что им нравится и т.д., чем можете умело пользоваться. Вы получаете преимущество для бросков на обман, когда пытаетесь втереться в доверие.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'masterstvo-obmana',
    },
    keywordIds: [13, 58, 63, 70],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Преимущество на Обман при попытке втереться в доверие пока не исполняется Game.',
  },
  {
    id: 309,
    code: 'zapugivanie',
    type: 'ability',
    name: 'Запугивание( х из 2)',
    description:
      '+X к Красноречию от техники при попытках запугивания.\n+1 к Красноречию от техники при попытках запугивания за каждый размер Интеллекта выше среднего, вплоть до максимального бонуса +3.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2, 2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 58, 63],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Модификаторы Красноречия к проверкам Запугивания пока не исполняются Game.',
  },
  {
    id: 310,
    code: 'vnushenie-strakha',
    type: 'ability',
    name: 'Внушение страха',
    description:
      'Когда вы запугиваете обманывая, сначала пройдите проверку на Запугивание. За каждый РУ этой проверки оппонент получает помеху на проверку вашего высказывания на Обман, если она потребуется.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'zapugivanie',
    },
    keywordIds: [13, 58, 63, 70],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Связанные проверки Запугивания и передача помех на Обман пока не исполняются Game.',
  },
  {
    id: 311,
    code: 'obolschenie',
    type: 'ability',
    name: 'Обольщение( х из 2)',
    description:
      '+X к Красноречию от техники при попытках обольщения.\n+1 к Красноречию от техники при попытках обольщения за каждый размер Восприятия выше среднего, вплоть до максимального бонуса +3.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2, 2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 58, 63],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Модификаторы Красноречия к проверкам Обольщения пока не исполняются Game.',
  },
  {
    id: 312,
    code: 'lstivye-rechi',
    type: 'ability',
    name: 'Льстивые речи',
    description:
      'Если вы используете это улучшение, то получаете +1 к эффективности проверок на Обольщение. Однако в случае провала это вызовет более сильное отторжение у цели.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'obolschenie',
    },
    keywordIds: [13, 58, 63, 70],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Усиление проверки Обольщения и отторжение при провале пока не исполняются Game.',
  },
  {
    id: 313,
    code: 'vedenie-doprosa',
    type: 'ability',
    name: 'Ведение допроса',
    description:
      'Вы обучены ведению допроса, что даёт вам преимущество от мастерства для проверок на убеждение, запугивание и проницательность при допросе.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 58, 63],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Контекст допроса и преимущество от мастерства пока не исполняются Game.',
  },
  {
    id: 314,
    code: 'akterskoe-masterstvo',
    type: 'ability',
    name: 'Актёрское мастерство( х из 3)',
    description: 'Вы получаете +X к Красноречию для проверок на Актёрское мастерство.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1, 2, 2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 58, 63],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Бонус Красноречия для проверок на Актёрское мастерство пока не исполняется Game.',
  },
  {
    id: 315,
    code: 'igra-po-zhizni',
    type: 'ability',
    name: 'Игра по жизни',
    description: 'Вы получаете +1 к Красноречию для проверок на Обман и Обольщение.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'akterskoe-masterstvo',
    },
    keywordIds: [13, 58, 63, 70],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Бонус Красноречия для проверок на Обман и Обольщение пока не исполняется Game.',
  },
  {
    id: 316,
    code: 'menyaya-maski',
    type: 'ability',
    name: 'Меняя маски',
    description:
      'Вы получаете преимущество от манеры для проверок общения на запугивание, на убеждение, на обман, на обольщение или на торговлю. Одновременно вы можете иметь только один бонус от манеры . Вы не можете изменить манеру во время общения.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'akterskoe-masterstvo',
    },
    keywordIds: [13, 58, 63],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Выбор Манеры и преимущество от неё пока не исполняются Game.',
  },
  {
    id: 317,
    code: 'otvlech-vnimanie',
    type: 'ability',
    name: 'Отвлечь внимание',
    description:
      'Выберите действие тех, чьё внимание вы хотите отвлечь и совершите соответствующее контексту действие. Это может быть широкий круг действий. Например вы можете выкрикнуть фразу, чтобы привлечь внимание противника к себе для того, чтобы вашему товарищу было проще нанести удар. Вы не можете использовать этот навык, если не можете как-либо отвлечь внимание.\nСовершите совместную проверку на Обман против Проницательности целей, которые вы хотите отвлечь. Если ваш способ неубедителен - вы получите от одной до трёх помех на усмотрение ведущего.\nКаждый, кого вам удалось отвлечь, получает помеху для всех проверок, пока не потратит 2ОД и не могут отреагировать на это действие. Помеха не распространяется на цель отвлечения.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 2,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 58, 63, 70],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Действие, совместная проверка Обмана и временные помехи пока не исполняются Game.',
  },
  {
    id: 318,
    code: 'prikinutsya-mertvym',
    type: 'ability',
    name: 'Прикинуться мёртвым',
    description:
      'Вы падаете ничком. Совершите проверку на Актёрское мастерство со сложностью [Пассивная внимательность целей]. Те, против кого вы прошли проверку считают вас погибшим. Однако при тщательном осмотре можно легко обнаружить то, что вы живы.\nПассивная внимательность равна уменьшенной на размер Внимательности.\nСложность проверки может модифицироваться мастером в зависимости от ситуации. Так окружающие вряд ли поверят в то, что вы внезапно умерли.',
    catalogSection: 'abilities-acquired-social',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 3,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 58, 63],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Действие, проверка Актёрского мастерства и сравнение с пассивной Внимательностью пока не исполняются Game.',
  },
  {
    id: 319,
    code: 'blizhniy-boy',
    type: 'ability',
    name: 'Навыки боя',
    description:
      'Этот навык показывает, насколько вы опытны как боец. Первый уровень даётся при 2 опыте ближнего боя, второй — при 8, третий — при 16.\nВы получаете +уровень навыка к Мастерству боя от тренировок.\nОпыт ближнего боя — суммарная стоимость навыков ближнего боя. Навыки владения оружием в этот опыт не входят. Все владеют врождённым естественным оружием, обычно руками и ногами.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [0, 0, 0],
        },
      },
      requirements: [],
      grants: [
        {
          level: 1,
          grants: [
            {
              type: 'characteristic_modify',
              characteristic_code: 'melee-combat',
              amount: { type: 'ability_level', ability_code: 'blizhniy-boy', multiplier: 1, offset: 0 },
              source_code: 'training',
            },
          ],
        },
      ],
      parent_ability_code: null,
      derived_level: {
        source_keyword: 'section-melee',
        thresholds: [2, 8, 16],
      },
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Автоматический подсчёт опыта ближнего боя по стоимости навыков пока не исполняется Game.',
  },
  {
    id: 320,
    code: 'boevye-refleksy',
    type: 'ability',
    name: 'Боевые рефлексы',
    description: 'Для всех проверок на попадание вы получаете +3 от реакции к Восприятию, вплоть до значения Реакции.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'blizhniy-boy',
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Бонус Реакции к Восприятию для проверок попадания пока не исполняется Game.',
  },
  {
    id: 321,
    code: 'otstuplenie',
    type: 'ability',
    name: 'Отступление',
    description:
      'Это реакция на удар по вам. Вы можете использовать её как сопутствующее действие другой реакции на удар. Нельзя одновременно совершать два действия движения, а также одновременно падать и отступать. Стоимость реакции не должна превышать стоимость основного действия.\nСделайте шаг от направления удара. Если шаг выводит вас за пределы радиуса действия оружия, вы получаете бонус к эффективности проверки попадания: +1 при двукратном увеличении расстояния, +2 при четырёхкратном и так далее. После перемещения вы получаете эффект «Неустойчивость».\nЕсли атака была процессом, перемещение происходит после его части; атакующий может совершить действие движения не дороже этой части, получив помеху по правилу одновременных действий.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 2,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 53, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Реакция, перемещение, бонус к попаданию и эффект Неустойчивости пока не исполняются Game.',
  },
  {
    id: 322,
    code: 'zaschita-znaniem',
    type: 'ability',
    name: 'Защита знанием',
    description: 'Вы получаете преимущество для проверок на попадание атак по вам, которые вам известны.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Запоминание известных атак и преимущество против них пока не исполняются Game.',
  },
  {
    id: 323,
    code: 'adaptatsiya-k-protivniku',
    type: 'ability',
    name: 'Адаптация к противнику',
    description:
      'Когда по вам противник совершает удар такой же атакой, которую он уже совершал, или защищается той же реакцией, которую уже использовал, пройдите проверку на Интеллект со сложностью, равной мастерству боя противника. При этом каждый размер восприятия выше среднего даст вам преимущество для этой проверки. В случае успеха отметьте данную атаку или реакцию на атаку данного противника как ту, которой вы нашли противодействие. Вы получаете преимущество для любых проверок на попадание против атак, которым нашли противодействие. Противник, совершающий реакцию на вашу атаку, которой вы нашли противодействие, получает помеху. Этот бонус сохраняется до конца боя.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Память о найденном противодействии, преимущества и помехи до конца боя пока не исполняются Game.',
  },
  {
    id: 324,
    code: 'podavlenie-ponimaniem',
    type: 'ability',
    name: 'Подавление пониманием',
    description:
      'Когда против вас совершается удар атаки или реакция на удар, которой вы уже нашли противодействие, вы получаете вместо одного преимущества по одному преимуществу за каждый размер вашего Интеллекта выше среднего.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [{ type: 'has_ability', ability_code: 'blizhniy-boy', min_level: 1 }],
        },
      ],
      grants: [],
      parent_ability_code: 'adaptatsiya-k-protivniku',
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Найденные противодействия и замена преимущества пока не исполняются Game.',
  },
  {
    id: 325,
    code: 'podderzhka',
    type: 'ability',
    name: 'Поддержка',
    description:
      'Вы можете при атаке ближнего боя, её части или реакции на удар сказать, что используете свободную руку для поддержки. В таком случае вы получите преимущество от поддержки для всех проверок на попадание этой атаки ближнего боя, её части или реакции на удар соответственно.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [{ type: 'has_ability', ability_code: 'blizhniy-boy', min_level: 1 }],
        },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Свободная рука и преимущество поддержки пока не исполняются Game.',
  },
  {
    id: 326,
    code: 'fekhtovanie',
    type: 'ability',
    name: 'Фехтование',
    description:
      'Вы можете применить эффект этого навыка при ударе или реакции на удар, если используете только фехтовальное оружие и у вас свободна как минимум половина рук. Для этого удара вы не получаете бонусов к урону и Пробитию от Силы, кроме описанных в этом навыке. Вы получаете одно преимущество при большом Восприятии и ещё одно, если Сила превышает минимальную для каждого используемого оружия минимум на размер.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [{ type: 'has_ability', ability_code: 'blizhniy-boy', min_level: 1 }],
        },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Условия оружия и свободных рук, а также преимущества Фехтования пока не исполняются Game.',
  },
  {
    id: 327,
    code: 'videnie-boya',
    type: 'ability',
    name: 'Видение боя',
    description:
      'Если вы не застигнуты врасплох, то считается, что вы видите атаки по вам со спины и можете реагировать на них.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'blizhniy-boy',
              min_level: 1,
            },
            {
              type: 'characteristic_value',
              characteristic_code: 'perception',
              min: { base: 0, size: 1 },
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 56, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Атаки со спины и состояние внезапности пока не исполняются Game.',
  },
  {
    id: 328,
    code: 'bezoruzhnyy-boy',
    type: 'ability',
    name: 'Безоружный бой',
    description:
      'Вы получаете +1 к эффективности проверок на уклонение при попытках избежать удара, если у вас есть минимум две свободные руки.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [3],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [{ type: 'has_ability', ability_code: 'blizhniy-boy', min_level: 1 }],
        },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Свободные руки и бонус к уклонению пока не исполняются Game.',
  },
  {
    id: 329,
    code: 'borba',
    type: 'ability',
    name: 'Борьба',
    description: 'Вы получаете +X к Силе и Мастерству боя для всех проверок на захват и толчок.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2, 2, 3],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Бонусы к Силе, захвату и толчку пока не исполняются Game.',
  },
  {
    id: 330,
    code: 'perekhvat',
    type: 'ability',
    name: 'Перехват',
    description:
      'Когда против вас совершают захват, вы можете вместо обычной реакции использовать «Перехват» за 3 ОД. Выполните проверку, аналогичную «Избеганию (Сила)». При успехе с минимум 2 РУ вы не только избегаете захвата, но и сами берёте противника в захват, после чего тратите ещё 1 ОД.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'borba',
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Реакция, проверка и ответный захват пока не исполняются Game.',
  },
  {
    id: 331,
    code: 'kontrudar',
    type: 'ability',
    name: 'Контрудар',
    description:
      'Когда цель, удерживающая вас в захвате, наносит вам удар, вы можете выбрать реакцию «Контрудар» за 2 ОД. Используйте свободную руку и бросьте [Мастерство боя] кубиков, получая преимущество за каждый размер наименьшего из Силы и Проворства. В остальном считается, что вы избегаете удар. При успехе нападающий получает удар собственным оружием с РУ, равным вашему РУ защиты от этого удара.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'borba',
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Реакция, проверка и ответный удар оружием противника пока не исполняются Game.',
  },
  {
    id: 332,
    code: 'brosok-protivnikom',
    type: 'ability',
    name: 'Бросок противником',
    description:
      'Перед захватом вы можете объявить бросок. Если вы получите меньше 2 РУ размера Силы противника за захват, захват проваливается. В противном случае вы берёте цель в захват, тратите дополнительно 1 ОД, перемещаете её в соседнюю клетку, отпускаете, и она падает и получает d3 дробящего урона своего размера. Бросок можно использовать и против цели, которую вы уже удерживаете: проведите проверку на захват и объявите бросок.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'borba',
              min_level: 1,
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 1,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Захват, бросок, перемещение, падение и урон пока не исполняются Game.',
  },
  {
    id: 333,
    code: 'kontrmery',
    type: 'ability',
    name: 'Контрмеры',
    description:
      'Когда против вас совершается действие «Захват», вы можете использовать реакцию «Бросок» за 3 ОД. Она аналогична «Избеганию (Сила)», но при успехе с минимум 2 РУ размера Силы противника вы берёте его в захват, тратите дополнительно 1 ОД, перемещаете в соседнюю клетку, отпускаете, и он падает и получает d3 дробящего урона своего размера.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'brosok-protivnikom',
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Реакция, захват, перемещение, падение и урон пока не исполняются Game.',
  },
  {
    id: 334,
    code: 'boy-s-oruzhiem-v-neskolkikh-rukakh',
    type: 'ability',
    name: 'Бой с оружием в нескольких руках',
    description:
      'Если всё оружие, которое вы выбрали для удара одинаковое, то вы получаете на одну помеху меньше за использование дополнительного оружия.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Снижение помехи за дополнительное оружие пока не исполняется Game.',
  },
  {
    id: 335,
    code: 'balans',
    type: 'ability',
    name: 'Баланс',
    description:
      'Если для каждого оружия, выбранного для удара, у вас есть минимум 4 среднего владения, вы получаете на одну помеху меньше за использование дополнительного оружия.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'boy-s-oruzhiem-v-neskolkikh-rukakh',
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Проверка владения оружием и снижение помехи пока не исполняются Game.',
  },
  {
    id: 336,
    code: 'podgotovka',
    type: 'ability',
    name: 'Подготовка',
    description:
      'При объявлении атаки ближнего боя вы можете заявить, что используете этот навык. В таком случае все ваши удары этой атаки получают помеху, однако, если следующее ваше действие - это атака ближнего боя, то её первый ваш удар получит преимущество для проверки на попадания от подготовки .',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Подготовка атаки, временная помеха и преимущество следующей атаки пока не исполняются Game; обязательна реализация.',
  },
  {
    id: 337,
    code: 'bystryy-udar',
    type: 'ability',
    name: 'Быстрый удар',
    description:
      'Совершите один удар с уменьшенной на 1 точностью. Если следующее действие - атака, то она будет стоить на 1ОД больше.',
    catalogSection: 'abilities-acquired-melee-combat-speed',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 2,
          label: 'Действие',
        },
      ],
      action_effects: [
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
      ],
    },
    keywordIds: [13, 14, 64, 71],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 338,
    code: 'stremitelnyy-udar',
    type: 'ability',
    name: 'Стремительный удар',
    description:
      'Вы подготавливаетесь к совершению стремительного удара. Если ваше следующее действие — атака, на совершение которой вы тратите не более 2 ОД с учётом всех временных модификаторов, то у цели её первого удара модификатор к Мастерству боя от Ловкости уменьшится на 3 (вплоть до 0).',
    catalogSection: 'abilities-acquired-melee-combat-speed',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'characteristic_value',
              characteristic_code: 'reaction',
              min: { base: 3, size: 1 },
            },
            {
              type: 'has_ability',
              ability_code: 'bystryy-udar',
              min_level: 1,
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 1,
          label: 'Действие',
        },
      ],
      action_effects: [
        {
          type: 'next_action_attack_target_characteristic_modifier',
          check_code: 'melee-combat',
          characteristic_code: 'dexterity',
          delta: -3,
          min: 0,
          max_total_action_cost: 2,
          scope: { components: ['strike'], hit_count: 1 },
        },
      ],
    },
    keywordIds: [13, 14, 64, 226],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 339,
    code: 'seriya-udarov',
    type: 'ability',
    name: 'Серия ударов',
    description:
      'Первый удар в процессе стоит 3 ОД, каждый следующий — 2 ОД. Процесс можно прервать в любой момент, а любой промах его прерывает. После окончания процесса вы получаете одну помеху от обстоятельств на любые проверки попадания, пока не потратите 1 ОД.',
    catalogSection: 'abilities-acquired-melee-combat-speed',
    spaceId: 1,
    spec: {
      type: 'process',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'bystryy-udar',
              min_level: 1,
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
      process: {
        start_step_code: 'part-1',
        transition: {
          mode: 'custom',
          edges: [
            { from: 'part-1', to: 'part-2' },
            { from: 'part-2', to: 'part-2' },
          ],
        },
        failure: 'end_action',
        steps: [
          {
            code: 'part-1',
            name: 'Первая часть',
            description: 'Совершите один удар. Промах заканчивает процесс.',
            interruption: { mode: 'normal' },
            costs: [{ resource_code: 'action-points', amount: 3 }],
          },
          {
            code: 'part-2',
            name: 'Вторая часть',
            description: 'Совершите один удар за 2 ОД. Если он промахнулся — процесс заканчивается.',
            interruption: { mode: 'normal' },
            costs: [{ resource_code: 'action-points', amount: 2 }],
          },
        ],
        completion_effects: [
          {
            type: 'after_action_until_resource_spent_check_modifier',
            resource_code: 'action-points',
            amount: 1,
            check_codes: ['check-hit'],
            delta: -1,
          },
        ],
      },
    },
    keywordIds: [13, 14, 15, 64, 71],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Описание приведено к текущему process spec; внутреннее повреждение из старого текста не добавлялось.',
  },
  {
    id: 340,
    code: 'kombinatsiya-udarov',
    type: 'ability',
    name: 'Комбинация ударов',
    description:
      'Процесс из трёх шагов по одной цели. Начало (3 ОД): удар с одной помехой на попадание от действия; попадание даёт Комбо 1, промах заканчивает процесс. Набор (2 ОД): помех от действия столько, сколько текущее Комбо; попадание увеличивает Комбо на 1, промах заканчивает процесс. Завершение (2 ОД, при Комбо не меньше 2): +[Комбо] преимуществ от действия (потолок 3) и +⌊Комбо/2⌋ успехов размера броска (потолок 3) поверх правила 6 и 1; процесс заканчивается и при попадании, и при промахе. Комбо живёт только в этом процессе и только против выбранной цели.',
    catalogSection: 'abilities-acquired-melee-combat-speed',
    spaceId: 1,
    spec: {
      type: 'process',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'and',
              children: [
                {
                  type: 'has_ability',
                  ability_code: 'bystryy-udar',
                  min_level: 1,
                },
                {
                  type: 'has_ability',
                  ability_code: 'tochnyy-udar',
                  min_level: 1,
                },
              ],
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
      process: {
        start_step_code: 'combo-start',
        transition: {
          mode: 'custom',
          edges: [
            { from: 'combo-start', to: 'combo-build' },
            { from: 'combo-build', to: 'combo-build' },
            { from: 'combo-build', to: 'combo-finish' },
          ],
        },
        failure: 'end_action',
        steps: [
          {
            code: 'combo-start',
            name: 'Начало комбо',
            description:
              'Совершите удар с одной помехой на попадание от действия. При попадании Комбо становится 1. При промахе процесс заканчивается.',
            interruption: { mode: 'normal' },
            costs: [{ resource_code: 'action-points', amount: 3 }],
          },
          {
            code: 'combo-build',
            name: 'Набор комбо',
            description:
              'Совершите удар с числом помех на попадание от действия, равным текущему Комбо. При попадании Комбо увеличивается на 1. При промахе процесс заканчивается.',
            interruption: { mode: 'normal' },
            costs: [{ resource_code: 'action-points', amount: 2 }],
          },
          {
            code: 'combo-finish',
            name: 'Завершение комбо',
            description:
              'Доступно при Комбо не меньше 2. Удар получает +[Комбо] преимуществ от действия (потолок 3) и +⌊Комбо/2⌋ успехов размера броска (потолок 3) поверх правила 6 и 1. Процесс заканчивается.',
            interruption: { mode: 'normal' },
            costs: [{ resource_code: 'action-points', amount: 2 }],
          },
        ],
      },
    },
    keywordIds: [13, 14, 15, 64, 71],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Game: три шага Комбо на сессии, пакет закрытия от действия.',
  },
  {
    id: 341,
    code: 'raskrytie',
    type: 'ability',
    name: 'Раскрытие',
    description:
      'При Комбо не меньше 2 вместо Завершения комбо можно объявить другое своё атакующее действие по той же цели. На его первый удар садится пакет Завершения: преимущества и доп. успехи размера броска. Стоимость — ОД той атаки. Процесс заканчивается при попадании и при промахе.',
    catalogSection: 'abilities-acquired-melee-combat-speed',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'kombinatsiya-udarov',
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Game: закрытие Комбо чужой атакой с пакетом Завершения на первый удар.',
  },
  {
    id: 343,
    code: 'oboerukaya-ataka',
    type: 'ability',
    name: 'Обоерукая атака',
    description:
      'Это действие стоит 4 ОД и не может стоить меньше 3 ОД.\nСовершите два удара с одной помехой от обстоятельств, не используя во втором ударе оружие, использованное в первом. Если следующее действие — атака по той же цели, её первый удар получает +1 к точности.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 4,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 64, 71],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Два удара, ограничение стоимости и бонус следующей атаки обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 344,
    code: 'sinkhronnaya-ataka',
    type: 'ability',
    name: 'Синхронная атака',
    description:
      'Вы совершаете все удары синхронно по разным местам. Из-за этого вы получаете одну помеху от обстоятельств , а цель вынуждена защищаться одновременно от двух ударов. Это не позволит ей использовать оружие, чтобы блокировать сразу два удара. И она получит помеху на защиту за каждый удар, от которого защищается после первого.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'oboerukaya-ataka',
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Синхронная защита от нескольких ударов и дополнительные помехи обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 345,
    code: 'sdvoennyy-udar',
    type: 'ability',
    name: 'Сдвоенный удар',
    description:
      'Совершите один удар с двумя помехами на попадания от обстоятельств , используя два оружия . В случае успеха вы попадаете каждым используемым для атаки оружием.\nДля проверки на попадание вы используете наименьшую точность используемых оружий; применяете помехи, которые есть хотя-бы для попадания одним оружием; и используете преимущества, которые распространяются только на каждое используемое оружие.\nВы получаете на одну помеху от обстоятельств больше, если используете голову для удара. Например, для удара рогами.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'blizhniy-boy',
              min_level: 1,
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 3,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 64, 71],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Два оружия, общая точность и распределение помех обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 346,
    code: 'mnozhestvo-ruk',
    type: 'ability',
    name: 'Множество рук',
    description:
      'Вы можете использовать больше двух оружий для удара. В таком случае вместо двух помех получаете столько помех от обстоятельств, сколько используете оружия для атаки.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'sdvoennyy-udar',
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Использование более двух оружий и расчёт помех обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 347,
    code: 'mnozhestvo-udarov',
    type: 'ability',
    name: 'Множество ударов',
    description:
      'Процесс начинается ударом за 3 ОД; каждая следующая часть стоит 2 ОД. Промах завершает процесс. Для удара используется столько помех от обстоятельств, сколько раз соответствующее оружие уже использовалось в процессе.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'process',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'blizhniy-boy',
              min_level: 1,
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
      process: {
        start_step_code: 'part-1',
        transition: {
          mode: 'chain',
          max_shift: 1,
          direction: 'both',
        },
        failure: 'end_action',
        steps: [
          {
            code: 'part-1',
            name: 'Первая часть',
            description: 'Совершите один удар. Если он промахнулся - процесс заканчивается.',
            interruption: { mode: 'normal' },
            costs: [{ resource_code: 'action-points', amount: 3 }],
          },
          {
            code: 'part-2',
            name: 'Часть',
            description:
              'Совершите удар оружием с [Количество использований оружия в процессе] помех от обстоятельств . Т.е. если вы наносили удар правой рукой дважды - вы получите две помехи.',
            interruption: { mode: 'normal' },
            costs: [{ resource_code: 'action-points', amount: 2 }],
          },
        ],
      },
    },
    keywordIds: [13, 14, 15, 64, 71],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 349,
    code: 'razmashistyy-udar',
    type: 'ability',
    name: 'Размашистый удар',
    description:
      'Совершите удар с увеличенной на 2 Силой и помехой на попадание от обстоятельств. Помеха сохраняется для всех проверок на попадание, включая проверки по вам, пока вы не потратите 2 ОД.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 4,
          label: 'Действие',
        },
      ],
      action_effects: [
        {
          type: 'current_action_attack_characteristic_modifier',
          delta: 2,
          scope: { components: ['strike'], hit_count: 1 },
        },
        {
          type: 'current_action_check_modifier',
          check_codes: ['check-hit'],
          delta: -2,
        },
        {
          type: 'after_action_until_resource_spent_check_modifier',
          resource_code: 'action-points',
          amount: 2,
          check_codes: ['check-hit'],
          delta: -2,
        },
      ],
    },
    keywordIds: [13, 14, 64, 71],
    mechanicId: null,
    createdAt: 1786269600,
  },
  {
    id: 350,
    code: 'yarostnyy-ryvok',
    type: 'ability',
    name: 'Яростный рывок',
    description:
      'Вы можете после этого удара пройти проверку на Силу воли со сложностью 3, в случае успеха вы получаете [Сила удара]↓ внутреннего дробящего урона и не получаете помеху после удара.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'razmashistyy-udar',
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Проверка Воли, внутренний дробящий урон и снятие помехи обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 351,
    code: 'udvoennaya-mosch',
    type: 'ability',
    name: 'Удвоенная мощь',
    description:
      'Если при рубящем или дробящем ударе действием Силовой удар вы держите оружие в двух или более руках, то ваша Сила для этого удара увеличена ещё на 1.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'razmashistyy-udar',
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Дополнительная Сила при Силовом ударе и оружии в нескольких руках обязательна к реализации; пока не исполняется Game.',
  },
  {
    id: 352,
    code: 'tolkayuschiy-udar',
    type: 'ability',
    name: 'Толкающий удар',
    description:
      'Совершите Толчок по цели своим оружием, выбрав рубящий или дробящий урон. РУ вашей проверки на толчок снижена на 1, однако такой толчок наносит урон как обычный удар: [Урон * РУ].',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'razmashistyy-udar',
              min_level: 1,
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 3,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 64, 71],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Толчок оружием, снижение РУ и урон обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 353,
    code: 'silovoy-udar',
    type: 'ability',
    name: 'Силовой удар',
    description: 'Совершите один удар по цели с +[РУ атаки]↓ к силе удара от обстоятельств, вплоть до +3.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'and',
              children: [
                {
                  type: 'has_ability',
                  ability_code: 'razmashistyy-udar',
                  min_level: 1,
                },
                {
                  type: 'has_ability',
                  ability_code: 'blizhniy-boy',
                  min_level: 1,
                },
              ],
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 4,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 64, 71],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Расчёт бонуса к Силе удара от РУ атаки обязателен к реализации; пока не исполняется Game.',
  },
  {
    id: 354,
    code: 'shirokiy-udar',
    type: 'ability',
    name: 'Широкий удар',
    description:
      'Совершите рубящий, режущий или дробящий удар по выбранной вами цели и двум стоящим рядом с ней целям с помехой на попадание от обстоятельств за каждую цель после первой.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'and',
              children: [
                {
                  type: 'has_ability',
                  ability_code: 'razmashistyy-udar',
                  min_level: 1,
                },
                {
                  type: 'has_ability',
                  ability_code: 'bystryy-udar',
                  min_level: 1,
                },
              ],
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 4,
          label: 'Действие',
        },
      ],
      attack_mode: 'wide',
      max_targets: 3,
    },
    keywordIds: [13, 14, 64, 71],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Атака по нескольким целям и помехи за дополнительные цели обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 355,
    code: 'tochnyy-udar',
    type: 'ability',
    name: 'Точный удар',
    description: 'Совершите удар с увеличенной на 1 Точностью от обстоятельств.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 4,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 64, 71],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Бонус +1 к Точности от обстоятельств сейчас не отрабатывает; обязательная доработка runtime.',
  },
  {
    id: 356,
    code: 'masterstvo-v-tochnosti',
    type: 'ability',
    name: 'Мастерство в точности',
    description:
      'Если вы имеете понимание (2) во владении оружием, то вы можете уменьшить его для любого удара на 1, чтобы получить +2 к Точности оружия от обстоятельств вместо бонуса +1.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'tochnyy-udar',
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Требование понимания во владении оружием и обмен бонуса Точности обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 357,
    code: 'napravlennyy-udar',
    type: 'ability',
    name: 'Направленный удар',
    description:
      'Совершите удар с уменьшенной на 1 силой. Бонус к Мастерству боя цели от Восприятия для этого удара будет снижен на 3, вплоть до 0.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'tochnyy-udar',
              min_level: 1,
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 3,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 64, 71],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Снижение Силы удара и бонуса Мастерства боя цели обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 358,
    code: 'protivodeystvuyuschiy-udar',
    type: 'ability',
    name: 'Противодействующий удар',
    description:
      'Объявите реакцию (блокирование, уклонение) , которой вы будете противодействовать и совершите удар. Если цель удара совершила эту реакцию в ответ на удар - вы получаете преимущество. Если она не проигнорировала удар и совершила другую реакцию - помеху.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'and',
              children: [
                {
                  type: 'has_ability',
                  ability_code: 'tochnyy-udar',
                  min_level: 1,
                },
                {
                  type: 'has_ability',
                  ability_code: 'blizhniy-boy',
                  min_level: 1,
                },
              ],
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 3,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 64, 71],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Выбор реакции и выдача преимущества/помехи обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 359,
    code: 'udar-v-sochlenenie',
    type: 'ability',
    name: 'Удар в сочленение',
    description:
      'Совершите колющий, рубящий или режущий удар с помехой от обстоятельств за каждую единицу надёжности доспеха цели. Вы получаете на одну помеху меньше, если используете короткое оружие. Вы получаете на одну помеху меньше, если наносите колющий удар. В случае попадания с минимум 1РУ этот удар игнорирует защиту от доспеха.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'and',
              children: [
                {
                  type: 'has_ability',
                  ability_code: 'tochnyy-udar',
                  min_level: 1,
                },
                {
                  type: 'has_ability',
                  ability_code: 'blizhniy-boy',
                  min_level: 1,
                },
              ],
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 4,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 64, 71],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Зависимость помех от Надёжности доспеха, скидки помех и игнорирование защиты обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 360,
    code: 'smertelnyy-udar',
    type: 'ability',
    name: 'Смертельный удар',
    description:
      'Совершите удар. Каждая единица и шестёрка при вашем броске на попадание для этого удара добавляют и убирают х дополнительных успехов соответственно.\nЕсли удар был колющим, то каждая 6 при броске на увечье добавляет дополнительный провал.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2, 2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'tochnyy-udar',
              min_level: 1,
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 3,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 64, 71],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Применение параметра X к успехам, колющий удар и дополнительные провалы обязательны к реализации; доменное требование владения оружием пока не поддержано.',
  },
  {
    id: 361,
    code: 'kriticheskiy-udar',
    type: 'ability',
    name: 'Критический удар',
    description:
      'Эту атаку можно применить только сразу после атаки, каждый удар которой нанёс повреждения.\nВыберите одну из целей ударов прошлой атаки и совершите по ней удар. Каждая 4 и 5 будет считаться за 6, а каждая 2 будет считаться за 1.\nЕсли атака нанесла истощение, то если ваше следующее действие - атака ближнего боя, то она получит эффект Критического удара. Включая возможность распространить эффект на следующую атаку',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [3],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'and',
              children: [
                {
                  type: 'has_ability',
                  ability_code: 'tochnyy-udar',
                  min_level: 1,
                },
                {
                  type: 'has_ability',
                  ability_code: 'silovoy-udar',
                  min_level: 1,
                },
              ],
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 3,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 64, 71],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Условие предыдущей атаки, преобразование результатов и перенос эффекта обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 362,
    code: 'vypad',
    type: 'ability',
    name: 'Выпад',
    description: 'Совершите удар с дальностью действия оружия, увеличенной на полшага. Обычно это ½ ипари.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 4,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 64, 71],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Увеличение дальности действия оружия обязательна к реализации; пока не исполняется Game.',
  },
  {
    id: 363,
    code: 'vyverennyy-udar',
    type: 'ability',
    name: 'Выверенный удар',
    description:
      'Если предыдущая действие оказывает эффект на следующую за ним атаку, то примените его дважды * . *Так двойной удар даёт +1 к точности для следующей атаки; для выверенного удара это будет +2.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 5,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 64, 71],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Требование понимания во владении оружием и удвоение эффекта обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 364,
    code: 'riskovannyy-udar',
    type: 'ability',
    name: 'Рискованный удар',
    description:
      'Совершите атаку из оз одного удара по выбранной вами цели с вдвое меньшей точностью. В случае успеха удара вы получите в два раза больше РУ атаки.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 4,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 64, 71],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Половина Точности, удвоение РУ и требование понимания во владении оружием обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 365,
    code: 'raschetlivaya-ataka',
    type: 'ability',
    name: 'Расчётливая атака',
    description: 'Вы можете потратить жетон концентрации, чтобы получить +1 к Точности удара от концентрации.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'characteristic_value',
              characteristic_code: 'perception',
              min: {
                base: 3,
                size: 1,
              },
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Жетоны концентрации, бонус Точности и доменное требование Мастерства боя обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 366,
    code: 'obezoruzhivanie',
    type: 'ability',
    name: 'Обезоруживание',
    description:
      'Когда вы наносите удар, вы можете за 5РУ атаки удара выбить у неё оружие из рук, если только ваша сила не более чем на два размера меньше, чем у цели. *Чтобы поднять оружие требуется 2ОД.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'blizhniy-boy',
              min_level: 1,
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Обезоруживание за 5 РУ атаки и поднятие оружия за 2 ОД обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 367,
    code: 'udar-v-padenii',
    type: 'ability',
    name: 'Удар в падении',
    description:
      'Когда вы используете навык “удар в движении”, чтобы сделать удар в падении, вы получаете в два раза меньше помех.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'and',
              children: [
                {
                  type: 'has_ability',
                  ability_code: 'blizhniy-boy',
                  min_level: 2,
                },
                {
                  type: 'has_ability',
                  ability_code: 'akrobatika',
                  min_level: 1,
                },
              ],
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Удар в падении и уменьшение помех обязательны к реализации; код навыка «Удар в движении» не выдумывался.',
  },
  {
    id: 368,
    code: 'kontrudar-2',
    type: 'ability',
    name: 'Контрудар',
    description: 'Боевой контрудар за 2 ОД.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'blizhniy-boy',
              min_level: 2,
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 2,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 53, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Описание эффекта отсутствует в выгрузке; реализация Контрудара обязательна, механику не выдумываем.',
  },
  {
    id: 369,
    code: 'prikrytie',
    type: 'ability',
    name: 'Прикрытие',
    description:
      'Реакция на удар, выстрел или бросок по союзнику, находящемуся в пределах шага от вас. Затраты ОД для этой реакции на 1ОД больше, чем для выбранной реакции прикрытия (обычно блокирования).\nВы присоединяетесь к совместной проверке на попадание на стороне цели удара. Выберите один или несколько ударов, от которых вы прикрываете цель удара. Выберите реакцию прикрытия (обычно блокирование с тратой ещё 2ОД). Вы совершаете проверку на попадание так, будто выбранные цели атаковали вас, но с одной помехой от обстоятельств. При этом вы не можете избегать атаку. В случае успеха вашей проверки целью удара считаетесь вы. В случае провала атакующий может выбрать цель сам.\nЕсли вы прикрываете союзника от каждой атаки, от которой защищается он, и у которого есть этот навык, то вы оба получаете преимущество от обстоятельств для проверок.\nПример: союзника атакуют с помощью атаки за 4ОД. У него есть навык Прикрытие. Вы защищаете его с помощью Блокирования. Это потребует [1+2] = 3ОД. Союзник получит преимущество для своей проверки, а вы — не получите помехи. У врага на кубах вышло 2 успеха. У союзника 1. У вас — 3. Итого удар прошёлся на вас вместо союзника и вы его успешно заблокировали.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'blizhniy-boy',
              min_level: 1,
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 1,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 53, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Прикрытие, совместная проверка и перенос результата атаки обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 370,
    code: 'perestanovka',
    type: 'ability',
    name: 'Перестановка',
    description:
      'Вы и ваш союзник, которого вы прикрывали, если не получили истощения, можете сделать по окончанию проверки шаг за 1ОД, чтобы поменяться местами. Если у союзника нет этого улучшения, то шаг обойдётся ему в 2ОД.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'prikrytie',
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Перестановка после Прикрытия и стоимость шага обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 371,
    code: 'podderzhka-v-boyu',
    type: 'ability',
    name: 'Поддержка в бою',
    description:
      'Вы можете совершить эту реакцию, когда кто-либо совершает удар по кому-то, в пределах дистанции вашего оружия. Вы помогаете либо атакующему, либо защищающемуся, давая тому преимущество, и ещё одно преимущество за каждый размер вашего мастерства боя выше среднего.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'blizhniy-boy',
              min_level: 1,
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 2,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 53, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Поддержка в бою и дополнительные преимущества от Мастерства боя обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 372,
    code: 'koordinatsiya',
    type: 'ability',
    name: 'Координация',
    description:
      'Если вы помогаете тому, у кого есть это улучшение, то он может проигнорировать первую выпавшую шестёрку на броске.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'podderzhka-v-boyu',
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Игнорирование первой шестёрки при поддержке обязательно к реализации; пока не исполняется Game.',
  },
  {
    id: 373,
    code: 'obmannyy-manevr',
    type: 'ability',
    name: 'Обманный манёвр',
    description:
      'Вы можете использовать этот навык после того, как были объявлены все защиты против вашего удара, но до проверок. Цель навыка — все, кто выбрал защиту против вашего удара.\nВы и цели навыка совершаете совместную проверку с признаком Обман, вместо обычной проверки на попадание (удар не наносится). Вы для проверки можете использовать либо своё Мастерство боя, либо Обман. Цель может использовать для проверки либо своё Мастерство боя, либо Проницательность. Каждая провалившая проверку цель теряет возможность сделать действие после этой вашей атаки и получает две помехи для проверки на защиту против первого вашего удара по ней в этом ходу.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'or',
              children: [
                {
                  type: 'has_ability',
                  ability_code: 'blizhniy-boy',
                  min_level: 1,
                },
                {
                  type: 'has_ability',
                  ability_code: 'masterstvo-obmana',
                  min_level: 1,
                },
              ],
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 64, 70],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Обманный манёвр, совместная проверка и временные запреты/помехи обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 374,
    code: 'sovmestnaya-ataka',
    type: 'ability',
    name: 'Совместная атака',
    description:
      'Вы можете присоединиться почти к любой атаке - цели будет сложнее сопротивляться нескольким ударам, броскам или выстрелам одновременно.\nКогда кто-либо совершает атаку, вы можете отреагировать. В таком случае эта атака считается Первичной атакой. Совершите атаку, которая стоит не больше ОД, нежели первичная атака. Эта атака получит признак Вторичной и к ней нельзя будет присоединиться посредством этого навыка.\nПри каждом ударе, броске или выстреле первичной атаки вы можете совершить один удар, бросок или выстрел вторичной атаки по той же цели.\nЦель выбирает реакцию на каждую атаку, которую не собирается игнорировать и тратит ОД по наибольшей стоимости среди выбранных реакций. Для одинаковых реакций цель бросает только одну проверку, к которой применяются все штрафы каждой проверки для этой реакции. Помимо этого применяется правило об одновременных действиях - цель получит столько помех от состояния , сколько действий (в т.ч. реакций) совершает.\nПример: цель получает удар спереди и с фланга. Блокировать нечем, поэтому она решает увернуться от от ударов. Т.к. среди них есть удар с фланга, она получает 2 помехи от обстоятельств. Т.к. совершается две реакции уклонения, она получает 2 помехи от состояния. Итого уклонение за 1ОД с 4 помехами.\nОдновременно бить по цели и не мешать друг другу - не просто. Вы получаете помеху на попадание от обстоятельств , если первичный атакующий не владеет этим навыком. Вы получаете помеху на попадание от обстоятельств . если атакуете не той же атакой, что и первичный атакующий. И вы получаете две помехи от обстоятельств , если соответствуете обоим условиям.',
    catalogSection: 'abilities-acquired-melee-combat',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'blizhniy-boy',
              min_level: 1,
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Совместная атака, первичная/вторичная атаки и одновременные реакции обязательны к реализации; пока не исполняются Game.',
  },
  {
    id: 375,
    code: 'opyt-koordinatsii-atak',
    type: 'ability',
    name: 'Опыт координации атак',
    description:
      'Вы получаете на одну помеху от обстоятельств меньше для проверки на попадание по цели этим навыком от условий этого навыка.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'sovmestnaya-ataka',
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Обязательная боевая доработка: снижение помехи в Совместной атаке пока не исполняется Game.',
  },
  {
    id: 376,
    code: 'obezoruzhit-protivnika',
    type: 'ability',
    name: 'Обезоружить противника',
    description:
      'Вы можете применить этот манёвр при ударе, блокировании или парировании удара с 5РУ.\nЕсли вы защищаетесь - вы выбиваете одно заблокированное или полированное оружие атакующего. Если вы наносите удар - вы выбиваете одно оружие цели удара вместо нанесения урона.',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 1,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 54, 65],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Обязательная боевая доработка: триггеры, стоимость 5 РУ, выбивание оружия и поднятие оружия пока не исполняются Game.',
  },
  {
    id: 377,
    code: 'zastavit-otkrytsya',
    type: 'ability',
    name: 'Заставить открыться',
    description:
      'Вы можете применить этот манёвр при блокировании или парировании удара с 2РУ.\nВы заставляете атакующего открыться для вашего удара. Цель атаки получает Неустойчивость 1 , а вы можете после этого манёвра немедленно совершить любое действие Атаки не более чем за 3ОД.',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 1,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 54, 65],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Обязательная боевая доработка: блокирование/парирование, Неустойчивость и немедленная атака пока не исполняются Game.',
  },
  {
    id: 378,
    code: 'razbit-zaschitu',
    type: 'ability',
    name: 'Разбить защиту',
    description: 'Вы можете применить этот манёвр при ударе 3РУ.\nЦель атаки получает Неустойчивость 1.',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 1,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 54, 65],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Обязательная боевая доработка: манёвр при ударе и наложение Неустойчивости пока не исполняются Game.',
  },
  {
    id: 379,
    code: 'vskryt-slabost',
    type: 'ability',
    name: 'Вскрыть слабость',
    description:
      'При 4РУ манёвр Заставить открыться и Разбить защиту дают цели Неустойчивость 2 . При этом Заставить открыться позволяет провести атаку за 4ОД.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'razbit-zaschitu',
    },
    keywordIds: [13, 54, 65],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Обязательная боевая доработка: усиление Заставить открыться и Разбить защиту до Неустойчивости 2 пока не исполняется Game.',
  },
  {
    id: 380,
    code: 'poymat-moment',
    type: 'ability',
    name: 'Поймать момент',
    description:
      'Вы можете применить этот манёвр при уклонении с 3РУ.\nВы можете немедленно нанести атаку не более чем за 3ОД по тому, от чьего удара уклонились.',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 3,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 54, 65],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote: 'Обязательная боевая доработка: реакция после уклонения и немедленная атака пока не исполняются Game.',
  },
  {
    id: 381,
    code: 'smenit-pozitsiyu',
    type: 'ability',
    name: 'Сменить позицию',
    description:
      'Вы можете применить этот манёвр при уклонении с 2РУ.\nНемедленно совершите действие Осторожное передвижение так, как будто потратили 2ОД.',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      action_components: [
        {
          type: 'resource',
          resource_code: 'action-points',
          amount: 1,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 54, 65],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Обязательная боевая доработка: реакция после уклонения и действие Осторожное передвижение пока не исполняются Game.',
  },
  {
    id: 382,
    code: 'mnogooborotnaya-tekhnika-metaniya',
    type: 'ability',
    name: 'Многооборотная техника метания',
    description:
      'При броске можно использовать только одну технику метания. Используя эту технику, вы получаете +1 к точности броска от техники .\nВы можете использовать “Мастерство боя” для метания, прибавляя к нему владение этой техникой.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 55, 66, 68],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Обязательная боевая доработка дальнего боя: выбор техники, бонус Точности и использование Мастерства боя пока не исполняются Game.',
  },
  {
    id: 383,
    code: 'moschnaya-zakrutka',
    type: 'ability',
    name: 'Мощная закрутка',
    description:
      'Вы можете увеличить на размер силу броска многооборотной техникой для определения урона и пробития, уменьшив Дальнобойность на 1.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'mnogooborotnaya-tekhnika-metaniya',
    },
    keywordIds: [13, 66],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Обязательная боевая доработка дальнего боя: перенос размера в Силу броска, урон, пробитие и штраф Дальнобойности пока не исполняются Game.',
  },
  {
    id: 384,
    code: 'otrabotannaya-tekhnika',
    type: 'ability',
    name: 'Отработанная техника',
    description:
      'При использовании многооборотной техники вы получаете +1 к точности от восприятия в том случае, если вы можете определить расстояние до цели. Сделать это можно, например, пройдя проверку на Внимательность со сложностью [Ипари до цели]↓ за 1ОД.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'mnogooborotnaya-tekhnika-metaniya',
    },
    keywordIds: [13, 66],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Обязательная боевая доработка дальнего боя: бонус Точности от Восприятия и проверка Внимательности для определения расстояния пока не исполняются Game.',
  },
  {
    id: 385,
    code: 'bezoborotnaya-tekhnika-metaniya',
    type: 'ability',
    name: 'Безоборотная техника метания',
    description:
      'При броске можно использовать только одну технику метания. Используя эту технику, сила броска увеличивается на один размер от техники .\nВы можете использовать “Мастерство боя” для метания, прибавляя к нему владение этой техникой.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 55, 66, 68],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Обязательная боевая доработка дальнего боя: выбор техники, перенос размера в Силу броска и использование Мастерства боя пока не исполняются Game.',
  },
  {
    id: 386,
    code: 'dalniy-brosok',
    type: 'ability',
    name: 'Дальний бросок',
    description: 'Сила броска уменьшается на размер за каждые две Дальнобойности до цели, а не одну.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'bezoborotnaya-tekhnika-metaniya',
    },
    keywordIds: [13, 66],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Обязательная боевая доработка дальнего боя: изменение потери Силы броска по Дальнобойности пока не исполняется Game.',
  },
  {
    id: 387,
    code: 'otrabotannaya-tekhnika-2',
    type: 'ability',
    name: 'Отработанная техника',
    description: 'При использовании безоборотной техники метания Дальнобойность увеличивается на 1 от техники .',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'bezoborotnaya-tekhnika-metaniya',
    },
    keywordIds: [13, 66],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Обязательная боевая доработка дальнего боя: увеличение Дальнобойности от Безоборотной техники пока не исполняется Game.',
  },
  {
    id: 388,
    code: 'ataka-po-nezaschischennym-mestam',
    type: 'ability',
    name: 'Атака по незащищённым местам',
    description:
      'Вы можете применить один раз применить этот навык к любому своему выстрелу или броску, дав ему двойную помеху на попадание. Считайте Надёжность доспеха для этого выстрела вдвое меньшей.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 66],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Обязательная боевая доработка дальнего боя: применение к выстрелу/броску, двойная помеха и уменьшение Надёжности доспеха пока не исполняются Game.',
  },
  {
    id: 389,
    code: 'popadanie-po-sochleneniyam',
    type: 'ability',
    name: 'Попадание по сочленениям',
    description:
      'Вместо двойной помехи этим навыком вы можете применить тройную помеху, в таком случае правило будет распространяться на все виды защиты, имеющие надёжность (в т.ч. от кожи) .',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [3],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'ataka-po-nezaschischennym-mestam',
    },
    keywordIds: [13, 66],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Обязательная боевая доработка дальнего боя: усиление Атаки по незащищённым местам и распространение на все виды защиты пока не исполняются Game.',
  },
  {
    id: 390,
    code: 'ataka-po-uyazvimym-mestam',
    type: 'ability',
    name: 'Атака по уязвимым местам',
    description:
      'Вы можете применить один раз применить этот навык к любому своему выстрелу или броску за 1ОД. Если у попадания в результате будет 3РУ или больше, то он получит ещё 1РУ. Если меньше - то промахнется.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 66],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Обязательная боевая доработка дальнего боя: условие по 3 РУ, дополнительное РУ и автоматический промах пока не исполняются Game.',
  },
  {
    id: 391,
    code: 'smertelnyy-vystrel',
    type: 'ability',
    name: 'Смертельный выстрел',
    description:
      'Вы не можете одновременно применить этот навык и навык “атака по уязвимым местам”. Если вы применяете этот навык к броску или выстрелу, то потратьте 2ОД. Если у попадания в результате будет больше 4РУ, то он получит +2РУ атаки. Если меньше - то промахнется.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [3],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'ataka-po-uyazvimym-mestam',
    },
    keywordIds: [13, 66],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Обязательная боевая доработка дальнего боя: несовместимость с Атакой по уязвимым местам, порог 4 РУ, +2 РУ и автоматический промах пока не исполняются Game.',
  },
  {
    id: 392,
    code: 'riskovannaya-ataka',
    type: 'ability',
    name: 'Рискованная атака',
    description:
      'Вы можете применить один раз применить этот навык к любому своему выстрелу или броску. В таком случае каждая 1 будет генерировать дополнительно один кубик в рамках правила 6 и 1, но 6 при выпадении будут немедленно убирать один успех (до применения правила 6 и 1, помех, преимуществ и т.д.)',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 66],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Обязательная боевая доработка дальнего боя: изменение правила 6 и 1 и порядок применения помех/преимуществ пока не исполняются Game.',
  },
  {
    id: 393,
    code: 'popadanie-po-sochleneniyam-2',
    type: 'ability',
    name: 'Попадание по сочленениям',
    description:
      'Когда вы применяете рискованную атаку к выстрелу, вы можете применить также это улучшение. В таком случае правило “6 и 1” будет обязывать бросить за 1 в два раза больше кубиков, но взамен уже выпавшей единицы.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [3],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'riskovannaya-ataka',
    },
    keywordIds: [13, 66],
    mechanicId: null,
    createdAt: 1786269600,
    contentNote:
      'Обязательная боевая доработка дальнего боя: усиленное правило 6 и 1 для Рискованной атаки пока не исполняется Game.',
  },
  ...mockWeaponSkillsImport,
];

export const mockDevelopmentImport: Rule[] = importedRuleNameService.sanitizeCatalog(mockDevelopmentImportRaw);
