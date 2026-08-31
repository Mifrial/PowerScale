import { ExhaustionCheckService } from '@/modules/Roleplay/Game/Service/ExhaustionCheckService';
import { getGameApi } from '@/modules/Roleplay/Game/init';

export const exhaustionCheckService = new ExhaustionCheckService(getGameApi);
