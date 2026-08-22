/**
 * Оружейные навыки из docs/rule/battle/weapon-skills.md
 * ~96 записей по 30 тегам оружия.
 */
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

export const mockWeaponSkillsImport: Rule[] = [
  {
    id: 'rule-700',
    code: 'kogti_tochnost_v_gibkosti',
    type: 'ability',
    name: 'Точность в гибкости',
    description:
      'Если у вас свободно минимум две руки с когтями, и предыдущее действие не было атакой, вы можете совершить любую атаку боя с +1 к точности от обстоятельств для её первого удара.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'claws', min_level: 1 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-701',
    code: 'kogti_tekhnichniy_napor',
    type: 'ability',
    name: 'Техничный напор',
    description: 'процессов атак ближнего боя получают на 1 помеху от обстоятельств меньше.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'claws', min_level: 1 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-702',
    code: 'kogti_nemedlennaya_krovopoterya',
    type: 'ability',
    name: 'Немедленная кровопотеря',
    description:
      'При нанесении раны попаданием с минимом 2РУ, цель немедленно получает урон от кровотечения от этой раны.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'claws', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-703',
    code: 'kogti_smertelnie_rani',
    type: 'ability',
    name: 'Смертельные раны',
    description: 'Проверка на получение увечий от ран, нанесённых вами , получает помеху от обстоятельств',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'claws', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-704',
    code: 'kogti_razrivayushchiy_udar',
    type: 'ability',
    name: 'Разрывающий удар',
    description:
      'При попадании Сдвоенным ударом посредством , вы можете потратить дополнительно 1ОД. В таком случае вы увеличите наносимые повреждения на размер от обстоятельств',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'claws', min_level: 1 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-705',
    code: 'kogti_bolshie_rani',
    type: 'ability',
    name: 'Большие раны',
    description:
      'При попадании по существу большего размера, нежели можете потратить 1ОД, чтобы увеличить размер ранений до размера существа, но не больше чем на один размер.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'claws', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-706',
    code: 'posokh_dalnie_udari',
    type: 'ability',
    name: 'Дальние удары',
    description: 'Дистанция посохов для вас увеличена вдвое.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'staff', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 116],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-707',
    code: 'posokh_krugovaya_oborona',
    type: 'ability',
    name: 'Круговая оборона',
    description:
      'Вы можете блокировать посохом удары со всех сторон и наносить удары в любую сторону не поворачиваясь. При этом навык вам не даёт возможности реагировать на те действия о которых вы не знаете. Например - на удары по вам со спины.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'staff', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 116],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-708',
    code: 'posokh_otvetniy_udar',
    type: 'ability',
    name: 'Ответный удар',
    description:
      'Когда вы успешно блокируете удар посохом, вы можете после успешного блокирования совершить удар за 1ОД. При 3РУ защиты и более вы можете увеличить силу удара до силы заблокированного удара, если она больше.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'staff', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 116],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-709',
    code: 'posokh_grad_udarov_posokhom',
    type: 'ability',
    name: 'Град ударов посохом',
    description:
      'Любая атака посохом, совершённая сразу после другой атаки посохом требует на 1ОД меньше обычного, вплоть до 2ОД.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'staff', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 116],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-710',
    code: 'posokh_dvoynoy_udar',
    type: 'ability',
    name: 'Двойной удар',
    description:
      'Когда вы попадаете посохом, вы можете за 1ОД совершить ещё один удар этим посохом с преимуществом на попадание и уменьшенной на размер силой. К этому удару нельзя применять навыки, которые требуют дополнительных затрат ОД (в том числе этот)',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'staff', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 116],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-711',
    code: 'posokh_masterstvo_blokirovaniya_posokhom',
    type: 'ability',
    name: 'Мастерство блокирования посохом',
    description:
      'Если вы успешно заблокировали удар с минимум 3РУ, то вы не получаете урона так, как если бы избежали его.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [3],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'staff', min_level: 3 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 116],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-712',
    code: 'posokh_masterstvo_zashchiti_posokhom',
    type: 'ability',
    name: 'Мастерство защиты посохом',
    description:
      'Если 6 меньше, чем 1 при проверке на попадание , то 6 не оказывают эффект за правило 6 и 1. Оружие Текко-каги 1 рука',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [3],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'staff', min_level: 3 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 116],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-713',
    code: 'metch_podavlenie_preimushchestvom',
    type: 'ability',
    name: 'Подавление преимуществом',
    description:
      'Перед совместной проверкой на попадание удара (в т.ч. при защите) вы можете заявить, что превращаете любое количество своих преимуществ для этой проверки в помехи для противника.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'sword', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 111, 134, 135, 136],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-714',
    code: 'metch_prikritie_slabostey',
    type: 'ability',
    name: 'Прикрытие слабостей',
    description:
      'При проверке на попадание ударом меча или мечами вы можете вместо броска дополнительного кубика за 1 перебросить один свой кубик.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'sword', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 111, 134, 135, 136],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-715',
    code: 'metch_lozhnie_trudnosti',
    type: 'ability',
    name: 'Ложные трудности(',
    description:
      'При проверке на попадание, в которой используете только ~, вы можете сказать что получаете помех для этой проверки. В таком случае, если эта проверка будет успешной, то ваша следующая проверка на попадание этого действия или следующего сразу за этим действия получит преимуществ, если вы будете использовать для неё только Оружие Кинжал',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'sword', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 111, 134, 135, 136],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-716',
    code: 'metch_perekhodyashchie_ataki',
    type: 'ability',
    name: 'Переходящие атаки',
    description:
      'Это улучшение работает при условии, что вы используете только рыцарские мечи в качестве оружия. Если вы после атаки немедленно начали очередную атаку, и в она будет стоить на 1ОД меньше (вплоть до 2ОД) , если использует мечи, которые могли быть использованы в предыдущей атаки, но не были использованы. Если вы решите использовать не являющееся рыцарским мечом оружие во время одной из таких атак, вы должны будете доплатить 1ОД.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [3],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'sword', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 111, 134, 135, 136],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-717',
    code: 'metch_preimushchestvo_v_zashchite',
    type: 'ability',
    name: 'Преимущество в защите',
    description:
      'Когда вы не поворачиваясь избегаете, блокируете или парируете удар по вам, используя только рыцарский меч, это действие тратит на 1ОД меньше, вплоть до 1ОД. Для изучения и использования требуется иметь навык Выпад',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'sword', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 111, 134, 135, 136],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-718',
    code: 'metch_srazhenie_na_rasstoyanii',
    type: 'ability',
    name: 'Сражение на расстоянии',
    description:
      'Когда вы совершаете удар только одним оружием, вы можете совершить этот удар как выпад, доплатив 1ОД или получив помеху на попадание на свой выбор.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'sword', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 111, 134, 135, 136],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-719',
    code: 'metch_preimushchestva_iz_preimushchestv',
    type: 'ability',
    name: 'Преимущества из преимуществ',
    description:
      'Это улучшение работает при условии, что вы используете только рыцарские мечи в качестве оружия. Если вы попали ударом меча, то для следующего удара (который будет в этом же действии, либо в следующем непосредственно за этим) вы получите [РУ атаки / 2] преимуществ на попадание. Оружие',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [4],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'sword', min_level: 3 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 111, 134, 135, 136],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-720',
    code: 'kopyo_ispolzovanie_vozmozhnostey',
    type: 'ability',
    name: 'Использование возможностей',
    description:
      'За каждый критический провал цели при проверке на защиту от удара этим оружием даёт вам одно преимущество для атаки, которая последует непосредственно после этого вашего действия.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'spear', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 122, 141],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-721',
    code: 'kopyo_podavlyayushchee_preimushchestvo',
    type: 'ability',
    name: 'Подавляющее преимущество',
    description:
      'Если при проверке на попадание у вас больше преимуществ, чем у противника, то вы получаете ещё одно преимущество. Для изучения и использования требуется иметь навык Выпад',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'spear', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 122, 141],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-722',
    code: 'kopyo_podvizhnost_v_boyu',
    type: 'ability',
    name: 'Подвижность в бою',
    description:
      'Когда вы совершаете удар только вы можете совершить этот удар как выпад, получив помеху на попадание.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'spear', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 122, 141],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-723',
    code: 'kopyo_preimushchestvo_ot_sili',
    type: 'ability',
    name: 'Преимущество от силы',
    description: 'Вы получаете преимущество, если ваша сила превышает минимальную силу на два размера.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'spear', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 122, 141],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-724',
    code: 'kopyo_glubokie_rani',
    type: 'ability',
    name: 'Глубокие раны',
    description:
      'Если при ударом ~ вы нанесли рану с силой 5 или больше, вы можете потратить 1ОД - в таком случае цель кинет дополнительный кубик на получение увечья за эту рану(минимум один). - - Метание - -',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'spear', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 122, 141],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-725',
    code: 'kopyo_bistriy_brosok',
    type: 'ability',
    name: 'Быстрый бросок',
    description:
      'Вы можете применять правило “удар с разбега” к броскам копьём, чтобы увеличить силу броска. Оружие Алебарда 1-4 руки 3,5 кг',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'spear', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 122, 141],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-726',
    code: 'knut_shirokoe_primenenie',
    type: 'ability',
    name: 'Широкое применение',
    description:
      'Вы можете эффективно использовать оружие для Акробатических трюков: эффектно сворачивать кнут одним движением (сл. 3↑) , цепляться им за ветви деревьев',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'whip', min_level: 1 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 115],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-727',
    code: 'knut_prityanut',
    type: 'ability',
    name: 'Притянуть',
    description:
      'Вы можете использовать действие Толчок оружием по направлению к себе. Дистанция такого толчка равна дистанции',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'whip', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 115],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-728',
    code: 'knut_gibkost_atak',
    type: 'ability',
    name: 'Гибкость атак',
    description:
      'Вы можете проводить атаки оружием по любому направлению. От того, что цель атаки находится во фланге вы не получаете помех.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'whip', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 115],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-729',
    code: 'knut_neprerivniy_grad_udarov',
    type: 'ability',
    name: 'Непрерывный град ударов',
    description:
      'После атаки, в каждом ударе которой вы использовали оружие вы можете немедленно совершить ещё одну атаку. Если в первом её ударе будет использовано оружие , то она потребует на 1ОД меньше, вплоть до 2ОД.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'whip', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 115],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-730',
    code: 'knut_sbivanie_s_nog',
    type: 'ability',
    name: 'Сбивание с ног',
    description:
      'Вы можете использовать действие Обезоруживание оружием Клинки Для изучения и использования необходим навык “Быстрый удар”',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'whip', min_level: 3 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 115],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-731',
    code: 'prostoi-topor_mnozhestvennie_udari',
    type: 'ability',
    name: 'Множественные удары',
    description:
      'Когда вы используете в качестве дополнительного оружия , вы можете вместо доплаты 1ОД получать одну помеху для броска на попадание. Оружие Полуторный меч 1-2 руки',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'simple-axe', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 139],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-732',
    code: 'prostoi-topor_kontrol_sili',
    type: 'ability',
    name: 'Контроль силы',
    description:
      'Вы можете любую атаку (кроме Силового удара) ближнего боя, состоящую из одного удара, провести как силовую. Она получит эффект силового удара.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'simple-axe', min_level: 1 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 139],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-733',
    code: 'prostoi-topor_kraynosti_v_borbe',
    type: 'ability',
    name: 'Крайности в борьбе',
    description:
      'Вы можете перед нанесением удара сказать, что используете это улучшение. В таком случае каждая 1 будет генерировать дополнительный кубик, а каждая 6 будет убирать ещё один успех.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'simple-axe', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 139],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-734',
    code: 'prostoi-topor_gran_smerti',
    type: 'ability',
    name: 'Грань смерти',
    description:
      'Если при броске вы имеете больше 1, чем провалов, то можете считать 1 как два успеха, а не один. Оружие Секира 1-4 руки Минимальная сила:',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'simple-axe', min_level: 3 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 139],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-735',
    code: 'prostoi-topor_vikhr_smerti',
    type: 'ability',
    name: 'Вихрь смерти',
    description:
      'Вы можете объявить о том, что следующие ваших действий - будут атаки только с секирой. В таком совершите совершите атаку, состоящую только из ударов секирой или секирами. По её окончании вы должны начать очередную атаку, состоящую только из ударов секирой или секирами. Таким образом вы должны совершить атак. В любой момент вы можете прервать серию за 2ОД и 3 энергии. Каждая атака этой серии, за исключением первой, стоит на 1ОД меньше, вплоть до 2ОД. Более того, атаки этой серии получают +1 к силе удара удар серии перед текущим, вплоть до 3.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'simple-axe', min_level: 3 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 139],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-736',
    code: 'ruka_gibkost_napadeniya',
    type: 'ability',
    name: 'Гибкость нападения',
    description:
      'Руки получают свойство “гибкость нападения”. Если при ударе всё используемое оружие имеет это свойство, вы получаете на одно преимущество от проворства больше за каждый размер проворства выше среднего.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [4],
        },
      },
      requirements: [
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'hand-unarmed', min_level: 3 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-737',
    code: 'ruka_ottochennie_udari',
    type: 'ability',
    name: 'Отточенные удары',
    description:
      'Перед совершением атаки ближнего боя, вы можете объявить, что будете в качестве оружия использовать только руки. В таком случае атака будет стоить на 1ОД меньше, вплоть до 2ОД. Если она уже стоила 2ОД, то вместо этого каждый удар атаки получит преимущество на попадание, однако атака прервётся, если увеличатся затраты ОД на неё.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [4],
        },
      },
      requirements: [
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'hand-unarmed', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-738',
    code: 'ruka_mnozhestvennie_udari',
    type: 'ability',
    name: 'Множественные удары',
    description:
      'Первая рука, выбранная в качестве оружия при ударе не учитывается при подсчёте количества помех за использование более чем одного оружия.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'hand-unarmed', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-739',
    code: 'ruka_trenirovka_paltsev',
    type: 'ability',
    name: 'Тренировка пальцев',
    description:
      'Вы можете сказать, что используете это улучшение перед ударом рукой. В таком случае уменьшите урон руки для этого удара на размер, однако, в случае попадания увеличьте РУ атаки на размер. Оружие 1 нога Минимальная сила: Прочность',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'hand-unarmed', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-740',
    code: 'noga_sokhranenie_balansa',
    type: 'ability',
    name: 'Сохранение баланса',
    description:
      'Для получение неустойчивости необходимо использовать не минимум половину ног в качестве оружия, а больше половины.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'foot-unarmed', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-741',
    code: 'noga_otrabotanniy_verkhniy',
    type: 'ability',
    name: 'Отработанный верхний',
    description:
      'Вы получаете +1 к точности ударов ног от удобства Напоминание: бонусы из одного источника не суммируются.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'foot-unarmed', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-742',
    code: 'noga_dvoynoy_udar',
    type: 'ability',
    name: 'Двойной удар',
    description:
      'Когда вы попадаете ногой, вы можете за 1ОД совершить ещё один удар этой ногой с преимуществом на попадание и уменьшенной на размер силой. К этому удару нельзя применять навыки, которые требуют дополнительных затрат ОД (в том числе этот) Оружие 1 рука Минимальная сила:',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'foot-unarmed', min_level: 3 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-743',
    code: 'tekko-kagi_odnovremennie_udari',
    type: 'ability',
    name: 'Одновременные удары',
    description:
      'Когда вы используете только в качестве оружия, вы можете объявить, что используете этот навык. В таком случае каждое выбранное оружие после первого не требует затрат ОД, но получит дополнительную помеху на попадание. Этот удар всегда заканчивает или прерывает атаку.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'tekko-kagi', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-744',
    code: 'tekko-kagi_bistrie_ataki',
    type: 'ability',
    name: 'Быстрые атаки',
    description:
      'Вы можете перед совершением атаки ближнего боя сказать, что во время неё будете использовать только . В таком случае она будет стоить на 1ОД меньше, вплоть до 3ОД.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'tekko-kagi', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-745',
    code: 'tekko-kagi_rubyashcherezhushchie_udari',
    type: 'ability',
    name: 'Рубяще-режущие удары',
    description: 'Увеличение ран вдвое за 3РУ рубящих попаданий не уменьшает урон на размер.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [4],
        },
      },
      requirements: [
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'tekko-kagi', min_level: 3 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-746',
    code: 'tekko-kagi_gibkost_napadeniya',
    type: 'ability',
    name: 'Гибкость нападения',
    description:
      'получают свойство “гибкость нападения”. Если при ударе всё используемое оружие имеет это свойство, вы получаете на одно преимущество от проворства больше за каждый размер проворства выше среднего. Оружие Кусаригама',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [4],
        },
      },
      requirements: [
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'tekko-kagi', min_level: 3 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-747',
    code: 'kinzhal-nozh_odnovremennie_udari',
    type: 'ability',
    name: 'Одновременные удары',
    description:
      'Когда вы используете только в качестве оружия, вы можете объявить, что используете этот навык. В таком случае каждое выбранное оружие после первого не требует затрат ОД, но получит дополнительную помеху на попадание. Этот удар всегда заканчивает или прерывает атаку.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'dagger', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 108, 109, 184],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-748',
    code: 'kinzhal-nozh_ispolzovanie_vozmozhnostey',
    type: 'ability',
    name: 'Использование возможностей',
    description:
      'За каждый критический провал цели при проверке на защиту от удара этим оружием даёт вам одно преимущество для атаки, которая последует непосредственно после этого вашего действия.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'dagger', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 108, 109, 184],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-749',
    code: 'kinzhal-nozh_udacha_iz_masterstva',
    type: 'ability',
    name: 'Удача из мастерства',
    description:
      'Все эффекты от критического успеха при проверках на попадание оружием срабатывают два раза. - - Метание - -',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'dagger', min_level: 3 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 108, 109, 184],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-750',
    code: 'kinzhal-nozh_bistriy_brosok',
    type: 'ability',
    name: 'Быстрый бросок',
    description:
      'При использовании многооборотной техники метания вы можете уменьшить затраты ОД атаки, содержащей бросок, на 1ОД, вплоть до 2ОД. Однако сила такого броска уменьшится на размер. Оружие Фехтовальный меч 1 рука',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'dagger', min_level: 1 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 108, 109, 184],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-751',
    code: 'polutoruiy-mech_udar_za_udarom',
    type: 'ability',
    name: 'Удар за ударом',
    description:
      'Когда вы попадаете не требующим дополнительных трат ОД ударом , нанося его двумя или более руками, вы можете сделать ещё один удар за 1ОД с уменьшенной на размер силой. Этот удар приходится на то же самое место и не требует проверки на попадание. Тем не менее, если цель удара может без траты ОД перебросить свой результат проверки. Если цель блокировала предыдущий удар, то её показатель блокирования уменьшен на половину от урона предыдущего удара. Для использования требуется соответствовать требованиям минимальной силы при обращении с этим оружием одной рукой.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [4],
        },
      },
      requirements: [
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'bastard-sword', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 135],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-752',
    code: 'polutoruiy-mech_tyazhyoloe_fekhtovanie',
    type: 'ability',
    name: 'Тяжёлое фехтование',
    description: 'становится фехтовальным.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'bastard-sword', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 135],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-753',
    code: 'polutoruiy-mech_bezostanovochnaya_rubka',
    type: 'ability',
    name: 'Безостановочная рубка',
    description:
      'Если вы совершили атаку только ~ и не потратили дополнительно ни одного ОД, то можете доплатить 1ОД и начать другую атаку из одного удара с увеличенной на размер силой.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [4],
        },
      },
      requirements: [
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'bastard-sword', min_level: 3 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 135],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-754',
    code: 'polutoruiy-mech_podavlenie_preimushchestvom',
    type: 'ability',
    name: 'Подавление преимуществом',
    description:
      'Если вы используете этот навык, вы лишаетесь всех преимуществ от силы для удара (обычно это может быть дополнительным уроном и/или одним преимуществом) , но можете перебросить столько кубиков проверки на попадание этого удара, сколько у вас было преимуществ. Оружие Двуручный меч',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [4],
        },
      },
      requirements: [
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'bastard-sword', min_level: 3 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 135],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-755',
    code: 'sekira_ubiystvennaya_seriya',
    type: 'ability',
    name: 'Убийственная серия',
    description:
      'Если атака состоит из нескольких ударов, то вы получаете столько преимуществ для попадания ударом секиры, сколько совершили попаданий секирой в этой атаке, вплоть до 2.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'poleaxe', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 118],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-756',
    code: 'sekira_kriticheskie_popadaniya',
    type: 'ability',
    name: 'Критические попадания',
    description:
      'в ваших руках получает свойство Критическое : 1 при проверках на попадание дают дополнительный успех.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'poleaxe', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 118],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-757',
    code: 'sekira_smertelnie_uvechya',
    type: 'ability',
    name: 'Смертельные увечья',
    description:
      'в ваших руках получает свойство Смертоносное : “Если попадание этим оружием привело к получению увечья, то такая проверка на увечье получает две помехи”.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'poleaxe', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 118],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-758',
    code: 'sekira_moguchiy_blok',
    type: 'ability',
    name: 'Могучий блок',
    description:
      'За каждый размер, на который ваша сила больше минимальной, получает +2 к блокированию от силы. Размер бонуса равен размеру оружия. Древковое Оружие 1-4 руки',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'poleaxe', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 118],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-759',
    code: 'boevoy-molot_dalnie_udari',
    type: 'ability',
    name: 'Дальние удары',
    description: 'Вы можете удвоить дистанцию оружия для удара, доплатив 1ОД.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'war-hammer', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 143],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-760',
    code: 'boevoy-molot_silovie_udari',
    type: 'ability',
    name: 'Силовые удары',
    description:
      'Вы можете отказаться от преимуществ от проворства для проверок на попадание ~, получив +4 дробящего урона от силы за каждый размер, на который ваша сила больше минимальной. Т.к. бонусы от одного источника не складываются, этот бонус заменяет стандартный на +3 урона.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'war-hammer', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 143],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-761',
    code: 'boevoy-molot_seryoznie_uvechya',
    type: 'ability',
    name: 'Серьёзные увечья',
    description:
      'в ваших руках получает свойство Смертоносное : “Если попадание этим оружием привело к получению увечья, то такая проверка на увечье получает помеху”.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'war-hammer', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 143],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-762',
    code: 'boevoy-molot_moguchiy_blok',
    type: 'ability',
    name: 'Могучий блок',
    description:
      'За каждый размер, на который ваша сила больше минимальной, получает +2 к блокированию от силы. Размер бонуса равен размеру оружия.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'war-hammer', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 143],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-763',
    code: 'dolgiy-mech_silniy_blok',
    type: 'ability',
    name: 'Сильный блок',
    description:
      'За каждый размер, на который ваша сила больше минимальной, получает +1 к блокированию от силы. Размер бонуса равен размеру оружия.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'longsword', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-764',
    code: 'dolgiy-mech_silovoy_metod',
    type: 'ability',
    name: 'Силовой метод',
    description:
      'Вы можете отказаться от преимуществ от проворства для удара ~, в таком случае вы получите преимущества от силы , если ваша сила превышает минимальную.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'longsword', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-765',
    code: 'dolgiy-mech_podavlenie_preimushchestvom',
    type: 'ability',
    name: 'Подавление преимуществом',
    description:
      'Если вы используете этот навык, вы лишаетесь всех преимуществ от силы для удара (обычно это может быть дополнительным уроном и/или одним преимуществом) , но можете перебросить столько кубиков проверки на попадание этого удара, сколько у вас было преимуществ. Оружие Гибкий меч',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [4],
        },
      },
      requirements: [
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'longsword', min_level: 3 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-766',
    code: 'pata_bistrie_ataki',
    type: 'ability',
    name: 'Быстрые атаки',
    description:
      'Если предыдущая действие - атака, в торой вы использовали в качестве оружия только , и вы не платили в это время дополнительные ОД, стоимость этой атаки будет уменьшена на 1ОД, вплоть до 3, если вы в ней будете использовать только',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'pata', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 113],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-767',
    code: 'pata_mnozhestvennie_udari',
    type: 'ability',
    name: 'Множественные удары',
    description:
      'Первая , выбранная в качестве оружия при ударе не учитывается при подсчёте количества помех за использование более чем одного оружия. Этот навык не лишает вас помехи от применения навыка “одновременные удары”.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [3],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'pata', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 113],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-768',
    code: 'pata_odnovremennie_udari',
    type: 'ability',
    name: 'Одновременные удары',
    description:
      'Когда вы используете только в качестве оружия, вы можете объявить, что используете этот навык. В таком случае каждое выбранное оружие после первого не требует затрат ОД, но получит дополнительную помеху на попадание. Этот удар всегда заканчивает или прерывает атаку.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [3],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'pata', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 113],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-769',
    code: 'luk_bistraya_perezaryadka',
    type: 'ability',
    name: 'Быстрая перезарядка',
    description:
      'Если у вас есть одна свободная рука на протяжении всего действия, содержащего выстрел из , то по окончании выстрела вы можете немедленно произвести перезарядку лука, потратив на неё на 1ОД меньше.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'bow', min_level: 1 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 128, 146, 147, 148, 149, 129, 145],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-770',
    code: 'luk_masterskaya_strelba',
    type: 'ability',
    name: 'Мастерская стрельба',
    description:
      'Вы можете при выстреле из этого оружия потратить дополнительно 1ОД, увеличив Дальнобойность оружия вдвое, вплоть до 10 ипари.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [3],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'bow', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 128, 146, 147, 148, 149, 129, 145],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-771',
    code: 'luk_skorostnaya_strelba',
    type: 'ability',
    name: 'Скоростная стрельба',
    description:
      'Если вы после совершения атаки из лука совершаете немедленно атаку из лука по цели, находящейся не дальше 1 ипари от предыдущей цели (того места, где она находилась во время выстрела) , то этот выстрел займёт на 1ОД меньше.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [3],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'bow', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 128, 146, 147, 148, 149, 129, 145],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-772',
    code: 'zagnutiye-kogti_khvatat_i_kolot',
    type: 'ability',
    name: 'Хватать и колоть',
    description:
      'При попадании последним режущим или рубящим ударом атаки , вы можете совершить следующим действием атаку из одного колющего удара с +1 Силой удара от обстоятельств',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'curved-claws', min_level: 1 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-773',
    code: 'zagnutiye-kogti_tsepkiy_khvat',
    type: 'ability',
    name: 'Цепкий хват',
    description:
      'Вы получаете преимущество от обстоятельств для проверок на захват, если используете для этого когти. Если вы используете когти с помощью правой и левой руки одновременно для захвата - вы получаете два преимущества от обстоятельств Для изучения и использования необходимо: Сдвоенный удар',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'curved-claws', min_level: 1 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-774',
    code: 'korotkiy-klinoq_seriya_bistrikh_udarov',
    type: 'ability',
    name: 'Серия быстрых ударов',
    description:
      'Вы можете заменить штраф быстрого удара в доплату 1ОД для следующей атаки на уменьшение силы на размер для всех ударов следующей атаки.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'short-blade', min_level: 1 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-775',
    code: 'korotkiy-klinoq_odnovremennie_udari',
    type: 'ability',
    name: 'Одновременные удары',
    description:
      'Если вытащить (взять в руку) это оружие занимает у вас не больше 1ОД, вы можете вытащить его как часть атаки (т.е. не тратя ОД)',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'short-blade', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-776',
    code: 'urumi_besprestannie_udari',
    type: 'ability',
    name: 'Беспрестанные удары',
    description:
      'Если предыдущая действие - атака, в торой вы использовали в качестве оружия только , и вы не платили в это время дополнительные ОД, стоимость этой атаки будет уменьшена на 1ОД, вплоть до 2, если вы в ней будете использовать только',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'urumi', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-777',
    code: 'urumi_zashchita_v_atake',
    type: 'ability',
    name: 'Защита в атаке',
    description: 'ОД ходящими Оружие 1 рука Минимальная сила: Прочность',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [4],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'urumi', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-778',
    code: 'palitsa_vishibit_dukh',
    type: 'ability',
    name: 'Вышибить дух',
    description:
      'Попадания ударов с 3РУ или больше, которые нанесли цели не меньше [Телосложение] повреждений также отнимают у неё 2ОД. Оружие Боевой молот 1-4 руки',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'club', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 121, 120],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-779',
    code: 'palitsa_podavlenie_oglusheniem',
    type: 'ability',
    name: 'Подавление оглушением',
    description: 'Каждый наложенный попаданием ~ эффект оглушения тратит 2ОД цели.',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'club', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 121, 120],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-780',
    code: 'shield-med_seriya_blokirovaniya',
    type: 'ability',
    name: 'Серия блокирования',
    description:
      'Если вы успешно заблокировали Инструментом у которого есть Укрыться за щитом х , то против остальных ударов этой же атаки(если такие есть) вы получаете Укрытие(Этот щит, Сторона блока,',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'shield-medium', min_level: 1 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-781',
    code: 'shield-med_umeloe_prikritie',
    type: 'ability',
    name: 'Умелое прикрытие',
    description:
      'Укрытие от навыка Укрыться за щитом позволяет не игнорировать попадания с РУ атаки или меньше, а уменьшать РУ атаки на',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'shield-medium', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-782',
    code: 'shield-any_tolchki_i_shchiti',
    type: 'ability',
    name: 'Толчки и щиты',
    description: 'Вы получаете преимущество для проверки на Силу для ваших и направленных на вас толчков.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'shield-any', min_level: 1 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-783',
    code: 'shield-any_otvetniy_udar',
    type: 'ability',
    name: 'Ответный удар',
    description:
      'Если вы успешно заблокировали Инструментом и не получили истощения, то вы можете немедленно совершить инструментом атаку ближнего боя (в т.ч. толчок) . Эта атака требует на 1ОД меньше, вплоть до 2ОД и получает преимущество для проверки на попадание.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'shield-any', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-784',
    code: 'roga_taran',
    type: 'ability',
    name: 'Таран',
    description:
      'Когда вы используете только в качестве оружия для удара с разбега, вы получаете на одну помеху от разбега меньше. Оружие Тяжёлый хвост Минимальная сила:',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [1],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'horns', min_level: 1 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-785',
    code: 'sablya_bistrie_ataki',
    type: 'ability',
    name: 'Быстрые атаки',
    description:
      'Если предыдущая действие - атака, в торой вы использовали в качестве оружия только , и вы не платили в это время дополнительные ОД, стоимость этой атаки будет уменьшена на 1ОД, вплоть до 3, если вы в ней будете использовать только',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [2],
        },
      },
      requirements: [{ level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'saber', min_level: 2 }] }],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-786',
    code: 'flamberg_rezhushchie_ataki',
    type: 'ability',
    name: 'Режущие атаки',
    description: 'Вы можете наносить режущие атаки 5 режущего Точность: Дистанция: ½ ипари',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'flamberge', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-787',
    code: 'gibkiy-klinoq_rasshirenniy_diapazon',
    type: 'ability',
    name: 'Расширенный диапазон',
    description:
      'Каждая 1 на даёт дополнительный успех, а каждая 6 дополнительно лишает успех при проверке на попадание',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {
        or: {
          kind: 'array',
          levels_cost: [4],
        },
      },
      requirements: [
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'flexible-blade', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-788',
    code: 'alebarde_dalniy_vipad',
    type: 'ability',
    name: 'Дальний выпад',
    description:
      'Выпад рубящим ударом алебарды увеличивает дополнительно дистанцию на половину от изначальной дистанции алебарды. Это позволяет выпадом алебарды среднего размера среднему существу бить с дистанцией 2 ипари. Оружие Боевая коса 1-4 руки',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'halberd', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-789',
    code: 'small-shield_shchit_i_oruzhie',
    type: 'ability',
    name: 'Щит и оружие',
    description: 'Вы можете использовать',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'small-shield', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 150],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-790',
    code: 'class-shield_shchit_i_oruzhie',
    type: 'ability',
    name: 'Щит и оружие',
    description:
      'Если вы используете инструмент при проверке на попадание по вам, то каждая 1 даёт дополнительный успех.',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'classic-shield', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 151],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
  {
    id: 'rule-791',
    code: 'big-shield_ustoychivost',
    type: 'ability',
    name: 'Устойчивость',
    description:
      'Ваши Сила и Телосложение считаются на размер больше, когда против вас совершается проверка на толчок или захват со стороны, с которой вы прикрываетесь ко..',
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
        { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: 'tower-shield', min_level: 2 }] },
      ],
      grants: [],
      parent_ability_code: null,
    },
    keywordIds: [13, 152],
    mechanicId: null,
    createdAt: '2026-08-17T10:00:00Z',
  },
];
