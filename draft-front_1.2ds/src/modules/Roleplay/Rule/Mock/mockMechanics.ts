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
];

const delay = (ms = 300) => new Promise((r) => setTimeout(r, ms));

export async function fetchMechanics(_signal?: AbortSignal): Promise<Mechanic[]> {
  await delay();

  return mechanics.map((m) => ({ ...m }));
}
