import type { PermissionCategory } from '@/modules/Core/User/Interface/IPermissionRegistry'

export const GAME_PERMISSION_CATEGORY: PermissionCategory = {
  key: 'game',
  label: 'Игры',
  actions: [
    { key: 'create', label: 'Создание' },
    { key: 'view_all', label: 'Просмотр всех' },
    { key: 'edit_all', label: 'Редактирование всех' },
  ],
}
