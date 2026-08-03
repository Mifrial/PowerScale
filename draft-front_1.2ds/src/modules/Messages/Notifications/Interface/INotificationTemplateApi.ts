import type { NotificationTemplate } from '@/modules/Messages/Notifications/Dto/NotificationTemplate';

export interface CreateTemplateData {
  key: string;
  titleTemplate: string;
  bodyTemplate: string;
  buttonsJson?: NotificationTemplate['buttonsJson'];
}

export interface UpdateTemplateData {
  key?: string;
  titleTemplate?: string;
  bodyTemplate?: string;
  buttonsJson?: NotificationTemplate['buttonsJson'];
}

export interface INotificationTemplateApi {
  getTemplates(signal?: AbortSignal): Promise<NotificationTemplate[]>;
  getTemplate(id: number, signal?: AbortSignal): Promise<NotificationTemplate>;
  createTemplate(data: CreateTemplateData, signal?: AbortSignal): Promise<NotificationTemplate>;
  updateTemplate(id: number, data: UpdateTemplateData, signal?: AbortSignal): Promise<NotificationTemplate>;
  deactivateTemplate(id: number, signal?: AbortSignal): Promise<void>;
}
