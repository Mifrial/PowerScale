import type { SceneDetail } from '@/modules/Roleplay/Scene/Dto/SceneDetail';
import { sceneFixtures } from '@/modules/Roleplay/Scene/Mock/mockSceneFixtures';

/** Сохраняет снимок mock: заменяет существующую локацию или добавляет новую. */
export function mockSaveScene(scene: SceneDetail): SceneDetail {
  const index = sceneFixtures.findIndex((item) => item.id === scene.id);
  const saved = structuredClone(scene);
  if (index >= 0) {
    sceneFixtures[index] = saved;
  } else {
    sceneFixtures.push(saved);
  }

  return structuredClone(saved);
}
