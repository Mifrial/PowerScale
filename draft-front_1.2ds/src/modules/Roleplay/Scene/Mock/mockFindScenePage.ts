import type { ScenePage } from '@/modules/Roleplay/Scene/Dto/ScenePage';
import { sceneFixtures } from '@/modules/Roleplay/Scene/Mock/mockSceneFixtures';

/** Список локаций mock. */
export function mockFindScenePage(): ScenePage {
  return { items: sceneFixtures.map((scene) => ({ ...scene })), total: sceneFixtures.length };
}
