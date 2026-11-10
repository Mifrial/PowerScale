import { SceneRibbonService } from '@/modules/Roleplay/Scene/Service/SceneRibbonService';
import { sceneCurveService } from '@/modules/Roleplay/Scene/Service/Instance/sceneCurveService';

/** Готовый экземпляр сервиса ленты. */
export const sceneRibbonService = new SceneRibbonService(sceneCurveService);
