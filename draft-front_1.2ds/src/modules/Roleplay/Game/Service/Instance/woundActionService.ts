import { WoundActionService } from '@/modules/Roleplay/Game/Service/WoundActionService';
import { getGameApi } from '@/modules/Roleplay/Game/init';

export const woundActionService = new WoundActionService(getGameApi);
