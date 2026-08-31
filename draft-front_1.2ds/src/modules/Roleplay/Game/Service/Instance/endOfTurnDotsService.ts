import { EndOfTurnDotsService } from '@/modules/Roleplay/Game/Service/EndOfTurnDotsService';
import { getGameApi } from '@/modules/Roleplay/Game/init';

export const endOfTurnDotsService = new EndOfTurnDotsService(getGameApi);
