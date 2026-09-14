import { CommittedActionFlowService } from '@/modules/Roleplay/Game/Service/CommittedActionFlowService';
import { getGameApi } from '@/modules/Roleplay/Game/init';

export const committedActionFlowService = new CommittedActionFlowService(getGameApi);
