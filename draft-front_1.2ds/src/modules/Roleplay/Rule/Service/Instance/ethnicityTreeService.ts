import { EthnicityTreeService } from '@/modules/Roleplay/Rule/Service/EthnicityTreeService';
import { ethnicitySpecService } from '@/modules/Roleplay/Rule/Service/Instance/ethnicitySpecService';

export const ethnicityTreeService = new EthnicityTreeService(ethnicitySpecService);
