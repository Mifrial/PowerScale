import { MapHitTestService } from '@/modules/Roleplay/Map/Service/MapHitTestService';
import { mapGeometryService } from '@/modules/Roleplay/Map/Service/Instance/mapGeometryService';

/** Готовый сервис попаданий по объектам карты. */
export const mapHitTestService = new MapHitTestService(mapGeometryService);
