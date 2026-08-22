import type { NotifFilter } from '@/modules/Messages/Notifications/Enum/NotifFilter';

export interface NotificationFilters {
  filter?: NotifFilter;
  search?: string;
  offset: number;
  limit: number;
}
