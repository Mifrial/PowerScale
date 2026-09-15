import { ScriptLiteracyService } from '@/modules/Roleplay/Rule/Service/ScriptLiteracyService';
import { languageSpecService } from '@/modules/Roleplay/Rule/Service/Instance/languageSpecService';
import { scriptSpecService } from '@/modules/Roleplay/Rule/Service/Instance/scriptSpecService';
import { SCRIPT_ALPHABETIC_LADDER } from '@/modules/Roleplay/Rule/Constant/Script/SCRIPT_ALPHABETIC_LADDER';
import { SCRIPT_LOGOGRAPHIC_LADDER } from '@/modules/Roleplay/Rule/Constant/Script/SCRIPT_LOGOGRAPHIC_LADDER';

export const scriptLiteracyService = new ScriptLiteracyService(
  languageSpecService,
  scriptSpecService,
  SCRIPT_ALPHABETIC_LADDER,
  SCRIPT_LOGOGRAPHIC_LADDER,
);
