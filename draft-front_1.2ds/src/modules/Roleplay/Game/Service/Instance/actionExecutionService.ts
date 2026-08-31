import { ActionExecutionService } from '@/modules/Roleplay/Game/Service/ActionExecutionService';
import { getGameApi } from '@/modules/Roleplay/Game/init';

export const actionExecutionService = new ActionExecutionService(getGameApi);
