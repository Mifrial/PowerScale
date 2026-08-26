import { CheckLaunchService } from '@/modules/Roleplay/Rule/Service/CheckLaunchService';
import { checkResolutionService } from '@/modules/Roleplay/Rule/Service/Instance/checkResolutionService';

export const checkLaunchService = new CheckLaunchService(checkResolutionService);
