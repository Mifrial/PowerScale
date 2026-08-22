import type { TemplateForm } from '@/modules/Messages/Notifications/Dto/TemplateForm';
import type { NotificationTemplate } from '@/modules/Messages/Notifications/Dto/NotificationTemplate';
import type { NotificationButton } from '@/modules/Messages/Notifications/Dto/NotificationButton';
import type { CreateTemplateData } from '@/modules/Messages/Notifications/Dto/CreateTemplateData';
import type { UpdateTemplateData } from '@/modules/Messages/Notifications/Dto/UpdateTemplateData';

export class TemplateSpecService {
  createEmpty(): TemplateForm {
    return {
      key: '',
      titleTemplate: '',
      bodyTemplate: '',
      buttons: [],
      active: true,
    };
  }

  createEmptyButton(): NotificationButton {
    return {
      label: '',
      actionType: 'event',
      action: '',
      payload: {},
    };
  }

  addButton(buttons: NotificationButton[]): void {
    buttons.push(this.createEmptyButton());
  }

  removeButton(buttons: NotificationButton[], index: number): void {
    buttons.splice(index, 1);
  }

  fill(form: TemplateForm, template: NotificationTemplate): void {
    form.key = template.key;
    form.titleTemplate = template.titleTemplate;
    form.bodyTemplate = template.bodyTemplate;
    form.buttons = template.buttonsJson ? [...template.buttonsJson] : [];
    form.active = template.active;
  }

  buildCreatePayload(form: TemplateForm): CreateTemplateData {
    return {
      key: form.key,
      titleTemplate: form.titleTemplate,
      bodyTemplate: form.bodyTemplate,
      buttonsJson: form.buttons,
    };
  }

  buildUpdatePayload(form: TemplateForm): UpdateTemplateData {
    return {
      key: form.key,
      titleTemplate: form.titleTemplate,
      bodyTemplate: form.bodyTemplate,
      buttonsJson: form.buttons,
    };
  }
}
