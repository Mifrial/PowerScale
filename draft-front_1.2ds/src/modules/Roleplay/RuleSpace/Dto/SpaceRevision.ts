import type { AbilitySection } from '@/modules/Roleplay/Space/Dto/AbilitySection';

export interface SpaceRevision<TRule = unknown> {
  revision: number;
  publishedAt: string;
  spaceCode: string;
  spaceName: string;
  rules: TRule[];
  /** Навигационное дерево каталога способностей. Старые ревизии могут не содержать поле. */
  sections?: AbilitySection[];
}
