import type { NotificationButton } from '@/modules/Messages/Notifications/Dto/NotificationButton';

export interface TemplateForm {
  key: string;
  titleTemplate: string;
  bodyTemplate: string;
  buttons: NotificationButton[];
  active: boolean;
}
