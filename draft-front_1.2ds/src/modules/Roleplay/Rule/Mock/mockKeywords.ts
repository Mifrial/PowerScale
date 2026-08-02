import type { Keyword } from '../Dto/Keyword'
import type { CreateKeywordData, UpdateKeywordData } from '../Interface/IKeywordApi'

const delay = (ms = 300) => new Promise(r => setTimeout(r, ms))

let nextId = 11

const keywords: Keyword[] = [
  { id: 1, code: 'melee', name: 'Ближний бой', description: 'Оружие и навыки ближнего боя', active: true },
  { id: 2, code: 'ranged', name: 'Дальний бой', description: 'Дистанционное оружие', active: true },
  { id: 3, code: 'magic', name: 'Волшебство', description: 'Магические способности и заклинания', active: true },
  { id: 4, code: 'stealth', name: 'Скрытность', description: 'Навыки скрытного передвижения', active: true },
  { id: 5, code: 'crafting', name: 'Ремесло', description: 'Создание предметов', active: true },
  { id: 6, code: 'diplomacy', name: 'Дипломатия', description: 'Переговоры и убеждение', active: false },
  { id: 7, code: 'combat', name: 'Боевое', description: 'Подтип боевых способностей', active: true },
  { id: 8, code: 'utility', name: 'Полезное', description: 'Подтип утилитарных способностей', active: true },
  { id: 9, code: 'passive', name: 'Пассивное', description: 'Подтип пассивных способностей', active: true },
  { id: 10, code: 'active', name: 'Активное', description: 'Подтип активных способностей', active: true },
  { id: 11, code: 'trait', name: 'Черта', description: 'Тип способности: черта', active: true },
  { id: 12, code: 'feature', name: 'Особенность', description: 'Тип способности: особенность', active: true },
  { id: 13, code: 'skill', name: 'Навык', description: 'Тип способности: навык', active: true },
  { id: 14, code: 'action', name: 'Действие', description: 'Тип способности: действие (требует ОД)', active: true },
  { id: 15, code: 'process', name: 'Процесс', description: 'Тип способности: процесс (шаги)', active: true },
  { id: 16, code: 'spell', name: 'Заклинание', description: 'Тип способности: заклинание', active: true },
]

export async function fetchTags(_signal?: AbortSignal): Promise<Keyword[]> {
  await delay()
  return keywords.map(t => ({ ...t }))
}

export async function fetchTag(id: number, _signal?: AbortSignal): Promise<Keyword> {
  await delay()
  const t = keywords.find(t => t.id === id)
  if (!t) throw new Error(`Keyword ${id} not found`)
  return { ...t }
}

export async function createTag(data: CreateKeywordData, _signal?: AbortSignal): Promise<Keyword> {
  await delay()
  const keyword: Keyword = {
    id: nextId++,
    code: data.code,
    name: data.name,
    description: data.description,
    active: true,
  }
  keywords.push(keyword)
  return { ...keyword }
}

export async function updateTag(id: number, data: UpdateKeywordData, _signal?: AbortSignal): Promise<Keyword> {
  await delay()
  const t = keywords.find(t => t.id === id)
  if (!t) throw new Error(`Keyword ${id} not found`)
  if (data.code !== undefined) t.code = data.code
  if (data.name !== undefined) t.name = data.name
  if (data.description !== undefined) t.description = data.description
  if (data.active !== undefined) t.active = data.active
  return { ...t }
}

export async function deactivateTag(id: number, _signal?: AbortSignal): Promise<void> {
  await delay()
  const t = keywords.find(t => t.id === id)
  if (t) t.active = false
}
