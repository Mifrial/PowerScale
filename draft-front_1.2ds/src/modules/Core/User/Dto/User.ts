export interface User {
  id: number;
  name: string;
  surname?: string;
  nickname?: string;
  login: string;
  email: string | null;
  groups: number[];
  registered: number;
  active: boolean;
  lastLogin?: number;
  bypass: boolean;
  deactivatedUntil?: number | null;
  deactivateReason?: string | null;
  permissions: string[];
}
