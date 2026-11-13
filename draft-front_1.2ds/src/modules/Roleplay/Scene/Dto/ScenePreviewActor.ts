import type { SceneActor } from '@/modules/Roleplay/Scene/Dto/SceneActor';

/** Актор предпросмотра: стендалка плюс радиус обзора. */
export interface ScenePreviewActor extends SceneActor {
  /** Радиус обзора в единицах сцены. */
  sightRadius: number;
  /** Роль маркера, из которого создан актор. */
  role: string;
}
