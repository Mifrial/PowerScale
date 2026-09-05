import type { AbilitySection } from '@/modules/Roleplay/Space/Dto/AbilitySection';

export interface AbilitySectionTreeNode extends AbilitySection {
  depth: number;
  path: string;
}
