import type { NotificationTemplate } from '@/modules/Messages/Notifications/Dto/NotificationTemplate';
import type { CreateTemplateData } from '@/modules/Messages/Notifications/Dto/CreateTemplateData';
import type { UpdateTemplateData } from '@/modules/Messages/Notifications/Dto/UpdateTemplateData';

export interface INotificationTemplateApi {
  getTemplates(signal?: AbortSignal): Promise<NotificationTemplate[]>;
  getTemplate(id: number, signal?: AbortSignal): Promise<NotificationTemplate>;
  createTemplate(data: CreateTemplateData, signal?: AbortSignal): Promise<NotificationTemplate>;
  updateTemplate(id: number, data: UpdateTemplateData, signal?: AbortSignal): Promise<NotificationTemplate>;
  deactivateTemplate(id: number, signal?: AbortSignal): Promise<void>;
}
