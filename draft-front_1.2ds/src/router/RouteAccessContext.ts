import type { User } from '@/modules/Core/User/Dto/User';

export interface RouteAccessContext {
  isAuthenticated: boolean;
  isGuest: boolean;
  user: User | null;
}
