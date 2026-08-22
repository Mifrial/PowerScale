import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { mockWeaponSkillsImport } from './mockWeaponSkillsImport';

/**
 * Импорт каталога навыков этапа «Развитие» (Фаза 6, S14).
 * Источник: docs/rule/skills/catalog.md (из docs/rule/skills/AI.html).
 * Типы по D106: Реакция/Манёвр — признаки действия; Множественный — skill + multiple + domain_ref;
 * Техника метания/Клич — навыки с признаком; «Эффект» — state; «Группа навыков» (владение оружием) — отложено.
 * Агрегаты «Развитие X» и производный «Ближний бой» — вычисляются (D108/D109).
 * Оружейные навыки из docs/rule/battle/weapon-skills.md — импортируются отдельно.
 */

export const mockDevelopmentImport: Rule[] = [
  {
    id: 'rule-200',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-201',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-202',
    code: 'razvitie-vnimatelnosti',
    type: 'ability',
    name: 'Развитие внимательности( х из 3)',
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
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-203',
    code: 'razvitie-reaktsii',
    type: 'ability',
    name: 'Развитие реакции( х из 3)',
    description: 'Вы получаете + х к Базовому Восприятию для проверок на реакцию от тренировок .',
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
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-204',
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
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 56],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-205',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-206',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-207',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-208',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-209',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-210',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-211',
    code: 'poisk-trav',
    type: 'ability',
    name: 'Поиск трав',
    description:
      'Вы не только знаете о внешнем виде, свойствах и местах произрастания трав, но и обучены их поиску и сбору. +3 к Внимательности от мастерства для проверок на поиск и сбор знакомых трав.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-212',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-213',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-214',
    code: 'razvitie-pamyati',
    type: 'ability',
    name: 'Развитие памяти( х из 3)',
    description: 'Вы получаете + х к Базовому Интеллекту для проверок на память от тренировок .',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-215',
    code: 'razvitie-myshleniya',
    type: 'ability',
    name: 'Развитие мышления( х из 3)',
    description: 'Вы получаете + х к Базовому Интеллекту для проверок на мышление от тренировок .',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-216',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-217',
    code: 'neytralizatsiya-pomekh-2',
    type: 'ability',
    name: 'Нейтрализация помех',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-218',
    code: 'kontsentratsiya',
    type: 'ability',
    name: 'Концентрация',
    description:
      'Вы получаете ресурс “Жетоны концентрации”, максимум которых - 1. Каждый размер восприятия и каждый размер интеллекта выше среднего увеличивают максимум на 1.\nПеред совершением проверки на попадание, восприятие, интеллект, общение или проворство вы можете потратить жетон концентрации, чтобы получить преимущество для этой проверки. В конце вашего хода, если вы не тратили жетоны концентрации с конца предыдущего хода, вы можете восполнить количество жетонов концентрации до максимума. В таком случае вы не сможете тратить жетоны концентрации до начала своего следующего хода.\nЕсли вы получаете повреждения, превышающие [Телосложение], рану или увечье, то совершите проверку на сохранение концентрации - проверку на Силу воли против боли со сложностью 2. Если повреждения превышают [Телосложение * 2]; сила раны 5 или больше; серьёзность увечья 2 - сложность становится большой. Если серьёзность полученного увечья 3 - огромной.\nНекоторые действия используют жетоны концентрации - используемые жетоны нельзя тратить, и пока вы используете хотя бы один жетон, нельзя восполнять жетоны концентрации. Когда вы перестанете использовать жетон концентрации, он станет потраченным.\nКонцентрация - это кратковременное сосредоточение умственных сил - вы не можете поддерживать её дольше одного хода. Когда вы должны перестать концентрироваться, вы можете потратить жетон концентрации, чтобы продолжить концентрироваться ещё такое же время.',
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
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-219',
    code: 'predelnaya-kontsentratsiya',
    type: 'ability',
    name: 'Предельная концентрация( x из 2 )',
    description:
      'Вы можете тратить сразу несколько кубиков концентрации для получения преимуществ. Вы получаете столько преимуществ, сколько потратили кубиков для получения преимуществ, вплоть до [ х + 1].',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'progression',
          max_level: 2,
          base_cost: 1,
          step: 1,
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'kontsentratsiya',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-220',
    code: 'sosredotochenie-vnimaniya',
    type: 'ability',
    name: 'Сосредоточение внимания',
    description:
      'Когда вы концентрируетесь на чём-либо для получения преимуществ, вы можете использовать это улучшение, чтобы получить дополнительное преимущество. Но, в таком случае, все ваши проверки до начала вашего хода будут иметь помеху.',
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
      parent_ability_code: 'kontsentratsiya',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-221',
    code: 'parallelnye-deystviya',
    type: 'ability',
    name: 'Параллельные действия',
    description:
      'Вы можете сконцентрироваться на выполнении параллельных действий, используя k жетонов концентрации. Пока вы концентрируетесь на выполнении параллельных действий, вы получаете на k меньше помех от совершения параллельных действий.',
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
      parent_ability_code: 'kontsentratsiya',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-222',
    code: 'sosredotochenie-voli',
    type: 'ability',
    name: 'Сосредоточение воли',
    description:
      'Когда вы совершаете проверку на потерю сознания, вы можете использовать жетоны все концентрации (минимум 1) , чтобы получить преимущество для её совершения.',
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
      parent_ability_code: 'kontsentratsiya',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-223',
    code: 'volevoe-usilie',
    type: 'ability',
    name: 'Волевое усилие',
    description:
      'Когда вы должны прекратить концентрироваться из-за предела времени, вы можете пройти проверку на сохранение концентрации волей - это проверка на Силу воли со сложностью 1. В случае успеха время концентрации продлится на [Максимальное время концентрации]. В случае провала вы получаете не больше 1 истощения силы воли. Вне зависимости от успеха, сложность проверок на сохранение концентрации силой воли увеличена на размер до тех пор, пока вы концентрируетесь на чём-либо.',
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
      parent_ability_code: 'kontsentratsiya',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-224',
    code: 'molnienosnaya-reaktsiya',
    type: 'ability',
    name: 'Молниеносная реакция',
    description:
      'Вы можете сконцентрироваться, используя 2 жетона концентрации. В таком случае вы получите + 3 к реакции от концентрации до тех пор, пока концентрируетесь.',
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
      parent_ability_code: 'kontsentratsiya',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-225',
    code: 'dlitelnoe-napryazhenie',
    type: 'ability',
    name: 'Длительное напряжение( n из 5)',
    description: 'Вы можете концентрироваться вплоть до [2 * n ] ходов.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'progression',
          max_level: 1,
          base_cost: 3,
          step: 0,
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: 'kontsentratsiya',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-226',
    code: 'vladenie-yazykom',
    type: 'ability',
    name: 'Владение языком( Язык , х из 3)',
    description: '',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-227',
    code: 'pravilnoe-proiznoshenie',
    type: 'ability',
    name: 'Правильное произношение',
    description: '',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-228',
    code: 'pismennost',
    type: 'ability',
    name: 'Письменность',
    description: 'Даёт возможность писать на выбранном вами языке.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-229',
    code: 'chtenie-po-gubam',
    type: 'ability',
    name: 'Чтение по губам( Вид )',
    description: '',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-230',
    code: 'gramotnost',
    type: 'ability',
    name: 'Грамотность',
    description: '',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-231',
    code: 'etiket',
    type: 'ability',
    name: 'Этикет( Культура )',
    description: '',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-232',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-233',
    code: 'schet',
    type: 'ability',
    name: 'Счёт',
    description: '',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-234',
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
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-235',
    code: 'estestvoznanie',
    type: 'ability',
    name: 'Естествознание',
    description: '',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-236',
    code: 'fizika',
    type: 'ability',
    name: 'Физика',
    description: '',
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
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-237',
    code: 'torgovlya',
    type: 'ability',
    name: 'Торговля( регион )',
    description: '',
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
      domain_ref: 'region',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-238',
    code: 'znanie-zakonov',
    type: 'ability',
    name: 'Знание законов( субъект )',
    description: '',
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
      domain_ref: 'subject',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-239',
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
      parent_ability_code: 'znanie-zakonov',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-240',
    code: 'znanie-o-zhivotnykh',
    type: 'ability',
    name: 'Знание о животных( регион )',
    description: '',
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
      domain_ref: 'region',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-241',
    code: 'znanie-o-rasteniyakh',
    type: 'ability',
    name: 'Знание о растениях( регион )',
    description: '',
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
      domain_ref: 'region',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-242',
    code: 'znanie-o-istorii',
    type: 'ability',
    name: 'Знание о истории( регион )',
    description: '',
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
      domain_ref: 'region',
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-243',
    code: 'shifr',
    type: 'ability',
    name: 'Шифр',
    description: '',
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
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 57],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-244',
    code: 'slozhnyy-shifr',
    type: 'ability',
    name: 'Сложный шифр',
    description:
      'Вы узнаёте конкретный способ сложной шифра. Сложность и его расшифровки на размер больше обычной. Запись и расшифровка его занимают в десять раз больше времени.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-245',
    code: 'pervaya-pomosch',
    type: 'ability',
    name: 'Первая помощь( вид )',
    description: '',
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
      domain_ref: 'species',
    },
    keywordIds: [13, 59],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-246',
    code: 'predpisaniya-o-lechenii',
    type: 'ability',
    name: 'Предписания о лечении',
    description: '',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-247',
    code: 'sporaya-perevyazka',
    type: 'ability',
    name: 'Спорая перевязка',
    description: 'Уменьшает затраты времени на перевязку ранений на 2ОД от мастерства .',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-248',
    code: 'ukhod',
    type: 'ability',
    name: 'Уход( вид )',
    description: '',
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
      domain_ref: 'species',
    },
    keywordIds: [13, 59],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-249',
    code: 'znanie-bolezney',
    type: 'ability',
    name: 'Знание болезней( вид , х )',
    description:
      'Даёт возможность определять болезни и даёт знание, чем можно болезни лечить, будь то травы, порошки или мази, но не даёт знаний о изготовлении лекарств.\nИнтеллект увеличен на х размеров для проверок на знание болезней.\nБазовая сложность опознания распространённой болезни равна 2; редкой - 4; очень редкой - 8;\nНекоторые болезни может быть трудно опознать. Если болезнь не имеет ярко выраженных симптомов, то сложность её распознания увеличивается на размер. Некоторые болезни могут иметь повышенную на два и три размера стоимость, или даже не быть возможными для распознавания доступными методами.\nПодробность описаний симптомов как правило влияет на сложность определения болезни. При их отсутствии сложность увеличивается на размер. При неверном описании симптомов, помимо этого, на проверку налагается две помехи.',
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
      multiple: true,
      domain_ref: 'species',
    },
    keywordIds: [13, 57, 59],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-250',
    code: 'fiziologiya',
    type: 'ability',
    name: 'Физиология( вид , х )',
    description: '',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2, 4, 4],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      multiple: true,
      domain_ref: 'species',
    },
    keywordIds: [13, 59],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-251',
    code: 'farmatsiya',
    type: 'ability',
    name: 'Фармация( вид , х )',
    description: '',
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
      multiple: true,
      domain_ref: 'species',
    },
    keywordIds: [13, 59],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-252',
    code: 'khirurgiya',
    type: 'ability',
    name: 'Хирургия( вид , х )',
    description:
      'Вы получаете возможность проводить проверки на Хирургию. Базовая характеристика для проверок на Хирургию - это наименьшая из Предела хирургии , Мелкой моторики и Внимательности . Предел хирурги равен 5 и каждый уровень этого навыка после первого повышает его на размер.\nПомимо этого вы получаете к проверкам на Хирургию +[ х + Физиология] от мастерства .',
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
      multiple: true,
      domain_ref: 'species',
    },
    keywordIds: [13, 57, 59],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-253',
    code: 'smertonosnye-udary',
    type: 'ability',
    name: 'Смертоносные удары',
    description:
      'Когда вы наносите кому-либо, относящемуся к известному вам виду увечье, вы можете использовать этот навык, чтобы дать ему помеху или преимущество на совершение проверки на получение увечья.',
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
    },
    keywordIds: [13, 59],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-254',
    code: 'pitanie',
    type: 'ability',
    name: 'Питание( вид )',
    description:
      'Вы знаете о свойствах разной еды и различные способы питания для существа вида, и схожим с этим видом существ.\nПитание для развития тела : если вы тренируетесь на протяжении большей части приобретения черты Физическое развитие, то её стоимость уменьшится на 1, вплоть до 2. Стоимость еды для подобной программы питания при генерации персонажа считайте равной 6гз в месяц.\nПитание для похудения : вы можете составить программу питания для похудения, которая не будет вредить здоровью.\nПитание для больного : вы можете составить программу для ослабленного человека или для человека с проблемами с пищеварением.',
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
      domain_ref: 'species',
    },
    keywordIds: [13, 57, 59],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-255',
    code: 'trenirovka-voli',
    type: 'ability',
    name: 'Тренировка воли( х из 3)',
    description: 'Вы получаете + х к Силе воли.',
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
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 60],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-256',
    code: 'podavlenie-somneniy',
    type: 'ability',
    name: 'Подавление сомнений',
    description: 'При броске на силу воли вы можете убрать одну выпавшую 6 до применения каких-либо эффектов.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-257',
    code: 'nesgibaemyy-razum',
    type: 'ability',
    name: 'Несгибаемый разум',
    description: 'Вы получаете [Сила воли] сопротивления психическому урону от разума .',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-258',
    code: 'otkhodchivost',
    type: 'ability',
    name: 'Отходчивость',
    description: '+1 к эффективности проверок на восстановление воли.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-259',
    code: 'adaptatsiya',
    type: 'ability',
    name: 'Адаптация',
    description: 'Вы бросаете на 1 кубик больше для проверки на восстановление силы воли.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-260',
    code: 'osnovy-fizicheskogo-razvitiya',
    type: 'ability',
    name: 'Основы физического развития( х из 3)',
    description:
      'Каждый уровень этой тренировки повышает или Силу или Ловкость или Стойкость и Выносливость на на 1. Вы можете выбирать каждый раз новую характеристику или ту же самую. Однако, если взять характеристику третий раз, то вы получите описанный ниже штраф от тренировки.\nСила: -1 к Ловкости; Ловкость: -1 к Силе; Стойкость: вы получаете только +1 к Стойкости для проверок на Выносливость.',
    spaceId: 1,
    spec: {
      type: 'trait',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2, 2, 2],
        },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [11, 61],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-261',
    code: 'fizicheskoe-razvitie',
    type: 'ability',
    name: 'Физическое развитие( х из 3)',
    description:
      'Каждый уровень этой тренировки повышает или Силу или Ловкость или Стойкость на 1. Вы можете выбирать каждый раз новую характеристику или ту же самую. Однако, если взять характеристику третий раз, то вы получите описанный ниже штраф от тренировки.\nСила: -1 к Ловкости; Если уже получали штраф к Ловкости от тренировки, то ещё -1 к Стойкости.\nЛовкость: -1 к Силе; Если уже получали штраф к Силе от тренировки, то ещё -1 к Стойкости.\nСтойкость: вы получаете только +1 к Стойкости для проверок на Выносливость.',
    spaceId: 1,
    spec: {
      type: 'trait',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [3, 3, 3],
        },
      },
      requirements: [
        {
          level: 1,
          requirements: [
            {
              type: 'has_ability',
              ability_code: 'osnovy-fizicheskogo-razvitiya',
              min_level: 3,
            },
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [11, 61],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-262',
    code: 'trenirovka-skorosti',
    type: 'ability',
    name: 'Тренировка скорости',
    description: '+1 к лимиту ОД от тренировки .',
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
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [11, 61],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-263',
    code: 'manevrennost',
    type: 'ability',
    name: 'Манёвренность( х из 2)',
    description: '',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-264',
    code: 'begun',
    type: 'ability',
    name: 'Бегун',
    description: 'Каждая часть бега позволяет вам переместиться на 1 шаг больше.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-265',
    code: 'skrytnost',
    type: 'ability',
    name: 'Скрытность( х из 3)',
    description: 'Вы получаете +[2 * х ] к проворству от мастерства для проверок на скрытность(проворство).',
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
      parent_ability_code: null,
    },
    keywordIds: [13, 61],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-266',
    code: 'ottochennyy-navyk',
    type: 'ability',
    name: 'Отточенный навык',
    description: '',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-267',
    code: 'akrobatika',
    type: 'ability',
    name: 'Акробатика( х из 3)',
    description:
      'Вы получаете + х к проворству от техники для проверок на Акробатику.\nПроверки на Акробатику могут основываться на Реакции, Силе и/или Ловкости. Если проверка основывается на нескольких характеристиках - то для проверки используется наименьшая.\nВ качестве примера вариант правил для прыжка от стены. Это проверка на Акробатику(сила) со сложностью 3 для первого прыжка. Для второго прыжка сложность уже будет 3 ↑ и стоить 2 энергии. Последующие прыжки будут иметь сложность 3 ↑↑ и стоить 2 ↑ энергии.',
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
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 61],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-268',
    code: 'ottochennyy-navyk-2',
    type: 'ability',
    name: 'Отточенный навык',
    description: '',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-269',
    code: 'boevaya-akrobatika',
    type: 'ability',
    name: 'Боевая акробатика',
    description:
      '+[ х / 2] к Ловкости от техники для реакции на атаку по вам Избежать, если ваша Сила превышает минимальную каждого используемого оружия минимум на размер. Если вы не используете при защите ни оружие, ни щит, то вашим оружием считаются ваши руки.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-270',
    code: 'smertonosnye-tryuki',
    type: 'ability',
    name: 'Смертоносные трюки',
    description:
      'Бонус от улучшения Боевая акробатика распространяется на все совместные проверки попадание ударом, а не только при реакции Избежать . В том числе и на ваши удары.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-271',
    code: 'smertonosnye-tryuki-2',
    type: 'ability',
    name: 'Смертоносные трюки',
    description: 'Пока вы имеете минимум две свободные руки, бонус от навыка Боевая акробатика увеличен вдвое.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-272',
    code: 'polnoe-otstuplenie',
    type: 'ability',
    name: 'Полное отступление',
    description: 'Вы можете совершать отступление и падение одновременно.',
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
      parent_ability_code: 'boevaya-akrobatika',
    },
    keywordIds: [13, 61],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-273',
    code: 'udar-nogami',
    type: 'ability',
    name: 'Удар ногами',
    description:
      'Вы можете бить ногами без обычного ограничения по их количеству, доплатив 1ОД. Однако, в таком случае, вы получите дополнительно 2 неустойчивости на всё время ударов.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-274',
    code: 'soblyusti-balans',
    type: 'ability',
    name: 'Соблюсти баланс',
    description:
      'Если вы должны совершить проверку, для которой неустойчивость даёт помехи, вы можете совершить проверку на неустойчивость перед этим с увеличенной на размер сложностью. В случае успеха, неустойчивость больше не будет давать помех для этой проверки, но в случае провала вы падаете.',
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
      parent_ability_code: 'akrobatika',
    },
    keywordIds: [13, 61],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-275',
    code: 'kontrol-balansa',
    type: 'ability',
    name: 'Контроль баланса',
    description: 'Вы получаете +[ х / 2] к Ловкости от техники для проверок против неустойчивости.',
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
      parent_ability_code: 'akrobatika',
    },
    keywordIds: [13, 61],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-276',
    code: 'pryzhki-s-vysoty',
    type: 'ability',
    name: 'Прыжки с высоты',
    description:
      'При приземлении на ноги вы можете перебросить брошенные для определения повреждений от падения кубики, чей результат вас не устраивает.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-277',
    code: 'razdelka-tush',
    type: 'ability',
    name: 'Разделка туш',
    description: '',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-278',
    code: 'vladenie-muzykalnym-instrumentom',
    type: 'ability',
    name: 'Владение музыкальным инструментом ( x из 3, инструмент )',
    description:
      'Инструментом может выступать голос.\nВы получаете характеристику Музицирование на инструменте , равную 3.\nВы получаете +[ х - 1] к Музицированию на инструменте от владения инструментом .',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-279',
    code: 'muzitsirovanie',
    type: 'ability',
    name: 'Музицирование( x из 3)',
    description: 'Вы получаете + х к Музицированию.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-280',
    code: 'trenirovka-melkoy-motoriki',
    type: 'ability',
    name: 'Тренировка мелкой моторики( х из 3)',
    description:
      'Эта черта отображает то, насколько хорошо у вас развита мелкая моторика. Этот навык может понадобиться, например, в хирургии или для краж. Обычно базовая Мелкая моторика равна 3. Однако, некоторые черты могут это изменить. Так у орков она существенно хуже от рождения.\n+ х к Мелкой моторике .',
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
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [11, 62],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-281',
    code: 'vzlom',
    type: 'ability',
    name: 'Взлом',
    description:
      'Вы теперь умеете взламывать обыкновенные замки, используя предназначенные для этого инструменты. Обычно это отмычки. Каждый замок имеет определённую Сложность и Надёжность и может иметь дополнительные эффекты, влияющие на взлом. Сложность - это сложность проверок для взлома замка. Для простейшего замка она равна 1, однако сложность наиболее распространённых простых замков равна 3. Надёжность - это суммарное количество РУ успешных проверок, необходимого для взлома замка. Минимальная надёжность равна 1, однако Надёжность наиболее распространённых простых замков равна 5. Для взлома замка, используя подходящие инструменты для взлома, совершите действие со стоимостью 5ОД - Взлом. В её рамках совершите проверку на взлом: Мелкая моторика со сложностью Сложность взлома замка . В случае успеха вы получаете [ РУ проверки ] прогресса взлома. Если вы накопили Надёжность замка или больше прогресса взлома, то вы его успешно взломали. В случае провала вы теряете [ -РУ проверки ] прогресса взлома. Если при провале вы не выбросили хотя бы один маленький успех, то вы ломаете свой инструмент для взлома и прекращаете эту попытку взлома. По окончании действия взлом вы можете его Продолжить как процесс, стоимостью 5ОД или прекратить попытку взлома. При прекращении Взлома, вы теряете весь прогресс взлома.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-282',
    code: 'vnimanie-k-detalyam',
    type: 'ability',
    name: 'Внимание к деталям',
    description:
      'Вы получаете +1 к проверке на взлом от восприятия за каждый размер Восприятия выше среднего, вплоть до +3.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-283',
    code: 'opyt-vzloma',
    type: 'ability',
    name: 'Опыт взлома( х из 5)',
    description: 'Вы получаете + х к Мелкой моторике от мастерства для проверок на Взлом.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-284',
    code: 'karmannye-krazhi',
    type: 'ability',
    name: 'Карманные кражи( х из 5)',
    description: 'Вы получаете + х к Мелкой моторике от мастерства для проверок на карманные кражи.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-285',
    code: 'lovkost-ruk',
    type: 'ability',
    name: 'Ловкость рук',
    description:
      'Вы получаете +1 к проверке на карманную кражу от ловкости за каждый размер Ловкости выше среднего, вплоть до +3.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-286',
    code: 'razrez',
    type: 'ability',
    name: 'Разрез',
    description:
      'Вы научились скрытно подрезать вещи прямо на людях, чтобы незаметно вытащить их содержимое. Для этого вы используете режущее оружие. В зависимости от того, насколько ваше оружие скрытно, мастер может увеличить сложность проверки. Помимо этого, если Пробитие оружия без учёта силы не превышает защиту разрезаемого материала, сложность проверки увеличивается на 1. Если оно меньше - увеличивается на столько, на сколько оно меньше. Если защита превышает урон, вы не можете разрезать предмет.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-287',
    code: 'makiyazh',
    type: 'ability',
    name: 'Макияж',
    description:
      'Этот навык позволяет вам вносить корректировки во внешность с помощью косметических материалов, которые могут улучшить цели [Внешность] на 1, вплоть до 1 или ухудшить на произвольное значение, вплоть до -2. Время, которое необходимо потратить на макияж и время, в течении которого он будет оказывать эффект, зависит от используемых материалов. Важно отметить, что понятия о красоте у разных существ отличается, и вы можете сделать цель более красивой только для тех существ, чьи понятия о красоте вы понимаете.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-288',
    code: 'professionalnyy-makiyazh',
    type: 'ability',
    name: 'Профессиональный макияж',
    description:
      'Это улучшение позволяет вам улучшать внешность цели на 2, если она меньше 0. В таком случае время наложения макияжа увеличивается в два раза.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-289',
    code: 'masterstvo-makiyazha',
    type: 'ability',
    name: 'Мастерство макияжа',
    description:
      'Это улучшение позволяет вам улучшать внешность цели вплоть до 2, увеличив время наложения макияжа в 5 раз.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-290',
    code: 'nanesenie-grima',
    type: 'ability',
    name: 'Нанесение грима( х из 3)',
    description:
      'Чтобы нанести проверку на нанесение грима, потратьте комплект для наложения грима и пройдите проверку на нанесение грима: [Качество набора для грима * х ] со сложностью 1, и преимуществом за каждые 6 проворства и восприятия. РУ проверки - это качество получившегося грима. Если не приглядываться, то грим не заметен для существ с восприятием равным или меньше [Качество грима * 2]. Если существо приглядывается, то оно должно потратить 3 секунды и пройти проверку со сложностью, равной РУ вашей проверки. Время накладывания грима зависит от используемых инструментов для грима и уменьшается в х раз. Каждый комплект для грима имеет ограниченные варианты, в кого позволяет замаскироваться. При этом, если у вас не части комплекта для грима, мастер может позволить вам его наложить, увеличив сложность проверки. Если вы хотите загримироваться под конкретного человека, то сложность проверки увеличивается на 1.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-291',
    code: 'razvitie-obscheniya',
    type: 'ability',
    name: 'Развитие общения',
    description:
      'Изучение методов развития общения развивает ваше Красноречие.\nВы получаете +1 к Красноречию от методов развития общения, если владеете минимум двумя методами развития общения со Стоимостью 1 или больше.\nБонус увеличивается до 2, если помимо этого вы владеете минимум двумя методами развития общения со Стоимостью 2 или больше; до 3 - если помимо этого двумя со стоимостью 3 или больше.',
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
              source_code: 'development',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-292',
    code: 'krasnorechie',
    type: 'ability',
    name: 'Тренировка Красноречия',
    description: 'Вы получаете +{уровень навыка} к Красноречию от тренировки.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-293',
    code: 'manera-obscheniya',
    type: 'ability',
    name: 'Манера общения',
    description:
      'Вам свойственна манера общения, которая предоставляет вам преимущество от манеры для одного из видов проверок общения: на запугивание, на убеждение, на обман, на обольщение или на торговлю.',
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
    keywordIds: [13, 58, 63],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-294',
    code: 'masterstvo-torga',
    type: 'ability',
    name: 'Мастерство торга( х из 2)',
    description:
      '+ х к Мастерству общения при проверках на торговлю.\n+1 к Мастерству общения от техники при проверках на торговлю за каждый размер Интеллекта или Внимательности выше среднего, вплоть до максимального бонуса +3.\nЭтот навык не даёт вам знания о ценности тех или иных монет, а также стоимости товаров. Для этого существует навык “Торговля”.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-295',
    code: 'opytnyy-torgovets',
    type: 'ability',
    name: 'Опытный торговец',
    description: '',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-296',
    code: 'pronitsatelnyy-torgovets',
    type: 'ability',
    name: 'Проницательный торговец',
    description:
      'Даже не зная стоимости цели торга, вы можете пытаться торговаться как ни в чём ни бывало. Вместо обычных штрафов (повышения на два размера сложности) вы получаете 3 помехи на проверку.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-297',
    code: 'poverkhnostnaya-otsenka',
    type: 'ability',
    name: 'Поверхностная оценка',
    description:
      'Вы всегда можете определить примерную цену товара, если знаете достаточно о самом товаре и о месте продажи. Для последнего можно потратить время на то, чтобы узнать о местных ценах, если вы вдруг оказались в новом для себя месте. В зависимости от трудности ситуации и ваших знаний мастер может назначить вам проверку [Восприятие + Модификаторы для проверок на торговлю] против установленной им сложности.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-298',
    code: 'khvalebnye-rechi',
    type: 'ability',
    name: 'Хвалебные речи',
    description:
      'Если вы лжёте при торговле, то можете вместо того, чтобы выбрать лучший модификатор, добавить половину модификатора обмана сверх модификатора торговли.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-299',
    code: 'pronitsatelnost',
    type: 'ability',
    name: 'Проницательность( х из 2)',
    description:
      '+ х к Мастерству общения при проверках на проницательность: попытках распознать ложь, намерения, настроение собеседника и т.д.\n+1 к Мастерству общения от техники при проверках на проницательность за каждый размер Интеллекта или Внимательности выше среднего, вплоть до максимального бонуса +3.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-300',
    code: 'dobycha-informatsii',
    type: 'ability',
    name: 'Добыча информации',
    description:
      'Вы понимаете, как и что нужно сказать и сделать, чтобы спровоцировать собеседника выдать словом, мимикой или делом свои намерения. Вы можете совершить проверку на общение любым удобным вам способом(убеждение, обман и т.д.), чтобы это сделать. В случае успеха вы получите [РУ проверки] преимуществ от техники для немедленной проверки на проницательность на тему, согласно которой вы провоцировали, вплоть до 3.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-301',
    code: 'kholodnyy-um',
    type: 'ability',
    name: 'Холодный ум',
    description:
      '+1 к Мастерству общения от техники при проверках на проницательность при попытке вас запугать за каждый размер Силы воли выше среднего, вплоть до максимального бонуса +3.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-302',
    code: 'moralnaya-podderzhka',
    type: 'ability',
    name: 'Моральная поддержка',
    description:
      'При длительном общении (от часа) вы можете пройти проверку на оказание психологической помощи, аналогичную проверке в навыке Психологическая помощь. В случае успеха, собеседник получит преимущество от поддержки для проверки на снятие стресса. При использовании навыка Психологическая помощь это улучшение используется без дополнительных проверок.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-303',
    code: 'psikhologicheskaya-pomosch',
    type: 'ability',
    name: 'Психологическая помощь ( х из 2)',
    description:
      'Вы можете оказывать моральную поддержку другим, способным вас понимать существам. Проведите часовой сеанс и пройдите проверку на оказание психологической помощи : Общение со сложностью 1. За каждый размер выше маленького, сложность проверки возрастает на размер. В случае успеха, если это первый сеанс за день, то цель снимает 4 стресса.\n+ х к Общению от знаний для проверок на оказание психологической помощи.\nДлительная терапия : при длительной терапии раз в неделю, после первой недели, вы можете снять не 4, а 5 стресса в случае успеха. А раз в месяц - 6, а не 4 в случае успеха.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-304',
    code: 'prorabotka-problem',
    type: 'ability',
    name: 'Проработка проблем',
    description:
      'В случае успеха проверки на оказание психологической помощи с 2РУ или больше, цель в течении недели или до перехода на следующий уровень стресса иметь преимущество для проверок против стресса от подготовки .',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-305',
    code: 'rabota-nad-soboy',
    type: 'ability',
    name: 'Работа над собой',
    description:
      'Вы можете провести часовой сеанс для себя. Он не может снизить стресс, но может оказать эффект от Проработки проблем .',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-306',
    code: 'rabota-nad-soboy-2',
    type: 'ability',
    name: 'Работа над собой',
    description: 'Увеличьте количество преимуществ от поддержки до РУ проверки, вплоть до 4.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-307',
    code: 'masterstvo-obmana',
    type: 'ability',
    name: 'Мастерство обмана( х из 2)',
    description:
      '+ х к Мастерству общения при попытках обмануть.\n+1 к Мастерству общения от техники при попытках обмануть за каждый размер Интеллекта выше среднего, вплоть до максимального бонуса +3.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-308',
    code: 'bezuprechnyy-drug',
    type: 'ability',
    name: 'Безупречный друг',
    description:
      'Вы легко понимаете людей - их мысли, то что им нравится и т.д., чем можете умело пользоваться. Вы получаете преимущество для бросков на обман, когда пытаетесь втереться в доверие.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-309',
    code: 'zapugivanie',
    type: 'ability',
    name: 'Запугивание( х из 2)',
    description:
      '+ х к Мастерству общения при попытках запугивания.\n+1 к Мастерству общения от техники при попытках запугивания за каждый размер Интеллекта выше среднего, вплоть до максимального бонуса +3.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-310',
    code: 'vnushenie-strakha',
    type: 'ability',
    name: 'Внушение страха',
    description:
      'Когда вы запугиваете обманывая, проведите сначала проверку на запугивание. За каждый РУ этой проверки оппонент получает помеху для проверки вашего высказывания на обман (если её потребует оппонент) .',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-311',
    code: 'obolschenie',
    type: 'ability',
    name: 'Обольщение( х из 2)',
    description:
      '+ х к Мастерству общения при попытках обольщения.\n+1 к Мастерству общения от техники при попытках обольщения за каждый размер Внимательности выше среднего, вплоть до максимального бонуса +3.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-312',
    code: 'lstivye-rechi',
    type: 'ability',
    name: 'Льстивые речи',
    description:
      'Если вы используете это улучшение, то в ы получаете +1 к эффективности проверок на Обольщение. Однако, в случае провала, это вызовет более сильное отторжение у цели.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-313',
    code: 'vedenie-doprosa',
    type: 'ability',
    name: 'Ведение допроса',
    description:
      'Вы обучены ведению допроса, что даёт вам преимущество от мастерства для проверок на убеждение, запугивание и проницательность при допросе.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-314',
    code: 'akterskoe-masterstvo',
    type: 'ability',
    name: 'Актёрское мастерство( х из 3)',
    description: 'Вы получаете + х к Мастерству общения для проверок на Актёрское мастерство.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-315',
    code: 'igra-po-zhizni',
    type: 'ability',
    name: 'Игра по жизни',
    description: 'Вы получаете +1 к Мастерству общения для проверок на Обман и Обольщение.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-316',
    code: 'menyaya-maski',
    type: 'ability',
    name: 'Меняя маски',
    description:
      'Вы получаете преимущество от манеры для проверок общения на запугивание, на убеждение, на обман, на обольщение или на торговлю. Одновременно вы можете иметь только один бонус от манеры . Вы не можете изменить манеру во время общения.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-317',
    code: 'otvlech-vnimanie',
    type: 'ability',
    name: 'Отвлечь внимание',
    description:
      'Выберите действие тех, чьё внимание вы хотите отвлечь и совершите соответствующее контексту действие. Это может быть широкий круг действий. Например вы можете выкрикнуть фразу, чтобы привлечь внимание противника к себе для того, чтобы вашему товарищу было проще нанести удар. Вы не можете использовать этот навык, если не можете как-либо отвлечь внимание.\nСовершите совместную проверку на Обман против Проницательности целей, которые вы хотите отвлечь. Если ваш способ неубедителен - вы получите от одной до трёх помех на усмотрение ведущего.\nКаждый, кого вам удалось отвлечь, получает помеху для всех проверок, пока не потратит 2ОД и не могут отреагировать на это действие. Помеха не распространяется на цель отвлечения.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-318',
    code: 'prikinutsya-mertvym',
    type: 'ability',
    name: 'Прикинуться мёртвым',
    description:
      'Вы падаете ничком. Совершите проверку на Актёрское мастерство со сложностью [Пассивная внимательность целей]. Те, против кого вы прошли проверку считают вас погибшим. Однако при тщательном осмотре можно легко обнаружить то, что вы живы.\nПассивная внимательность равна уменьшенной на размер Внимательности.\nСложность проверки может модифицироваться мастером в зависимости от ситуации. Так окружающие вряд ли поверят в то, что вы внезапно умерли.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-319',
    code: 'blizhniy-boy',
    type: 'ability',
    name: 'Навыки боя',
    description:
      'Этот навык показывает, насколько вы опытны как боец. Уровень навыка зависит от навыков ближнего боя, которыми вы владеете: вы получаете первый уровень навыка, когда ваш опыт ближнего боя достигает 2, второй — при 8 опыте, третий — при 16 опыте.\nВы получаете +{уровень навыка} к Мастерству боя от тренировок.\nОпыт ближнего боя - суммарная стоимость навыков ближнего боя. Обратите внимание, что навыки мастерства владения оружием не относятся к навыкам ближнего боя. Также обратите внимание, что все владеют врождённым естественным оружием. Обычно это руки и ноги.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-320',
    code: 'boevye-refleksy',
    type: 'ability',
    name: 'Боевые рефлексы',
    description: 'Для всех проверок на попадание вы получаете +3 от реакции к Восприятию, вплоть до значения Реакции.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-321',
    code: 'otstuplenie',
    type: 'ability',
    name: 'Отступление',
    description:
      'Это - реакция на удар по вам. Вы можете использовать её как сопутствующее действие любой другой реакции на удар по вам. Помните, что вы не можете совершать одновременно два действия движения и вы не можете одновременно падать и отступать. Помните, что реакция не может превышать требовать больше ОД, чем действие которое на которое она совершается. Помните то, что сопутствующее действие не может превышать основное в затратах ОД.\nСделайте шаг по направлению от совершённому по вам удару. Если этот шаг должен будет вас вывести за пределы радиуса действия оружия, то вы получите +1 к эффективности для проверки на попадание. Если вы оказались в два раза раза дальше - +2; в 4 раза - +3; в 8 - +4 и т.д. Перемещение происходит после того, как атака по вам будет закончена. После перемещения вы получаете эффект “Неустойчивость”.\nЕсли атака была процессом, то вы перемещаетесь после Части и следующая Часть начнётся уже после вашего перемещения, однако атакующий может согласно правилу одновременных действий совершить действие движения, которое занимает не больше ОД, чем сама Часть. За это он получит помеху для проверок, согласно правилу одновременных действий.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-322',
    code: 'zaschita-znaniem',
    type: 'ability',
    name: 'Защита знанием',
    description: 'Вы получаете преимущество для проверок на попадание атак по вам, которые вам известны.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-323',
    code: 'adaptatsiya-k-protivniku',
    type: 'ability',
    name: 'Адаптация к противнику',
    description:
      'Когда по вам противник совершает удар такой же атакой, которую он уже совершал, или защищается той же реакцией, которую уже использовал, пройдите проверку на Интеллект со сложностью, равной мастерству боя противника. При этом каждый размер восприятия выше среднего даст вам преимущество для этой проверки. В случае успеха отметьте данную атаку или реакцию на атаку данного противника как ту, которой вы нашли противодействие. Вы получаете преимущество для любых проверок на попадание против атак, которым нашли противодействие. Противник, совершающий реакцию на вашу атаку, которой вы нашли противодействие, получает помеху. Этот бонус сохраняется до конца боя.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-324',
    code: 'podavlenie-ponimaniem',
    type: 'ability',
    name: 'Подавление пониманием',
    description:
      'Когда против вас совершается удар атаки или реакция на удар, которой вы уже нашли противодействие, вы получаете вместо одного преимущества по одному преимуществу за каждый размер вашего Интеллекта выше среднего.',
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
      parent_ability_code: 'adaptatsiya-k-protivniku',
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-325',
    code: 'podderzhka',
    type: 'ability',
    name: 'Поддержка',
    description:
      'Вы можете при атаке ближнего боя, её части или реакции на удар сказать, что используете свободную руку для поддержки. В таком случае вы получите преимущество от поддержки для всех проверок на попадание этой атаки ближнего боя, её части или реакции на удар соответственно.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-326',
    code: 'fekhtovanie',
    type: 'ability',
    name: 'Фехтование',
    description:
      'Вы можете применить эффект этого навыка п ри совершении удара или реакции на удар, если вы используете только фехтовальное оружие и у вас минимум половина рук свободна. В таком случае для этого удара вы не получаете никаких бонусов (в т.ч. урона и пробития) от силы кроме описанных в этом навыке, если они у вас были. Вы получаете одно преимущество, если имеете большое восприятие; ещё одно, если имеете силу на размер, превышающую минимальную для каждого используемого оружия.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-327',
    code: 'videnie-boya',
    type: 'ability',
    name: 'Видение боя',
    description:
      'Если вы не застигнуты врасплох, то во считается, что вы видите атаки по вам со спины, что позволяет реагировать на них.',
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
          ],
        },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 56, 64],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-328',
    code: 'bezoruzhnyy-boy',
    type: 'ability',
    name: 'Безоружный бой',
    description:
      'Вы получаете +1 к эффективности проверок на уклонение при попытках избежать удар, если у вас есть минимум две свободные руки.',
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
      parent_ability_code: null,
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-329',
    code: 'borba',
    type: 'ability',
    name: 'Борьба( х из 3)',
    description: 'Вы получаете + х к Силе и Мастерству боя для всех проверок на захват и толчок.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-330',
    code: 'perekhvat',
    type: 'ability',
    name: 'Перехват',
    description:
      'Когда против вас делают захват, вы можете в ответ вместо обычной реакции использовать “Перехват” за 3ОД. В таком случае будет проверка аналогичная “Избегать(сила)”, но, в случае вашего успеха с минимум 2РУ, не только избегаете захвата, но и сами берёте её в захват, после чего тратите ещё 1ОД.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-331',
    code: 'kontrudar',
    type: 'ability',
    name: 'Контрудар',
    description:
      'Когда цель, которая удерживает вас в захвате наносит вам удар, вы можете выбрать реакцию “Контрудар” за 2ОД. В таким случае вы используете свободную руку, бросаете [Мастерство боя] кубиков, получая преимущество за каждый размер наименьшего из Силы и Проворства. В остальном считается, что вы избегаете удар. Если вы выиграете эту проверку, то нападающий получит удар от себя своим же оружием с РУ, равным вашему РУ защиты от этого удара.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-332',
    code: 'brosok-protivnikom',
    type: 'ability',
    name: 'Бросок противником',
    description:
      'Перед Захватом вы можете сказать, что собираетесь сделать бросок. В таком случае, если вы получите меньше 2РУ размера Силы противника за захват, то захват проваливается. В противном случае вы берёте цель в захват, тратите дополнительно 1ОД, после чего перемещаете цель в любую соседнюю клетку, перестаёте удерживать в захвате, она падает и получает d3 дробящего урона своего размера. Бросок можно использовать в том числе, если цель уже в захвате - для этого просто проведите проверку на захват, продолжая удерживать цель и объявите, что собираетесь сделать бросок.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-333',
    code: 'kontrmery',
    type: 'ability',
    name: 'Контрмеры',
    description:
      'Когда против вас совершается действие “Захват”, вы можете использовать реакцию “Бросок” за 3ОД. Она аналогична “Избегать(сила)”, однако если вы достигните успеха в проверке минимум с 2РУ размера силы противника, то вы берёте её в захват, тратите дополнительно 1ОД, после чего перемещаете цель в любую соседнюю клетку, перестаёте удерживать в захвате, она падает и получает d3 дробящего урона своего размера.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-334',
    code: 'boy-s-oruzhiem-v-neskolkikh-rukakh',
    type: 'ability',
    name: 'Бой с оружием в нескольких руках',
    description:
      'Если всё оружие, которое вы выбрали для удара одинаковое, то вы получаете на одну помеху меньше за использование дополнительного оружия.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-335',
    code: 'balans',
    type: 'ability',
    name: 'Баланс',
    description:
      'Если всем оружием, которое вы выбрали для удара у вас есть минимум 4 среднее владение, то вы получаете на одну помеху меньше за использование дополнительного оружия.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-336',
    code: 'podgotovka',
    type: 'ability',
    name: 'Подготовка',
    description:
      'При объявлении атаки ближнего боя вы можете заявить, что используете этот навык. В таком случае все ваши удары этой атаки получают помеху, однако, если следующее ваше действие - это атака ближнего боя, то её первый ваш удар получит преимущество для проверки на попадания от подготовки .',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-337',
    code: 'bystryy-udar',
    type: 'ability',
    name: 'Быстрый удар',
    description:
      'Совершите один удар с уменьшенной на 1 точностью. Если это действие заняло не больше 2ОД, то бонус к Мастерству боя цели удара от Ловкости будет снижен на 3, вплоть до 0.\nЕсли следующее действие - атака, то она будет стоить на 1ОД больше.',
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
    keywordIds: [13, 14, 64, 71],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-338',
    code: 'perekhodnyy-udar',
    type: 'ability',
    name: 'Переходный удар',
    description:
      'Совершите один удар с уменьшенной на 1 силой удара от обстоятельств .\nЕсли следующее действие - атака, то она будет стоить на 1ОД меньше.',
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
          amount: 3,
          label: 'Действие',
        },
      ],
    },
    keywordIds: [13, 14, 64, 71],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-339',
    code: 'seriya-udarov',
    type: 'ability',
    name: 'Серия ударов',
    description:
      'По окончании процесса, пока вы не потратите 1ОД, вы будете иметь помеху для всех проверок на попадание от обстоятельств .',
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
          mode: 'chain',
          max_shift: 1,
          direction: 'both',
        },
        failure: 'end_action',
        steps: [
          {
            code: 'part-1',
            name: 'Первая часть',
            description: 'Совершите один удар. Промах заканчивает процесс.',
            costs: [{ resource_code: 'action-points', amount: 3 }],
          },
          {
            code: 'part-2',
            name: 'Часть',
            description:
              'Вы получаете 1 внутреннее повреждение от перенапряжения. Размер повреждения равен размеру вашей силы. Ваши повреждения не сбрасываются, пока вы выполняете этот процесс. Совершите один удар. Если он промахнулся — процесс заканчивается.',
            costs: [{ resource_code: 'action-points', amount: 2 }],
          },
        ],
      },
    },
    keywordIds: [13, 14, 15, 64, 71],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-340',
    code: 'kombinatsiya-udarov',
    type: 'ability',
    name: 'Комбинация ударов',
    description: '',
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
        start_step_code: 'prep',
        transition: {
          mode: 'chain',
          max_shift: 1,
          direction: 'both',
        },
        failure: 'end_action',
        steps: [
          {
            code: 'prep',
            name: 'Подготовительная часть',
            description:
              'Совершите удар с уменьшенной на размер силой и помехой на попадание от обстоятельств . Получите 1 Комбо до окончания процесса. В случае промаха процесс завершается.',
            costs: [{ resource_code: 'action-points', amount: 2 }],
          },
          {
            code: 'finish',
            name: 'Часть окончания',
            description:
              'Эта часть может быть использована только если вы использовали своим предыдущим действием Подготовительную часть этого процесса. Совершите один удар с увеличенной на [Комбо] точностью от обстоятельств , вплоть до +3. После чего процесс оканчивается. Если вы попали этим ударом и ваше следующее действие - атака ближнего боя, она будет стоить на 1ОД меньше, вплоть до 2ОД.',
            costs: [{ resource_code: 'action-points', amount: 2 }],
          },
        ],
      },
    },
    keywordIds: [13, 14, 15, 64, 71],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-341',
    code: 'raskrytie',
    type: 'ability',
    name: 'Раскрытие',
    description:
      'Вы можете вместо Части окончания совершить любую атаку. Её первый удар или бросок получит +[Комбо] к точности от обстоятельств . После чего процесс оканчивается.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-342',
    code: 'neotvratimaya-kombinatsiya',
    type: 'ability',
    name: 'Неотвратимая комбинация',
    description:
      'Вы можете заменить бонус к точности Части окончания или её замены полностью или частично. Каждая обмененная 1 точности даёт одно преимущество на попадание от обстоятельств и даёт цели удара одну помеху на получение увечий от обстоятельств .',
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
      parent_ability_code: 'kombinatsiya-udarov',
    },
    keywordIds: [13, 64],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-343',
    code: 'oboerukaya-ataka',
    type: 'ability',
    name: 'Обоерукая атака',
    description:
      'Это действие не может затратить меньше 3ОД.\nСовершите два удара с одной помехой от обстоятельств , не используя во втором ударе оружие, используемое в первом ударе. Т.е. если вы нанесли удар одной рукой, второй удар должен быть нанесён другой рукой или любым другим оружием на ваш выбор.\nЕсли ваше следующее действие - атака по той же цели, её первый удар получит +1 к точности.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-344',
    code: 'sinkhronnaya-ataka',
    type: 'ability',
    name: 'Синхронная атака',
    description:
      'Вы совершаете все удары синхронно по разным местам. Из-за этого вы получаете одну помеху от обстоятельств , а цель вынуждена защищаться одновременно от двух ударов. Это не позволит ей использовать оружие, чтобы блокировать сразу два удара. И она получит помеху на защиту за каждый удар, от которого защищается после первого.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-345',
    code: 'sdvoennyy-udar',
    type: 'ability',
    name: 'Сдвоенный удар',
    description:
      'Совершите один удар с двумя помехами на попадания от обстоятельств , используя два оружия . В случае успеха вы попадаете каждым используемым для атаки оружием.\nДля проверки на попадание вы используете наименьшую точность используемых оружий; применяете помехи, которые есть хотя-бы для попадания одним оружием; и используете преимущества, которые распространяются только на каждое используемое оружие.\nВы получаете на одну помеху от обстоятельств больше, если используете голову для удара. Например, для удара рогами.',
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
              ability_code: 'double-strike',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-346',
    code: 'mnozhestvo-ruk',
    type: 'ability',
    name: 'Множество рук',
    description:
      'Вы можете использовать больше двух оружий для удара. В таком случае вместо двух помех, вы получите столько помех от обстоятельств , сколько используете оружия для атаки.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-347',
    code: 'mnozhestvo-udarov',
    type: 'ability',
    name: 'Множество ударов',
    description: '',
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
              ability_code: 'double-strike',
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
            costs: [{ resource_code: 'action-points', amount: 3 }],
          },
          {
            code: 'part-2',
            name: 'Часть',
            description:
              'Совершите удар оружием с [Количество использований оружия в процессе] помех от обстоятельств . Т.е. если вы наносили удар правой рукой дважды - вы получите две помехи.',
            costs: [{ resource_code: 'action-points', amount: 2 }],
          },
        ],
      },
    },
    keywordIds: [13, 14, 15, 64, 71],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-349',
    code: 'razmashistyy-udar',
    type: 'ability',
    name: 'Размашистый удар',
    description:
      'Совершите удар с увеличенной на 2 Силой и помехой на попадание от обстоятельств . Помеха остается для всех проверок на попадание * до тех пор, пока вы не потратите 2ОД. *Включая проверки на попадание по вам.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-350',
    code: 'yarostnyy-ryvok',
    type: 'ability',
    name: 'Яростный рывок',
    description:
      'Вы можете после этого удара пройти проверку на Силу воли со сложностью 3, в случае успеха вы получаете [Сила удара]↓ внутреннего дробящего урона и не получаете помеху после удара.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-351',
    code: 'udvoennaya-mosch',
    type: 'ability',
    name: 'Удвоенная мощь',
    description:
      'Если при рубящем или дробящем ударе действием Силовой удар вы держите оружие в двух или более руках, то ваша Сила для этого удара увеличена ещё на 1.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-352',
    code: 'tolkayuschiy-udar',
    type: 'ability',
    name: 'Толкающий удар',
    description:
      'Совершите Толчок по цели своим оружием, выбрав рубящий или дробящий урон. РУ вашей проверки на толчок снижена на 1, однако такой толчок наносит урон как обычный удар: [Урон * РУ].',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-353',
    code: 'silovoy-udar',
    type: 'ability',
    name: 'Силовой удар',
    description: 'Совершите один удар по цели с +[РУ атаки]↓ к силе удара от обстоятельств, вплоть до +3.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-354',
    code: 'shirokiy-udar',
    type: 'ability',
    name: 'Широкий удар',
    description:
      'Совершите рубящий, режущий или дробящий удар по выбранной вами цели и двум стоящим рядом с ней целям с помехой на попадание от обстоятельств за каждую цель после первой.',
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
    },
    keywordIds: [13, 14, 64, 71],
    mechanicId: null,
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-355',
    code: 'tochnyy-udar',
    type: 'ability',
    name: 'Точный удар',
    description: 'Совершите удар с увеличенной на 1 Точностью от обстоятельств .',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-356',
    code: 'masterstvo-v-tochnosti',
    type: 'ability',
    name: 'Мастерство в точности',
    description:
      'Если вы имеете понимание (2) во владении оружием, то вы можете уменьшить его для любого удара на 1, чтобы получить +2 к Точности оружия от обстоятельств вместо бонуса +1.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-357',
    code: 'napravlennyy-udar',
    type: 'ability',
    name: 'Направленный удар',
    description:
      'Совершите удар с уменьшенной на 1 силой. Бонус к Мастерству боя цели от Восприятия для этого удара будет снижен на 3, вплоть до 0.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-358',
    code: 'protivodeystvuyuschiy-udar',
    type: 'ability',
    name: 'Противодействующий удар',
    description:
      'Объявите реакцию (блокирование, уклонение) , которой вы будете противодействовать и совершите удар. Если цель удара совершила эту реакцию в ответ на удар - вы получаете преимущество. Если она не проигнорировала удар и совершила другую реакцию - помеху.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-359',
    code: 'udar-v-sochlenenie',
    type: 'ability',
    name: 'Удар в сочленение',
    description:
      'Совершите колющий, рубящий или режущий удар с помехой от обстоятельств за каждую единицу надёжности доспеха цели. Вы получаете на одну помеху меньше, если используете короткое оружие. Вы получаете на одну помеху меньше, если наносите колющий удар. В случае попадания с минимум 1РУ этот удар игнорирует защиту от доспеха.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-360',
    code: 'smertelnyy-udar',
    type: 'ability',
    name: 'Смертельный удар( x из 2)',
    description:
      'Совершите удар. Каждая единица и шестёрка при вашем броске на попадание для этого удара добавляют и убирают х дополнительных успехов соответственно.\nЕсли удар был колющим, то каждая 6 при броске на увечье добавляет дополнительный провал.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-361',
    code: 'kriticheskiy-udar',
    type: 'ability',
    name: 'Критический удар',
    description:
      'Эту атаку можно применить только сразу после атаки, каждый удар которой нанёс повреждения.\nВыберите одну из целей ударов прошлой атаки и совершите по ней удар. Каждая 4 и 5 будет считаться за 6, а каждая 2 будет считаться за 1.\nЕсли атака нанесла истощение, то если ваше следующее действие - атака ближнего боя, то она получит эффект Критического удара. Включая возможность распространить эффект на следующую атаку',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-362',
    code: 'vypad',
    type: 'ability',
    name: 'Выпад',
    description: 'Совершите удар с дистанцией действия оружия увеличенной на полшага. Обычно - на ½ ипари.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-363',
    code: 'vyverennyy-udar',
    type: 'ability',
    name: 'Выверенный удар',
    description:
      'Если предыдущая действие оказывает эффект на следующую за ним атаку, то примените его дважды * . *Так двойной удар даёт +1 к точности для следующей атаки; для выверенного удара это будет +2.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-364',
    code: 'riskovannyy-udar',
    type: 'ability',
    name: 'Рискованный удар',
    description:
      'Совершите атаку из оз одного удара по выбранной вами цели с вдвое меньшей точностью. В случае успеха удара вы получите в два раза больше РУ атаки.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-365',
    code: 'raschetlivaya-ataka',
    type: 'ability',
    name: 'Расчётливая атака',
    description: 'Вы можете потратить жетон концентрации, чтобы получить +1 к точности удара от концентрации .',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-366',
    code: 'obezoruzhivanie',
    type: 'ability',
    name: 'Обезоруживание',
    description:
      'Когда вы наносите удар, вы можете за 5РУ атаки удара выбить у неё оружие из рук, если только ваша сила не более чем на два размера меньше, чем у цели. *Чтобы поднять оружие требуется 2ОД.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-367',
    code: 'udar-v-padenii',
    type: 'ability',
    name: 'Удар в падении',
    description:
      'Когда вы используете навык “удар в движении”, чтобы сделать удар в падении, вы получаете в два раза меньше помех.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-368',
    code: 'kontrudar-2',
    type: 'ability',
    name: 'Контрудар',
    description: '',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-369',
    code: 'prikrytie',
    type: 'ability',
    name: 'Прикрытие',
    description:
      'Реакция на удар, выстрел или бросок по союзнику, находящемуся в пределах шага от вас. Затраты ОД для этой реакции на 1ОД больше, чем для выбранной реакции прикрытия (обычно блокирования).\nВы присоединяетесь к совместной проверке на попадание на стороне цели удара. Выберите один или несколько ударов, от которых вы прикрываете цель удара. Выберите реакцию прикрытия (обычно блокирование с тратой ещё 2ОД). Вы совершаете проверку на попадание так, будто выбранные цели атаковали вас, но с одной помехой от обстоятельств. При этом вы не можете избегать атаку. В случае успеха вашей проверки целью удара считаетесь вы. В случае провала атакующий может выбрать цель сам.\nЕсли вы прикрываете союзника от каждой атаки, от которой защищается он, и у которого есть этот навык, то вы оба получаете преимущество от обстоятельств для проверок.\nПример: союзника атакуют с помощью атаки за 4ОД. У него есть навык Прикрытие. Вы защищаете его с помощью Блокирования. Это потребует [1+2] = 3ОД. Союзник получит преимущество для своей проверки, а вы — не получите помехи. У врага на кубах вышло 2 успеха. У союзника 1. У вас — 3. Итого удар прошёлся на вас вместо союзника и вы его успешно заблокировали.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-370',
    code: 'perestanovka',
    type: 'ability',
    name: 'Перестановка',
    description:
      'Вы и ваш союзник, которого вы прикрывали, если не получили истощения, можете сделать по окончанию проверки шаг за 1ОД, чтобы поменяться местами. Если у союзника нет этого улучшения, то шаг обойдётся ему в 2ОД.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-371',
    code: 'podderzhka-v-boyu',
    type: 'ability',
    name: 'Поддержка в бою',
    description:
      'Вы можете совершить эту реакцию, когда кто-либо совершает удар по кому-то, в пределах дистанции вашего оружия. Вы помогаете либо атакующему, либо защищающемуся, давая тому преимущество, и ещё одно преимущество за каждый размер вашего мастерства боя выше среднего.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-372',
    code: 'koordinatsiya',
    type: 'ability',
    name: 'Координация',
    description:
      'Если вы помогаете тому, у кого есть это улучшение, то он может проигнорировать первую выпавшую шестёрку на броске.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-373',
    code: 'obmannyy-manevr',
    type: 'ability',
    name: 'Обманный манёвр',
    description:
      'Вы можете использовать этот навык после того, как были объявлены все защиты против вашего удара, но до проверок. Цель навыка — все, кто выбрал защиту против вашего удара.\nВы и цели навыка совершаете совместную проверку с признаком Обман, вместо обычной проверки на попадание (удар не наносится). Вы для проверки можете использовать либо своё Мастерство боя, либо Обман. Цель может использовать для проверки либо своё Мастерство боя, либо Проницательность. Каждая провалившая проверку цель теряет возможность сделать действие после этой вашей атаки и получает две помехи для проверки на защиту против первого вашего удара по ней в этом ходу.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-374',
    code: 'sovmestnaya-ataka',
    type: 'ability',
    name: 'Совместная атака',
    description:
      'Вы можете присоединиться почти к любой атаке - цели будет сложнее сопротивляться нескольким ударам, броскам или выстрелам одновременно.\nКогда кто-либо совершает атаку, вы можете отреагировать. В таком случае эта атака считается Первичной атакой. Совершите атаку, которая стоит не больше ОД, нежели первичная атака. Эта атака получит признак Вторичной и к ней нельзя будет присоединиться посредством этого навыка.\nПри каждом ударе, броске или выстреле первичной атаки вы можете совершить один удар, бросок или выстрел вторичной атаки по той же цели.\nЦель выбирает реакцию на каждую атаку, которую не собирается игнорировать и тратит ОД по наибольшей стоимости среди выбранных реакций. Для одинаковых реакций цель бросает только одну проверку, к которой применяются все штрафы каждой проверки для этой реакции. Помимо этого применяется правило об одновременных действиях - цель получит столько помех от состояния , сколько действий (в т.ч. реакций) совершает.\nПример: цель получает удар спереди и с фланга. Блокировать нечем, поэтому она решает увернуться от от ударов. Т.к. среди них есть удар с фланга, она получает 2 помехи от обстоятельств. Т.к. совершается две реакции уклонения, она получает 2 помехи от состояния. Итого уклонение за 1ОД с 4 помехами.\nОдновременно бить по цели и не мешать друг другу - не просто. Вы получаете помеху на попадание от обстоятельств , если первичный атакующий не владеет этим навыком. Вы получаете помеху на попадание от обстоятельств . если атакуете не той же атакой, что и первичный атакующий. И вы получаете две помехи от обстоятельств , если соответствуете обоим условиям.',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-375',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-376',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-377',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-378',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-379',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-380',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-381',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-382',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-383',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-384',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-385',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-386',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-387',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-388',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-389',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-390',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-391',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-392',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  {
    id: 'rule-393',
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
    createdAt: '2026-08-09T10:00:00Z',
  },
  ...mockWeaponSkillsImport,
];
