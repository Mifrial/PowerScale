import type { NotificationTemplate } from '@/modules/Messages/Notifications/Dto/NotificationTemplate';

export interface CreateTemplateData {
  key: string;
  titleTemplate: string;
  bodyTemplate: string;
  buttonsJson?: NotificationTemplate['buttonsJson'];
}
