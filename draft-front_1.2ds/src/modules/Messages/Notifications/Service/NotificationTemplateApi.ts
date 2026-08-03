import type { Engine } from '@/modules/Core/Engine/Service/Engine'
import type { INotificationTemplateApi, CreateTemplateData, UpdateTemplateData } from '@/modules/Messages/Notifications/Interface/INotificationTemplateApi'
import type { NotificationTemplate } from '@/modules/Messages/Notifications/Dto/NotificationTemplate'

export class NotificationTemplateApi implements INotificationTemplateApi {
  constructor(private readonly engine: Engine) {}

  async getTemplates(signal?: AbortSignal): Promise<NotificationTemplate[]> {
    const res = await this.engine.runAction<NotificationTemplate[]>('notificationTemplate.getList', undefined, signal)
    return res.data ?? []
  }

  async getTemplate(id: number, signal?: AbortSignal): Promise<NotificationTemplate> {
    const res = await this.engine.runAction<NotificationTemplate>('notificationTemplate.get', { id }, signal)
    if (!res.data) throw new Error('Template not found')
    return res.data
  }

  async createTemplate(data: CreateTemplateData, signal?: AbortSignal): Promise<NotificationTemplate> {
    const res = await this.engine.runAction<NotificationTemplate>('notificationTemplate.create', data, signal)
    if (!res.data) throw new Error('Failed to create template')
    return res.data
  }

  async updateTemplate(id: number, data: UpdateTemplateData, signal?: AbortSignal): Promise<NotificationTemplate> {
    const res = await this.engine.runAction<NotificationTemplate>('notificationTemplate.update', { id, ...data }, signal)
    if (!res.data) throw new Error('Failed to update template')
    return res.data
  }

  async deactivateTemplate(id: number, signal?: AbortSignal): Promise<void> {
    await this.engine.runAction('notificationTemplate.deactivate', { id }, signal)
  }
}
