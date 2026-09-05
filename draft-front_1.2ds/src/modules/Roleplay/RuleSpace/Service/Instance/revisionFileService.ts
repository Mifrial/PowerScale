import { RevisionFileService } from '@/modules/Roleplay/Space/Service/RevisionFileService';
import { RULE_TYPE_LABELS, ruleDiffService } from '@/modules/Roleplay/Rule/init';

export const revisionFileService = new RevisionFileService(ruleDiffService, RULE_TYPE_LABELS);
