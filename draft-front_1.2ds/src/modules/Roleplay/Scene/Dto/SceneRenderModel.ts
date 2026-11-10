import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneEnclosure } from '@/modules/Roleplay/Scene/Dto/SceneEnclosure';

/** Модель, которую получает renderer. Доменное состояние остаётся снаружи. */
export interface SceneRenderModel {
  activeBoardId: number;
  boards: SceneBoard[];
  elements: SceneElement[];
  enclosures: SceneEnclosure[];
  selection: number[];
  mode: 'edit' | 'preview' | 'play';
}
