import { SceneGeometryService } from '@/modules/Roleplay/Scene/Service/SceneGeometryService';
import { SceneHitTestService } from '@/modules/Roleplay/Scene/Service/SceneHitTestService';
import { SceneViewportService } from '@/modules/Roleplay/Scene/Service/SceneViewportService';

/** Готовый hit-test локации. */
export const sceneHitTestService = new SceneHitTestService(new SceneViewportService(), new SceneGeometryService());
