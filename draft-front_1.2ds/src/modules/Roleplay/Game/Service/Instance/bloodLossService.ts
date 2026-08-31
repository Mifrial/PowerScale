import { BloodLossService } from '@/modules/Roleplay/Game/Service/BloodLossService';
import { getGameApi } from '@/modules/Roleplay/Game/init';

export const bloodLossService = new BloodLossService(getGameApi);
