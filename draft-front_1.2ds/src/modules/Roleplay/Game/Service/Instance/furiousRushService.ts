import { FuriousRushService } from '@/modules/Roleplay/Game/Service/FuriousRushService';
import { getGameApi } from '@/modules/Roleplay/Game/init';

export const furiousRushService = new FuriousRushService(getGameApi);
