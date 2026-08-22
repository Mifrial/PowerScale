import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { defineAsyncComponent } from 'vue';
import type { IMacroApi } from '@/modules/Roleplay/Game/Interface/IMacroApi';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import { rollService } from '@/modules/Roleplay/Game/Service/Instance/rollService';

export { rollEngine } from '@/modules/Roleplay/Game/Service/Roll/Instance/rollEngine';
import {
  registerCommandHandler,
  registerContentRenderer,
  registerToolbarExtension,
  registerAttachmentProcessor,
  registerChatTypes,
  registerChatTabs,
  registerChatRulesContextBuilder,
  registerChatRulesProvider,
} from '@/modules/Messages/Chat/init';
import { registerProfileSection, registerPermissionCategory } from '@/modules/Core/User/init';
import { GAME_PERMISSION_CATEGORY } from '@/modules/Roleplay/Game/Constant/permissions';
import { buildChatRulesContext } from '@/modules/Roleplay/Game/Utils/chatRulesContext';
import { gameChatRulesProvider } from '@/modules/Roleplay/Game/Chat/gameChatRulesProvider';
import { actualRulesChatRulesProvider } from '@/modules/Roleplay/Game/Chat/actualRulesChatRulesProvider';
import { GAME_CHAT_TYPES } from '@/modules/Roleplay/Game/Constant/Chat/GAME_CHAT_TYPES';
import { GAME_CHAT_TABS } from '@/modules/Roleplay/Game/Constant/Chat/GAME_CHAT_TABS';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { registerGameSheetRoles } from '@/modules/Roleplay/Game/Sheet/gameSheetRoles';
import { registerCharacterCardExtension } from '@/modules/Roleplay/Character/init';

// Компоненты регистрируются асинхронно (defineAsyncComponent): init.ts не тянет .vue/CSS
// при импорте в node-тестах (routes → store → init) и код-сплитится по месту использования.
const DiceRollResult = defineAsyncComponent(() => import('@/modules/Roleplay/Game/Component/DiceRollResult.vue'));
const RollFormExtension = defineAsyncComponent(() => import('@/modules/Roleplay/Game/Component/RollFormExtension.vue'));
const MacroBarExtension = defineAsyncComponent(() => import('@/modules/Roleplay/Game/Component/MacroBarExtension.vue'));
const MacrosSection = defineAsyncComponent(() => import('@/modules/Roleplay/Game/Component/MacrosSection.vue'));

export function registerMacroApi(api: IMacroApi): void {
  serviceLocator.set('Roleplay.Game.Service.MacroApi', api);
}

export function getMacroApi(): IMacroApi {
  return serviceLocator.get('Roleplay.Game.Service.MacroApi');
}

export function registerGameApi(api: IGameApi): void {
  serviceLocator.set('Roleplay.Game.Service.GameApi', api);
}

export function getGameApi(): IGameApi {
  return serviceLocator.get('Roleplay.Game.Service.GameApi');
}

export function registerGameModule(): void {
  registerPermissionCategory(GAME_PERMISSION_CATEGORY);
  registerGameSheetRoles();
  // Контекст правил чатов (чипы/ссылки/броски): сборщик RollEngine + провайдеры ревизий.
  registerChatRulesContextBuilder(buildChatRulesContext);
  registerChatRulesProvider(gameChatRulesProvider);
  registerChatRulesProvider(actualRulesChatRulesProvider);
  registerCharacterCardExtension({
    id: 'character-visibility',
    component: defineAsyncComponent(() => import('@/modules/Roleplay/Game/Component/CharacterVisibilitySection.vue')),
  });
  registerChatTypes(GAME_CHAT_TYPES);
  registerChatTabs(GAME_CHAT_TABS);
  registerCommandHandler({ command: 'roll', parse: (input) => rollService.parseRollCommand(input) });
  registerContentRenderer({ type: ROLL_ATTACHMENT_TYPE, component: DiceRollResult });
  // Процессор идемпотентен: готовый результат (payload с rolls) проходит как есть — нужен для
  // сообщений с заранее посчитанными бросками (шкала инициативы «Проверка на инициативу»),
  // чтобы mock не пере-бросал кубы и порядок в чате совпадал с порядком хода.
  registerAttachmentProcessor<DiceRollSpec | DiceRollResult>({
    type: ROLL_ATTACHMENT_TYPE,
    process: (payload: DiceRollSpec | DiceRollResult): Promise<DiceRollResult> => {
      if ('rolls' in payload) return Promise.resolve(payload as DiceRollResult);

      return Promise.resolve(rollService.computeRollResult(payload as DiceRollSpec));
    },
    describe: (payload: DiceRollSpec | DiceRollResult) => {
      if ('rolls' in payload) return 'Бросок';
      const adv = payload.adv ? (payload.adv > 0 ? ` +${payload.adv}` : ` ${payload.adv}`) : '';
      const label = payload.label ? ` (${payload.label})` : '';

      return `${payload.diceCount}к${payload.dieFaces}${adv}${label}`;
    },
  });
  registerToolbarExtension({ id: 'roll-form', component: RollFormExtension, placement: 'actions' });
  registerToolbarExtension({ id: 'macro-bar', component: MacroBarExtension });
  registerProfileSection({ id: 'macros', component: MacrosSection });
}
