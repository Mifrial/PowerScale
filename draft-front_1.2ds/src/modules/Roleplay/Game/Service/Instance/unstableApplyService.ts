import { UnstableApplyService } from '@/modules/Roleplay/Game/Service/UnstableApplyService';
import { getGameApi } from '@/modules/Roleplay/Game/init';

export const unstableApplyService = new UnstableApplyService(getGameApi);
