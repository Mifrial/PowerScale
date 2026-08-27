import { IMPORT_NAME_DOMAIN_ALIASES } from '@/modules/Roleplay/Rule/Constant/Ability/IMPORT_NAME_DOMAIN_ALIASES';
import { ImportedRuleNameService } from '@/modules/Roleplay/Rule/Service/ImportedRuleNameService';

export const importedRuleNameService = new ImportedRuleNameService(IMPORT_NAME_DOMAIN_ALIASES);
