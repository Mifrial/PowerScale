import type { SceneSummary } from '@/modules/Roleplay/Scene/Dto/SceneSummary';

/** Страница локаций. */
export interface ScenePage {
  items: SceneSummary[];
  total: number;
}
