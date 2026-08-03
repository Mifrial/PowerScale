import type { PermissionCategory } from '@/modules/Core/User/Interface/IPermissionRegistry';

export const CHARACTER_PERMISSION_CATEGORY: PermissionCategory = {
  key: 'character',
  label: 'Персонажи',
  actions: [
    { key: 'create', label: 'Создание' },
    { key: 'view', label: 'Просмотр' },
  ],
};
