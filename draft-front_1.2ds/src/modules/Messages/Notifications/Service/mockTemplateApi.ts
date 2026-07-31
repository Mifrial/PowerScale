import type { INotificationTemplateApi } from '../Interface/INotificationTemplateApi'
import * as mock from './mockTemplates'

export const mockTemplateApi: INotificationTemplateApi = {
  getTemplates: mock.fetchTemplates,
  getTemplate: mock.fetchTemplate,
  createTemplate: mock.createTemplate,
  updateTemplate: mock.updateTemplate,
  deleteTemplate: mock.deleteTemplate,
}
