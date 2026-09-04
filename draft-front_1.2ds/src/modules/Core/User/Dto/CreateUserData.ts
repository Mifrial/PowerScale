export interface CreateUserData {
  name: string;
  login: string;
  email?: string | null;
  password: string;
  groups: number[];
  surname?: string;
  nickname?: string;
}
