import { SceneVertexService } from '@/modules/Roleplay/Scene/Service/SceneVertexService';
import { sceneCurveService } from '@/modules/Roleplay/Scene/Service/Instance/sceneCurveService';
import { sceneRibbonService } from '@/modules/Roleplay/Scene/Service/Instance/sceneRibbonService';

/** Готовый сервис вершин и ручек. */
export const sceneVertexService = new SceneVertexService(sceneCurveService, sceneRibbonService);
