import type { ISceneRenderer } from '@/modules/Roleplay/Scene/Interface/ISceneRenderer';
import { PixiSceneRenderer } from '@/modules/Roleplay/Scene/Service/PixiSceneRenderer';

/** Создаёт новый PixiJS-рендерер локации. */
export function createPixiSceneRenderer(): ISceneRenderer {
  return new PixiSceneRenderer();
}
