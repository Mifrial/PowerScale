import type { Source } from '../Interface/types'

const sources: Source[] = [
  { id: 1, code: 'armor', name: 'от Доспеха' },
  { id: 2, code: 'shield', name: 'от Щита' },
  { id: 3, code: 'spell', name: 'от Заклинания' },
  { id: 4, code: 'innate', name: 'врождённый' },
  { id: 5, code: 'training', name: 'Тренировка' },
  { id: 6, code: 'development', name: 'Развитие' },
]

const delay = (ms = 300) => new Promise(r => setTimeout(r, ms))

export async function fetchSources(_signal?: AbortSignal): Promise<Source[]> {
  await delay()
  return sources.map(s => ({ ...s }))
}
