import { MapRibbonService } from '@/modules/Roleplay/Map/Service/MapRibbonService';
import { mapGeometryService } from '@/modules/Roleplay/Map/Service/Instance/mapGeometryService';

/** Готовый сервис геометрии ленты. */
export const mapRibbonService = new MapRibbonService(mapGeometryService);
