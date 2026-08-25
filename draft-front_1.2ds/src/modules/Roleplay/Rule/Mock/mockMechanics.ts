import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';

const mechanics: Mechanic[] = [
  {
    id: 1,
    code: 'six_one_rule',
    name: 'Правило 6 и 1',
    description: 'При броске кубика: 1 даёт дополнительный успех, 6 убирает один успех.',
    version: '4.5.0',
  },
  {
    id: 2,
    code: 'advantage_disadvantage',
    name: 'Помехи и преимущества',
    description:
      'Преимущество добавляет кубик и убирает худший результат. Помеха добавляет кубик и убирает лучший результат.',
    version: '2.1.0',
  },
  {
    id: 3,
    code: 'double_strike',
    name: 'Двойной удар',
    description: 'Атака, наносящая два удара подряд с модификатором.',
    version: '4.1.5',
  },
  {
    id: 4,
    code: 'purchase_surcharge',
    name: 'Прогрессивная доплата',
    description:
      'Третья и каждая последующая способность из фильтра доплачивает ОС сверх своей цены. Инстанции задаются payload.filter (признак «Общая», расовый признак Зверолюдей).',
    version: '1.0.0',
  },
  {
    id: 5,
    code: 'roll',
    name: 'Бросок',
    description:
      'Базовые параметры броска кубиков игры (дефолтные кубы/сложность/преимущества из правила «Бросок» ревизии).',
    version: '1.0.0',
  },
  {
    id: 6,
    code: 'critical_strike',
    name: 'Критический удар',
    description:
      'Активируемая механика броска: «1» начисляет дополнительный успех, грань списывает ещё один (поверх прочих механик подсчёта).',
    version: '1.0.0',
  },
  {
    id: 7,
    code: 'strike',
    name: 'Удар',
    description: 'Процедура ближнего удара. Алгоритм в клиенте: strike@version из среза ревизии.',
    version: '1.0.0',
  },
  {
    id: 8,
    code: 'injury_extra_dice_from_sr',
    name: 'Сложность увечья от РУ',
    description: 'На проверке увечья добавляет ⌊РУ атаки / 2⌋ к сложности (колющий).',
    version: '1.0.0',
  },
  {
    id: 9,
    code: 'injury_efficiency',
    name: 'Помеха на увечье',
    description: 'Дельта < 0 даёт помехи на проверку увечья (рубящий: −1 → одна помеха).',
    version: '1.0.0',
  },
  {
    id: 10,
    code: 'pay_sr_vs_reliability',
    name: 'РУ против надёжности',
    description: 'Набранные РУ атаки игнорируют всё с надёжностью ≤ РУ. РУ не тратятся.',
    version: '1.0.0',
  },
  {
    id: 11,
    code: 'exhaustion_to_stun',
    name: 'Истощение в оглушение',
    description: 'Истощение от этого типа урона накладывает оглушение той же силы.',
    version: '1.0.0',
  },
  {
    id: 12,
    code: 'exhaustion_to_wound',
    name: 'Истощение в рану',
    description: 'Истощение от этого типа урона накладывает рану (множитель в payload).',
    version: '1.0.0',
  },
  {
    id: 13,
    code: 'damage_as_wounds_by_size',
    name: 'Урон как раны по размеру',
    description: 'Повреждения не в HP: размер урона привести к размеру цели → рана этой силы.',
    version: '1.0.0',
  },
  {
    id: 14,
    code: 'blunt_unconscious',
    name: 'Потеря сознания от дробящего',
    description: 'При РУ ≥ 6 или дробящих повреждениях ≥ Стойкости — потеря сознания.',
    version: '1.0.0',
  },
  {
    id: 15,
    code: 'injury',
    name: 'Увечье',
    description: 'Процедура проверки на увечье. Алгоритм в клиенте: injury@version из среза ревизии.',
    version: '1.0.0',
  },
];

const delay = (ms = 300) => new Promise((r) => setTimeout(r, ms));

export async function fetchMechanics(_signal?: AbortSignal): Promise<Mechanic[]> {
  await delay();

  return mechanics.map((m) => ({ ...m }));
}
