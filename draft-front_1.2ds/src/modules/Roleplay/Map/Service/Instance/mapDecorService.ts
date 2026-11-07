import { MapDecorService } from '@/modules/Roleplay/Map/Service/MapDecorService';
import { mapGeometryService } from '@/modules/Roleplay/Map/Service/Instance/mapGeometryService';

/** Готовый сервис раскладки декора области. */
export const mapDecorService = new MapDecorService(mapGeometryService);
