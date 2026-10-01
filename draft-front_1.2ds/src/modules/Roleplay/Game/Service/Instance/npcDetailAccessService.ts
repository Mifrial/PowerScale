import { sheetAccessService } from '@/modules/Roleplay/Character/init';
import { gameAccessService } from '@/modules/Roleplay/Game/Service/Instance/gameAccessService';
import { NpcDetailAccessService } from '@/modules/Roleplay/Game/Service/NpcDetailAccessService';

export const npcDetailAccessService = new NpcDetailAccessService(gameAccessService, sheetAccessService);
