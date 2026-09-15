import { LanguageRelatednessService } from '@/modules/Roleplay/Rule/Service/LanguageRelatednessService';
import { languageSpecService } from '@/modules/Roleplay/Rule/Service/Instance/languageSpecService';

export const languageRelatednessService = new LanguageRelatednessService(languageSpecService);
