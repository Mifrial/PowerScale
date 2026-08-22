import { describe, it, expect } from 'vitest';
import { TemplateSpecService } from '@/modules/Messages/Notifications/Service/Spec/TemplateSpecService';
import type { NotificationButton } from '@/modules/Messages/Notifications/Dto/NotificationButton';
import type { TemplateForm } from '@/modules/Messages/Notifications/Dto/TemplateForm';
import type { NotificationTemplate } from '@/modules/Messages/Notifications/Dto/NotificationTemplate';

const service = new TemplateSpecService();

describe('TemplateSpecService.createEmpty/createEmptyButton', () => {
  it('createEmpty возвращает пустую форму без кнопок', () => {
    const form = service.createEmpty();
    expect(form.key).toBe('');
    expect(form.titleTemplate).toBe('');
    expect(form.bodyTemplate).toBe('');
    expect(form.buttons).toEqual([]);
    expect(form.active).toBe(true);
  });
  it('createEmptyButton создаёт кнопку по умолчанию', () => {
    const btn = service.createEmptyButton();
    expect(btn).toEqual({ label: '', actionType: 'event', action: '', payload: {} });
  });
});

describe('TemplateSpecService.addButton/removeButton', () => {
  it('addButton добавляет кнопку в конец', () => {
    const buttons: NotificationButton[] = [{ label: 'A', actionType: 'url', action: '/x', payload: {} }];
    service.addButton(buttons);
    expect(buttons).toHaveLength(2);
    expect(buttons[1]).toEqual({ label: '', actionType: 'event', action: '', payload: {} });
  });
  it('removeButton удаляет кнопку по индексу', () => {
    const buttons: NotificationButton[] = [
      { label: 'A', actionType: 'event', action: 'a', payload: {} },
      { label: 'B', actionType: 'event', action: 'b', payload: {} },
    ];
    service.removeButton(buttons, 0);
    expect(buttons.map((b) => b.action)).toEqual(['b']);
  });
});

describe('TemplateSpecService.fill', () => {
  it('копирует поля шаблона в форму', () => {
    const form = service.createEmpty();
    const template: NotificationTemplate = {
      id: 5,
      key: 'game_invite',
      titleTemplate: 'Приглашение',
      bodyTemplate: '<p>Тело</p>',
      buttonsJson: [{ label: 'Принять', actionType: 'event', action: 'accept', payload: {} }],
      active: true,
    };
    service.fill(form, template);
    expect(form.key).toBe('game_invite');
    expect(form.titleTemplate).toBe('Приглашение');
    expect(form.bodyTemplate).toBe('<p>Тело</p>');
    expect(form.buttons).toEqual(template.buttonsJson);
    expect(form.active).toBe(true);
  });
  it('без buttonsJson — форма получает пустой массив', () => {
    const form = service.createEmpty();
    const template: NotificationTemplate = {
      id: 5,
      key: 'k',
      titleTemplate: 't',
      bodyTemplate: 'b',
      active: true,
    };
    service.fill(form, template);
    expect(form.buttons).toEqual([]);
  });
});

describe('TemplateSpecService.buildCreatePayload/buildUpdatePayload', () => {
  const form: TemplateForm = {
    key: 'game_invite',
    titleTemplate: 'Приглашение',
    bodyTemplate: '<p>Тело</p>',
    buttons: [{ label: 'Принять', actionType: 'event', action: 'accept', payload: {} }],
    active: true,
  };

  it('create: переносит поля и кнопки', () => {
    const payload = service.buildCreatePayload(form);
    expect(payload.key).toBe('game_invite');
    expect(payload.titleTemplate).toBe('Приглашение');
    expect(payload.buttonsJson).toEqual(form.buttons);
  });
  it('update: переносит поля и кнопки', () => {
    const payload = service.buildUpdatePayload(form);
    expect(payload.key).toBe('game_invite');
    expect(payload.buttonsJson).toEqual(form.buttons);
  });
  it('пустые кнопки отдаются массивом [], а не undefined (можно очистить)', () => {
    const emptyForm: TemplateForm = { ...form, buttons: [] };
    expect(service.buildCreatePayload(emptyForm).buttonsJson).toEqual([]);
    expect(service.buildUpdatePayload(emptyForm).buttonsJson).toEqual([]);
  });
  it('созданный payload можно снова залить в форму (round-trip)', () => {
    const payload = service.buildCreatePayload(form);
    const restored = service.createEmpty();
    service.fill(restored, { id: 1, ...payload, active: true } as NotificationTemplate);
    expect(restored.key).toBe(form.key);
    expect(restored.buttons).toEqual(form.buttons);
  });
});
