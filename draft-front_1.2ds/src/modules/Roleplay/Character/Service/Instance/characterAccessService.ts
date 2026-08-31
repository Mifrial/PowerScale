import { CharacterAccessService } from '@/modules/Roleplay/Character/Service/CharacterAccessService';
import { sheetAccessService } from '@/modules/Roleplay/Character/Service/Instance/sheetAccessService';

export const characterAccessService = new CharacterAccessService(sheetAccessService);
