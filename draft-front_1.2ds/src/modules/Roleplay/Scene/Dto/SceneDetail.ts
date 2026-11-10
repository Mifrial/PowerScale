import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneEnclosure } from '@/modules/Roleplay/Scene/Dto/SceneEnclosure';
import type { SceneSummary } from '@/modules/Roleplay/Scene/Dto/SceneSummary';

/** Полный снимок локации. */
export interface SceneDetail extends SceneSummary {
  canEdit: boolean;
  boards: SceneBoard[];
  elements: SceneElement[];
  enclosures: SceneEnclosure[];
}
