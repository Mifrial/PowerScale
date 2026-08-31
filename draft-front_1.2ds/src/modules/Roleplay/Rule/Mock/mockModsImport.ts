import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ItemModifierApplies } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierApplies';
import type { ItemModifierEffect } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierEffect';
import type { ItemModifierPrice } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierPrice';
import type { ItemModifierSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierSpec';

/**
 * Импорт каталога модификаторов предметов (R29, заход 1).
 * Источник: docs/rule/mods/catalog.md. Таблица качества доспеха и уникальные
 * свойства изготовления — не покупаемые правила (keywords на предметах).
 */

function modifier(
  index: number,
  code: string,
  name: string,
  typeCode: string,
  applies: ItemModifierApplies,
  priceSpec: ItemModifierPrice,
  effects: ItemModifierEffect[],
  extra: Partial<ItemModifierSpec> = {},
): Rule {
  const spec: ItemModifierSpec = { type_code: typeCode, applies, price: priceSpec, effects, ...extra };

  return {
    id: 800 + index,
    code,
    type: 'item_modifier',
    name,
    description: effects.map((effect) => (effect.label ? `${effect.label}: ${effect.text}` : effect.text)).join('\n'),
    spaceId: 1,
    spec,
    keywordIds: [],
    mechanicId: null,
    createdAt: '2026-08-21T10:00:00Z',
  };
}

function weaponApplies(extraNone: string[] = []): ItemModifierApplies {
  return { keyword_all: ['weapon'], keyword_any: [], keyword_none: ['shield-item', ...extraNone] };
}

function staffApplies(): ItemModifierApplies {
  return { keyword_all: ['weapon', 'staff'], keyword_any: [], keyword_none: ['shield-item'] };
}

function shieldApplies(): ItemModifierApplies {
  return { keyword_all: ['shield-item'], keyword_any: [], keyword_none: [] };
}

function armorApplies(): ItemModifierApplies {
  return { keyword_all: ['armor-item'], keyword_any: [], keyword_none: [] };
}

function equipmentApplies(): ItemModifierApplies {
  return { keyword_all: [], keyword_any: ['weapon', 'shield-item', 'armor-item'], keyword_none: [] };
}

function metalApplies(): ItemModifierApplies {
  return { keyword_all: ['metal'], keyword_any: [], keyword_none: [] };
}

function leatherArmorApplies(): ItemModifierApplies {
  return { keyword_all: ['leather', 'armor-item'], keyword_any: [], keyword_none: [] };
}

function price(partial: Partial<ItemModifierPrice>): ItemModifierPrice {
  return {
    factor: partial.factor ?? null,
    add_gm: partial.add_gm ?? null,
    add_gm_per_100g: partial.add_gm_per_100g ?? null,
    min_final_gm: partial.min_final_gm ?? null,
    by_keyword: partial.by_keyword ?? null,
  };
}

export const mockModsImport: Rule[] = [
  modifier(
    0,
    'improvised',
    'Импровизированное',
    'craft-quality',
    { keyword_all: ['weapon', 'improvable'], keyword_any: [], keyword_none: ['shield-item'] },
    price({ factor: 0.25 }),
    [
      {
        text: 'Все проверки на попадание этим оружием имеют помеху от инструмента, а его прочность уменьшена на размер. Изначальная цена такого оружия, если он не обладает другими модификаторами, или если она имеет лишь модификатор, уменьшающий размер, считается равной нулю.',
        ops: [
          { type: 'durability', add_size: -1 },
          { type: 'advantage', delta: -1, source_code: 'tool' },
        ],
      },
    ],
  ),
  modifier(1, 'poorly-made', 'Плохо сделан', 'craft-quality', weaponApplies(), price({ factor: 0.5 }), [
    {
      text: 'Все проверки на попадание этим оружием имеют помеху от инструмента, а его прочность уменьшена на размер.',
      ops: [
        { type: 'durability', add_size: -1 },
        { type: 'advantage', delta: -1, source_code: 'tool' },
      ],
    },
  ]),
  modifier(2, 'sturdily-made', 'Крепко сделан', 'craft-quality', weaponApplies(), price({ factor: 1.2 }), [
    {
      text: 'Все проверки на попадание этим оружием имеют помеху от инструмента, но его прочность увеличена на размер.',
      ops: [
        { type: 'durability', add_size: 1 },
        { type: 'advantage', delta: -1, source_code: 'tool' },
      ],
    },
  ]),
  modifier(
    3,
    'excellently-made',
    'Отлично сделан',
    'craft-quality',
    weaponApplies(),
    price({ factor: 3, min_final_gm: 1000 }),
    [
      {
        text: 'Все проверки на попадание этим оружием имеют преимущество от инструмента, если ваш уровень владения им минимум Владение(2) или выше.',
        ops: [{ type: 'advantage', delta: 1, source_code: 'tool' }],
      },
    ],
  ),
  modifier(
    4,
    'excellently-made-sturdy',
    'Отлично сделан: крепкий',
    'craft-quality',
    weaponApplies(),
    price({ factor: 2, min_final_gm: 1000 }),
    [{ text: 'Прочность оружия увеличена на размер.', ops: [{ type: 'durability', add_size: 1 }] }],
  ),
  modifier(
    5,
    'masterwork-weapon',
    'Мастерски сделанный',
    'craft-quality',
    weaponApplies(),
    price({ factor: 10, min_final_gm: 10000 }),
    [
      {
        text: 'Все проверки на попадание этим оружием имеют преимущество от инструмента, если ваш уровень владения им минимум Владение(2) или выше. Прочность оружия увеличена на размер.',
        ops: [
          { type: 'durability', add_size: 1 },
          { type: 'advantage', delta: 1, source_code: 'tool' },
        ],
      },
    ],
  ),
  modifier(6, 'terrible-quality', 'Ужасное качество', 'craft-quality', armorApplies(), price({ factor: 1 / 3 }), [
    { text: 'Выберите минимум отрицательных свойств в таблице качества минимум на -5 очков.' },
  ]),
  modifier(7, 'low-quality', 'Низкокачественное', 'craft-quality', armorApplies(), price({ factor: 0.7 }), [
    { text: 'Выберите минимум отрицательных свойств в таблице качества минимум на -2 очка.' },
  ]),
  modifier(8, 'good-quality', 'Хорошее качество', 'craft-quality', armorApplies(), price({ factor: 1.5 }), [
    {
      text: 'Выберите свойства из таблицы качества не дороже 2 очков каждое так, чтобы их суммарная стоимость была не больше 2 очков.',
    },
  ]),
  modifier(
    9,
    'excellent-quality',
    'Отличное качество',
    'craft-quality',
    armorApplies(),
    price({ factor: 3, min_final_gm: 5000 }),
    [
      {
        text: 'Выберите свойства из таблицы качества не дороже 3 очков каждое так, чтобы их суммарная стоимость была не больше 3 очков.',
      },
    ],
  ),
  modifier(
    10,
    'masterwork-armor',
    'Мастерски сделан',
    'craft-quality',
    armorApplies(),
    price({ factor: 10, min_final_gm: 50000 }),
    [
      {
        text: 'Выберите свойства из таблицы качества не дороже 5 очков каждое так, чтобы их суммарная стоимость была не больше 5 очков.',
      },
    ],
  ),
  modifier(
    11,
    'spoiled-material',
    'Испорченный материал',
    'material-quality',
    weaponApplies(),
    price({ factor: 1 / 3 }),
    [{ text: 'Прочность оружия уменьшена на два размера.', ops: [{ type: 'durability', add_size: -2 }] }],
  ),
  modifier(
    12,
    'poor-material',
    'Плохой материал',
    'material-quality',
    equipmentApplies(),
    price({
      factor: 1 / 1.2,
      by_keyword: {
        'armor-item': { factor: 0.5 },
        'shield-item': { factor: 0.5 },
      },
    }),
    [
      {
        label: 'Оружие',
        text: 'Прочность уменьшена на размер.',
        ops: [{ type: 'durability', add_size: -1 }],
      },
      {
        label: 'Щит',
        text: 'Блокирование уменьшено вдвое.',
        ops: [{ type: 'block', add_size: -1 }],
      },
      {
        label: 'Доспех',
        text: 'Защита уменьшена вдвое.',
        ops: [{ type: 'defense', add_size: -1 }],
      },
    ],
  ),
  modifier(13, 'excellent-material', 'Отличный материал', 'material-quality', shieldApplies(), price({ factor: 10 }), [
    {
      text: 'Блокирование увеличено на ¼. Прочность +1.',
      ops: [
        { type: 'block', factor: 1.25 },
        { type: 'durability', delta: 1 },
      ],
    },
  ]),
  modifier(14, 'weighted', 'Утяжелённый', 'item-weight', equipmentApplies(), price({ factor: 1.5 }), [
    {
      label: 'Оружие',
      text: 'Вес увеличен на ¼. +1 к урону. +¼ к блокированию. +2 к минимальной силе.',
      ops: [
        { type: 'weight', factor: 1.25 },
        { type: 'block', factor: 1.25 },
        { type: 'min_strength', delta: 2 },
        { type: 'action_strength', field: 'damage', delta: 1, profiles: ['strike', 'throw'] },
      ],
    },
    {
      label: 'Щит',
      text: 'Вес и блокирование увеличены на ¼, +1 к минимальной силе, +1 к прочности.',
      ops: [
        { type: 'weight', factor: 1.25 },
        { type: 'block', factor: 1.25 },
        { type: 'min_strength', delta: 1 },
        { type: 'durability', delta: 1 },
      ],
    },
    {
      label: 'Доспех',
      text: 'Вес и защита увеличены на ¼, максимум Ловкости снижен на 1, штраф к силе увеличен на 1.',
      ops: [
        { type: 'weight', factor: 1.25 },
        { type: 'defense', factor: 1.25 },
        { type: 'max_agility', delta: -1 },
        { type: 'strength_penalty', add: -1 },
      ],
    },
  ]),
  modifier(
    15,
    'very-heavy',
    'Очень тяжёлый',
    'item-weight',
    {
      keyword_all: [],
      keyword_any: ['shield-item', 'armor-item'],
      keyword_none: [],
    },
    price({ factor: 2 }),
    [
      {
        label: 'Щит',
        text: 'Вес увеличен на ½, блокирование на ⅓, +1 к дробящему урону, +3 к минимальной силе, +2 к прочности.',
        ops: [
          { type: 'weight', factor: 1.5 },
          { type: 'block', factor: 4 / 3 },
          { type: 'min_strength', delta: 3 },
          { type: 'durability', delta: 2 },
          {
            type: 'action_strength',
            field: 'damage',
            delta: 1,
            profiles: ['strike'],
            damage_type_codes: ['blunt'],
          },
        ],
      },
      {
        label: 'Доспех',
        text: 'Вес увеличен на ½, защита на ⅓, максимум Ловкости снижен на 3, штраф к силе увеличен на 2.',
        ops: [
          { type: 'weight', factor: 1.5 },
          { type: 'defense', factor: 4 / 3 },
          { type: 'max_agility', delta: -3 },
          { type: 'strength_penalty', add: -2 },
        ],
      },
    ],
  ),
  modifier(16, 'lightened', 'Облегчённое', 'item-weight', equipmentApplies(), price({ factor: 1.2 }), [
    {
      label: 'Оружие',
      text: 'Вес уменьшен на 10%, -1 к урону, -10% к блокированию, минимальная сила уменьшена на 1.',
      ops: [
        { type: 'weight', factor: 0.9 },
        { type: 'block', factor: 0.9 },
        { type: 'min_strength', delta: -1 },
        { type: 'action_strength', field: 'damage', delta: -1, profiles: ['strike', 'throw'] },
      ],
    },
    {
      label: 'Щит',
      text: 'Вес и блокирование снижены на ¼, -2 к минимальной силе, -1 к прочности.',
      ops: [
        { type: 'weight', factor: 0.75 },
        { type: 'block', factor: 0.75 },
        { type: 'min_strength', delta: -2 },
        { type: 'durability', delta: -1 },
      ],
    },
    {
      label: 'Доспех',
      text: 'Вес и защита снижены на ¼ (защита минимум на 1), максимум Ловкости увеличен на 1.',
      ops: [
        { type: 'weight', factor: 0.75 },
        { type: 'defense', factor: 0.75, min: 1 },
        { type: 'max_agility', delta: 1 },
      ],
    },
  ]),
  modifier(
    17,
    'balance-hilt',
    'Баланс на рукояти',
    'balance',
    weaponApplies(['double-ended', 'polearm']),
    price({ factor: 1.1 }),
    [
      {
        text: '-1ОД и -1 к пробитию и урону для рубящих и дробящих ударов от баланса.',
        ops: [
          {
            type: 'action_strength',
            field: 'damage',
            delta: -1,
            profiles: ['strike'],
            damage_type_codes: ['slashing', 'blunt'],
          },
          {
            type: 'action_strength',
            field: 'penetration',
            delta: -1,
            profiles: ['strike'],
            damage_type_codes: ['slashing', 'blunt'],
          },
        ],
      },
    ],
  ),
  modifier(
    18,
    'balance-tip',
    'Баланс на наконечник',
    'balance',
    weaponApplies(['double-ended']),
    price({ factor: 1.1 }),
    [
      {
        text: '+3 к минимальной силе и +1 к урону и пробитию для рубящих и дробящих ударов от баланса.',
        ops: [
          { type: 'min_strength', delta: 3 },
          {
            type: 'action_strength',
            field: 'damage',
            delta: 1,
            profiles: ['strike'],
            damage_type_codes: ['slashing', 'blunt'],
          },
          {
            type: 'action_strength',
            field: 'penetration',
            delta: 1,
            profiles: ['strike'],
            damage_type_codes: ['slashing', 'blunt'],
          },
        ],
      },
    ],
  ),
  modifier(19, 'silvered', 'Посеребрённое', 'coating', equipmentApplies(), price({ add_gm_per_100g: 100 }), [
    { label: 'Оружие/щит', text: 'Сопротивление магии увеличивается до 1.' },
    {
      label: 'Общее',
      text: 'Предмет получает свойство Сопротивление магии 1.',
      ops: [{ type: 'resistance', damage_type_code: 'magic-damage', mode: 'max', value: 1 }],
    },
  ]),
  modifier(20, 'silver', 'Серебряное', 'material', metalApplies(), price({ factor: 10 }), [
    { label: 'Оружие/щит', text: 'Прочность снижена на размер, сопротивление магии увеличивается до 5.' },
    {
      label: 'Общее',
      text: 'Предмет получает свойство Сопротивление магии 5, +20% к весу от материала, +1 к минимальной силе (если есть), размер к прочности/блокированию/защите уменьшается на размер (если есть).',
      ops: [
        { type: 'weight', factor: 1.2 },
        { type: 'min_strength', delta: 1 },
        { type: 'durability', add_size: -1 },
        { type: 'block', add_size: -1 },
        { type: 'defense', add_size: -1 },
        { type: 'resistance', damage_type_code: 'magic-damage', mode: 'max', value: 5 },
      ],
    },
  ]),
  modifier(21, 'gold', 'Золотое', 'material', metalApplies(), price({ factor: 100 }), [
    { label: 'Оружие/щит', text: 'Прочность снижена на размер, оружие получает свойство Проводник магии 10.' },
    {
      label: 'Общее',
      text: 'Предмет получает свойство Проводник магии 10, +250% к весу от материала, +4 к минимальной силе (если есть), размеры из прочности/блокирования/защиты уменьшается на размер (если есть).',
      ops: [
        { type: 'weight', factor: 3.5 },
        { type: 'min_strength', delta: 4 },
        { type: 'durability', add_size: -1 },
        { type: 'block', add_size: -1 },
        { type: 'defense', add_size: -1 },
        { type: 'magic_conductor', value: 10 },
        { type: 'keyword', add: ['magic-conductor'] },
      ],
    },
  ]),
  modifier(22, 'steel-ferrule', 'Стальная оковка', 'ferrule', staffApplies(), price({ add_gm: 250 }), [
    {
      text: 'Оружие получает «металл» в описание, а все удары им отныне стоят минимум 2ОД. Вес +0,2кг, урон +1 от материала, минимальная сила +1, прочность +1 размер от материала.',
      ops: [
        { type: 'weight', add_kg: 0.2 },
        { type: 'min_strength', delta: 1 },
        { type: 'durability', add_size: 1 },
        { type: 'action_strength', field: 'damage', delta: 1, profiles: ['strike'] },
        { type: 'min_action_cost', min: 2 },
        { type: 'keyword', add: ['metal'] },
      ],
    },
  ]),
  modifier(23, 'silver-ferrule', 'Серебряная оковка', 'ferrule', staffApplies(), price({ add_gm: 5000 }), [
    {
      text: 'Оружие получает «металл» и «серебро» в описание, а все удары им отныне стоят минимум 2ОД. Вес +0,3кг, урон +1 от материала, минимальная сила +1, прочность +1 размер, сопротивление магии до 4. Сопротивление магии оружия позволяет ему поражать некоторых неосязаемых существ.',
      ops: [
        { type: 'weight', add_kg: 0.3 },
        { type: 'min_strength', delta: 1 },
        { type: 'durability', add_size: 1 },
        { type: 'action_strength', field: 'damage', delta: 1, profiles: ['strike'] },
        { type: 'resistance', damage_type_code: 'magic-damage', mode: 'max', value: 4 },
        { type: 'min_action_cost', min: 2 },
        { type: 'keyword', add: ['metal', 'silver'] },
      ],
    },
  ]),
  modifier(24, 'closed-helm', 'С закрытым шлемом', 'kit', armorApplies(), price({ add_gm: 10000 }), [
    {
      text: 'Надёжность всех защит доспеха +1. 1 помеха для проверок на внимательность от предмета.',
      ops: [
        { type: 'armor_reliability', add: 1 },
        { type: 'check_advantage', delta: -1, characteristic_codes: ['attention'] },
      ],
    },
  ]),
  modifier(25, 'breastplate-only', 'Только нагрудник', 'kit', armorApplies(), price({ factor: 1 / 3 }), [
    {
      text: 'Доспех имеет только нагрудник. Его надёжность уменьшена до 1, но максимум Ловкости на размер больше, нет штрафа к силе и лишается свойства «Требует владения» (если имел). Вес нагрудника равен ¼ веса доспеха.',
      ops: [
        { type: 'weight', factor: 0.25 },
        { type: 'armor_reliability', set: 1 },
        { type: 'max_agility', add_size: 1 },
        { type: 'strength_penalty', set: 0 },
        { type: 'keyword', remove: ['requires-proficiency'] },
      ],
    },
  ]),
  modifier(26, 'incomplete-kit', 'Неполная', 'kit', armorApplies(), price({ factor: 0.5 }), [
    {
      text: 'Доспех не полон. Надёжность уменьшена до 2, максимум Ловкости на 2 больше. Вес неполной комплектации равен ½ от полного.',
      ops: [
        { type: 'weight', factor: 0.5 },
        { type: 'armor_reliability', set: 2 },
        { type: 'max_agility', delta: 2 },
      ],
    },
  ]),
  modifier(27, 'insulated', 'Утеплённый', 'insulation', armorApplies(), price({ add_gm: 3000 }), [
    {
      text: 'Вес доспеха увеличен на 3 килограмма, максимум Ловкости снижен на 1, носитель получает 2 сопротивления холоду. В качестве материала для утепления может выступить модификатор с типом «материал», в котором указано, что может быть использован для утепления.',
      ops: [
        { type: 'weight', add_kg: 3 },
        { type: 'max_agility', delta: -1 },
        { type: 'resistance', damage_type_code: 'cold', mode: 'add', value: 2 },
      ],
    },
  ]),
  modifier(
    28,
    'lightning-snake-leather',
    'Кожа молниевой змеи',
    'material',
    leatherArmorApplies(),
    price({ add_gm: 5000 }),
    [
      {
        text: 'Защита -1, максимум Ловкости +1; 5 сопротивления электричеству.',
        ops: [
          { type: 'defense', add: -1 },
          { type: 'max_agility', delta: 1 },
          { type: 'resistance', damage_type_code: 'electricity', mode: 'add', value: 5 },
        ],
      },
    ],
  ),
  modifier(29, 'sky-ray-leather', 'Кожа небесного ската', 'material', leatherArmorApplies(), price({ add_gm: 30000 }), [
    {
      text: '10 сопротивления электричеству; 1 сопротивления магии.',
      ops: [
        { type: 'resistance', damage_type_code: 'electricity', mode: 'add', value: 10 },
        { type: 'resistance', damage_type_code: 'magic-damage', mode: 'add', value: 1 },
      ],
    },
  ]),
  modifier(30, 'salamander-leather', 'Кожа саламандры', 'material', leatherArmorApplies(), price({ add_gm: 100000 }), [
    {
      text: 'Защита +1, максимум Ловкости -1; 20 сопротивления огню; 3 сопротивления магии.',
      ops: [
        { type: 'defense', add: 1 },
        { type: 'max_agility', delta: -1 },
        { type: 'resistance', damage_type_code: 'fire', mode: 'add', value: 20 },
        { type: 'resistance', damage_type_code: 'magic-damage', mode: 'add', value: 3 },
      ],
    },
  ]),
  modifier(
    31,
    'stonehide-rhino-leather',
    'Кожа камнешкурого носорога',
    'material',
    leatherArmorApplies(),
    price({ add_gm: 30000 }),
    [
      {
        text: 'Защита +4, максимум Ловкости -4, штраф к силе -1; 5 сопротивления электричеству; 1 сопротивления магии. Трудный для работы материал: модификаторы качества, увеличивающие цену, увеличены вдвое.',
        ops: [
          { type: 'defense', add: 4 },
          { type: 'max_agility', delta: -4 },
          { type: 'strength_penalty', add: -1 },
          { type: 'resistance', damage_type_code: 'electricity', mode: 'add', value: 5 },
          { type: 'resistance', damage_type_code: 'magic-damage', mode: 'add', value: 1 },
        ],
      },
    ],
    { price_scale: { type_code: 'craft-quality', factor: 2, increasing_only: true } },
  ),
];
