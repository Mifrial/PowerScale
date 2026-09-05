export interface Space {
  id: number;
  code: string;
  name: string;
  description: string;
  revision: number;
  active: boolean;
  createdAt: string;
  rulesCount: number;
}
