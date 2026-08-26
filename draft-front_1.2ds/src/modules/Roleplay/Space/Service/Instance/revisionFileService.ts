import { RevisionFileService } from '@/modules/Roleplay/Space/Service/RevisionFileService';
import { ruleDiffService } from '@/modules/Roleplay/Rule/init';

export const revisionFileService = new RevisionFileService(ruleDiffService);
