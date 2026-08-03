import type { INotificationTemplateApi } from '@/modules/Messages/Notifications/Interface/INotificationTemplateApi';
import * as mock from '@/modules/Messages/Notifications/Mock/mockTemplates';

export const mockTemplateApi: INotificationTemplateApi = {
  getTemplates: mock.fetchTemplates,
  getTemplate: mock.fetchTemplate,
  createTemplate: mock.createTemplate,
  updateTemplate: mock.updateTemplate,
  deactivateTemplate: mock.deactivateTemplate,
};
