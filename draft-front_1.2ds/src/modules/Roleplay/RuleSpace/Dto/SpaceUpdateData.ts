import type { PublishedRead } from '@/modules/Roleplay/RuleSpace/Enum/PublishedRead';

export interface SpaceUpdateData {
  name?: string;
  description?: string;
  publishedRead?: PublishedRead;
}
