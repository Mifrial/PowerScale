import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { registerPermissionCategory, registerAdminSection, accessService } from '@/modules/Core/User/init';
import type { User } from '@/modules/Core/User/Dto/User';
import type { IKeywordApi } from '@/modules/Roleplay/Keyword/Interface/IKeywordApi';
import { KEYWORD_PERMISSION_CATEGORY } from '@/modules/Roleplay/Keyword/Constant/Permission/KEYWORD_PERMISSION_CATEGORY';
import { KEYWORDS_ADMIN_SECTION } from '@/modules/Roleplay/Keyword/Constant/Permission/KEYWORDS_ADMIN_SECTION';
import { KEYWORDS_MENU_ITEM } from '@/modules/Roleplay/Keyword/Constant/Navigation/KEYWORDS_MENU_ITEM';
import { registerMenuContribution, registerMenuItem } from '@/modules/Core/UI/init';

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
  registerMenuContribution({
    id: 'keyword.admin',
    apply: (actor) => {
      const user = actor === null || actor === undefined ? null : (actor as User);
      if (!accessService.hasAnyPermission(user, ['keyword.view'])) {
        return;
      }
      registerMenuItem(KEYWORDS_MENU_ITEM);
    },
  });
}
