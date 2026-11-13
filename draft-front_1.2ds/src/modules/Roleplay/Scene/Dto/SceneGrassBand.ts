import type { SceneGrassTuft } from '@/modules/Roleplay/Scene/Dto/SceneGrassTuft';

/** Полоса короткого ковра: пучки и `y`, по которому полоса сортируется. */
export interface SceneGrassBand {
  /** Нижняя граница полосы: по ней полоса встаёт в Y-сортировку. */
  y: number;
  tufts: SceneGrassTuft[];
}
