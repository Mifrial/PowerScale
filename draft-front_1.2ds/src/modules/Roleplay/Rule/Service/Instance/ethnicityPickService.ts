import { EthnicityPickService } from '@/modules/Roleplay/Rule/Service/EthnicityPickService';
import { ethnicitySpecService } from '@/modules/Roleplay/Rule/Service/Instance/ethnicitySpecService';
import { ethnicityTreeService } from '@/modules/Roleplay/Rule/Service/Instance/ethnicityTreeService';
import { languageRelatednessService } from '@/modules/Roleplay/Rule/Service/Instance/languageRelatednessService';

export const ethnicityPickService = new EthnicityPickService(
  ethnicitySpecService,
  ethnicityTreeService,
  languageRelatednessService,
);
