import { ActionModifierService } from '@/modules/Roleplay/Game/Service/ActionModifierService';
import { getGameApi } from '@/modules/Roleplay/Game/init';

export const actionModifierService = new ActionModifierService(getGameApi);
