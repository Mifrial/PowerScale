import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { registerPermissionCategory, registerAdminSection } from '@/modules/Core/User/init';
import type { IKeywordApi } from '@/modules/Roleplay/Keyword/Interface/IKeywordApi';
import { KEYWORD_PERMISSION_CATEGORY } from '@/modules/Roleplay/Keyword/Constant/Permission/KEYWORD_PERMISSION_CATEGORY';
import { KEYWORDS_ADMIN_SECTION } from '@/modules/Roleplay/Keyword/Constant/Permission/KEYWORDS_ADMIN_SECTION';

export { useKeywords } from '@/modules/Roleplay/Keyword/Composables/useKeywords';

export function registerKeywordApi(api: IKeywordApi): void {
  serviceLocator.set('Roleplay.Keyword.Service.KeywordApi', api);
}

export function getKeywordApi(): IKeywordApi {
  return serviceLocator.get('Roleplay.Keyword.Service.KeywordApi');
}

export function registerKeywordModule(): void {
  registerPermissionCategory(KEYWORD_PERMISSION_CATEGORY);
  registerAdminSection(KEYWORDS_ADMIN_SECTION);
}
