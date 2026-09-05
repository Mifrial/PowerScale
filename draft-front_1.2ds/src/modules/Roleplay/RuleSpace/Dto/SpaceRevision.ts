import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';

export interface SpaceRevision<TRule = unknown> {
  revision: number;
  publishedAt: number;
  spaceCode: string;
  spaceName: string;
  rules: TRule[];
  sections: AbilitySection[];
}
