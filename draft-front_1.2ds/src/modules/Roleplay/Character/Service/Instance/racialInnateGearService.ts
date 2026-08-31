import { RacialInnateGearService } from '@/modules/Roleplay/Character/Service/RacialInnateGearService';
import { raceSpecService } from '@/modules/Roleplay/Rule/init';

export const racialInnateGearService = new RacialInnateGearService(raceSpecService);
