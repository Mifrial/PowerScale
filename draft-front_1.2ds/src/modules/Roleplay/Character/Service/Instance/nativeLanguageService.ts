import { NativeLanguageService } from '@/modules/Roleplay/Character/Service/NativeLanguageService';
import { languageRelatednessService } from '@/modules/Roleplay/Rule/init';

export const nativeLanguageService = new NativeLanguageService(languageRelatednessService);
