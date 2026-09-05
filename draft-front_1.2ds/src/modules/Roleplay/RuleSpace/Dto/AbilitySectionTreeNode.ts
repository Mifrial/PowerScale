import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';

export interface AbilitySectionTreeNode extends AbilitySection {
  depth: number;
  path: string;
}
