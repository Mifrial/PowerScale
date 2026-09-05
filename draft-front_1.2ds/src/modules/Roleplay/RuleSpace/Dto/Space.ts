export interface Space {
  id: number;
  code: string;
  name: string;
  description: string;
  ownerId: number;
  revision: number;
  active: boolean;
  createdAt: number;
  rulesCount: number;
}
