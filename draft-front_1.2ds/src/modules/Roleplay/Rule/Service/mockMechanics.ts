export interface Mechanic {
  id: number
  code: string
  name: string
  description: string
  version: string
}

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
    description: 'Преимущество добавляет кубик и убирает худший результат. Помеха добавляет кубик и убирает лучший результат.',
    version: '2.1.0',
  },
  {
    id: 3,
    code: 'double_strike',
    name: 'Двойной удар',
    description: 'Атака, наносящая два удара подряд с модификатором.',
    version: '4.1.5',
  },
]

const delay = (ms = 300) => new Promise(r => setTimeout(r, ms))

export async function fetchMechanics(_signal?: AbortSignal): Promise<Mechanic[]> {
  await delay()
  return mechanics.map(m => ({ ...m }))
}

export async function fetchMechanic(id: number, _signal?: AbortSignal): Promise<Mechanic> {
  await delay()
  const mechanic = mechanics.find(m => m.id === id)
  if (!mechanic) throw new Error(`Mechanic ${id} not found`)
  return { ...mechanic }
}
