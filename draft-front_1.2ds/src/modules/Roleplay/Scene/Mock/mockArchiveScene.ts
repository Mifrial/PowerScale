import { sceneFixtures } from '@/modules/Roleplay/Scene/Mock/mockSceneFixtures';

/** Архивирует локацию mock. */
export function mockArchiveScene(id: number): void {
  const scene = sceneFixtures.find((item) => item.id === id);
  if (scene !== undefined) {
    scene.isArchived = true;
  }
}
