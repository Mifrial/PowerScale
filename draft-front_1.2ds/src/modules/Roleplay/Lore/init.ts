import { defineAsyncComponent } from 'vue';
import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { registerPermissionCategory } from '@/modules/Core/User/init';
import { registerMenuItem } from '@/modules/Core/UI/init';
import { registerInlineRenderer, registerTokenSource } from '@/modules/Messages/Inline/init';
import { LORE_INLINE_SOURCE } from '@/modules/Roleplay/Lore/Constant/Inline/LORE_INLINE_SOURCE';
import { LORE_MENU_ITEM } from '@/modules/Roleplay/Lore/Constant/Navigation/LORE_MENU_ITEM';
import { LORE_PERMISSION_CATEGORY } from '@/modules/Roleplay/Lore/Constant/Permission/LORE_PERMISSION_CATEGORY';
import type { ILoreApi } from '@/modules/Roleplay/Lore/Interface/ILoreApi';
import { LoreInlineArticleService } from '@/modules/Roleplay/Lore/Service/Inline/LoreInlineArticleService';
import { LoreInlineTokenSourceService } from '@/modules/Roleplay/Lore/Service/Inline/LoreInlineTokenSourceService';
import { LoreInlineValueService } from '@/modules/Roleplay/Lore/Service/Inline/LoreInlineValueService';

export const loreInlineValueService = new LoreInlineValueService();
export const loreInlineArticleService = new LoreInlineArticleService(() => getLoreApi(), loreInlineValueService);
const loreInlineTokenSourceService = new LoreInlineTokenSourceService(() => getLoreApi(), loreInlineValueService);

export function registerLoreApi(api: ILoreApi): void {
  serviceLocator.set('Roleplay.Lore.Service.LoreApi', api);
}

export function getLoreApi(): ILoreApi {
  return serviceLocator.get('Roleplay.Lore.Service.LoreApi');
}

export function registerLoreModule(): void {
  registerPermissionCategory(LORE_PERMISSION_CATEGORY);
  registerMenuItem(LORE_MENU_ITEM);
  registerInlineRenderer({
    type: LORE_INLINE_SOURCE.type,
    component: defineAsyncComponent(() => import('@/modules/Roleplay/Lore/Component/Inline/LoreInlineChip.vue')),
    describe: (segment) => {
      const override = segment.params[1]?.trim();
      if (override) return override;
      const parsed = loreInlineValueService.parse(segment.params[0] ?? '');

      return parsed ? loreInlineArticleService.peek(parsed.worldCode, parsed.articleCode) : null;
    },
  });
  registerTokenSource(loreInlineTokenSourceService.build());
}

export { loreTocService } from '@/modules/Roleplay/Lore/Service/Instance/loreTocService';
