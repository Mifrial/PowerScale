import { RevisionFileService } from '@/modules/Roleplay/RuleSpace/Service/RevisionFileService';
import { ruleDiffService } from '@/modules/Roleplay/Rule/init';

export const revisionFileService = new RevisionFileService(ruleDiffService, () => Math.floor(Date.now() / 1000));
