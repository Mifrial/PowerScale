import type { PublishedRead } from '@/modules/Roleplay/RuleSpace/Enum/PublishedRead';

export interface Space {
  id: number;
  code: string;
  name: string;
  description: string;
  ownerId: number;
  publishedRead: PublishedRead;
  revision: number;
  active: boolean;
  createdAt: number;
  rulesCount: number;
}
