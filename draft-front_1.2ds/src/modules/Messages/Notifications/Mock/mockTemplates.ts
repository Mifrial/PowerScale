import type { NotificationTemplate } from '@/modules/Messages/Notifications/Dto/NotificationTemplate'
import type { CreateTemplateData, UpdateTemplateData } from '../Interface/INotificationTemplateApi'

const delay = (ms = 300) => new Promise(r => setTimeout(r, ms))

let nextId = 4

const templates: NotificationTemplate[] = [
  {
    id: 1,
    key: 'game_invite',
    titleTemplate: 'Приглашение в игру "{{game_name}}"',
    bodyTemplate: '<p>Вас пригласили присоединиться к игре <strong>{{game_name}}</strong>.</p>',
    buttonsJson: [
      { label: 'Принять', actionType: 'event', action: 'accept_invite', payload: {} },
      { label: 'Отклонить', actionType: 'event', action: 'decline_invite', payload: {} },
    ],
    active: true,
  },
  {
    id: 2,
    key: 'character_moderation',
    titleTemplate: 'Персонаж "{{character_name}}" на модерации',
    bodyTemplate: '<p>Игрок {{player_name}} отправил персонажа на модерацию.</p>',
    buttonsJson: [
      { label: 'Проверить', actionType: 'url', action: '/games/{{game_id}}/moderate', payload: {} },
    ],
    active: true,
  },
  {
    id: 3,
    key: 'migration_complete',
    titleTemplate: 'Миграция персонажа завершена',
    bodyTemplate: '<p>Персонаж "{{character_name}}" успешно мигрирован на новую версию правил.</p>',
    buttonsJson: [],
    active: true,
  },
]

export async function fetchTemplates(_signal?: AbortSignal): Promise<NotificationTemplate[]> {
  await delay()
  return templates.map(t => ({ ...t }))
}

export async function fetchTemplate(id: number, _signal?: AbortSignal): Promise<NotificationTemplate> {
  await delay()
  const t = templates.find(t => t.id === id)
  if (!t) throw new Error(`Template ${id} not found`)
  return { ...t }
}

export async function createTemplate(data: CreateTemplateData, _signal?: AbortSignal): Promise<NotificationTemplate> {
  await delay()
  const template: NotificationTemplate = {
    id: nextId++,
    key: data.key,
    titleTemplate: data.titleTemplate,
    bodyTemplate: data.bodyTemplate,
    buttonsJson: data.buttonsJson,
    active: true,
  }
  templates.push(template)
  return { ...template }
}

export async function updateTemplate(id: number, data: UpdateTemplateData, _signal?: AbortSignal): Promise<NotificationTemplate> {
  await delay()
  const t = templates.find(t => t.id === id)
  if (!t) throw new Error(`Template ${id} not found`)
  if (data.key !== undefined) t.key = data.key
  if (data.titleTemplate !== undefined) t.titleTemplate = data.titleTemplate
  if (data.bodyTemplate !== undefined) t.bodyTemplate = data.bodyTemplate
  if (data.buttonsJson !== undefined) t.buttonsJson = data.buttonsJson
  return { ...t }
}

export async function deactivateTemplate(id: number, _signal?: AbortSignal): Promise<void> {
  await delay()
  const t = templates.find(t => t.id === id)
  if (t) t.active = false
}
