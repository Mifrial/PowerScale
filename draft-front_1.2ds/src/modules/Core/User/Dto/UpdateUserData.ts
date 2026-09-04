export interface UpdateUserData {
  name?: string;
  surname?: string;
  nickname?: string;
  email?: string | null;
  groups?: number[];
  active?: boolean;
}
