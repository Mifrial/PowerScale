import { RuleValidationService } from '@/modules/Roleplay/Rule/Service/RuleValidationService';
import { abilitySpecService } from '@/modules/Roleplay/Rule/Service/Instance/abilitySpecService';

export const ruleValidationService = new RuleValidationService(abilitySpecService);
