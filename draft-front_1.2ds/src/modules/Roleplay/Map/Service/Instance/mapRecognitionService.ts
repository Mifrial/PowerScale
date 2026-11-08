import { MapRecognitionService } from '@/modules/Roleplay/Map/Service/MapRecognitionService';
import { mapGeometryService } from '@/modules/Roleplay/Map/Service/Instance/mapGeometryService';

export const mapRecognitionService = new MapRecognitionService(mapGeometryService);
