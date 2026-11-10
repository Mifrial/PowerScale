import type { SceneDetail } from '@/modules/Roleplay/Scene/Dto/SceneDetail';
import { sceneFixtures } from '@/modules/Roleplay/Scene/Mock/mockSceneFixtures';

/** Локация mock по id. */
export function mockGetScene(id: number): SceneDetail {
  const scene = sceneFixtures.find((item) => item.id === id);
  if (scene === undefined) {
    throw new Error('SCENE_NOT_FOUND');
  }

  return structuredClone(scene);
}
