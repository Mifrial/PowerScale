import { InjuryCheckService } from '@/modules/Roleplay/Game/Service/InjuryCheckService';
import { getGameApi } from '@/modules/Roleplay/Game/init';

export const injuryCheckService = new InjuryCheckService(getGameApi);
