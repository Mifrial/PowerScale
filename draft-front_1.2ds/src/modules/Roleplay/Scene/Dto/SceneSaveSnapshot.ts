import type { SceneSaveBoard } from '@/modules/Roleplay/Scene/Dto/SceneSaveBoard';
import type { SceneSaveElement } from '@/modules/Roleplay/Scene/Dto/SceneSaveElement';
import type { SceneSaveEnclosure } from '@/modules/Roleplay/Scene/Dto/SceneSaveEnclosure';

/** Полный снимок для `scene.save`. */
export interface SceneSaveSnapshot {
  name: string;
  description: string;
  resolution: number;
  schemaVersion: number;
  boards: SceneSaveBoard[];
  elements: SceneSaveElement[];
  enclosures: SceneSaveEnclosure[];
}
