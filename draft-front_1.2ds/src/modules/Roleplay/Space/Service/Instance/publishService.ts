import { PublishService } from '@/modules/Roleplay/Space/Service/PublishService';
import { ruleValidationService, ruleDiffService } from '@/modules/Roleplay/Rule/init';

export const publishService = new PublishService(ruleValidationService, ruleDiffService);
