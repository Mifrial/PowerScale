import type { PermissionAction } from '@/modules/Core/User/Interface/PermissionAction';

export interface PermissionCategory {
  key: string;
  label: string;
  actions: PermissionAction[];
}
