import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { defineAsyncComponent } from 'vue';
import type { IMacroApi } from '@/modules/Roleplay/Game/Interface/IMacroApi';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import { rollService } from '@/modules/Roleplay/Game/Service/Instance/rollService';
import { simpleCheckRollService } from '@/modules/Roleplay/Game/Service/Instance/simpleCheckRollService';

import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/Service/Instance/aggregateSourceDeltasService';

export { rollEngine } from '@/modules/Roleplay/Game/Service/Roll/Instance/rollEngine';
import {
  registerCommandHandler,
  registerContentRenderer,
  registerToolbarExtension,
  registerAttachmentProcessor,
  registerChatTypes,
  registerChatTabs,
  registerChatRulesProvider,
  registerInlineRenderer,
} from '@/modules/Messages/Chat/init';
import { registerProfileSection, registerPermissionCategory } from '@/modules/Core/User/init';
import { GAME_PERMISSION_CATEGORY } from '@/modules/Roleplay/Game/Constant/permissions';
import { gameChatRulesProvider } from '@/modules/Roleplay/Game/Chat/gameChatRulesProvider';
import { actualRulesChatRulesProvider } from '@/modules/Roleplay/Game/Chat/actualRulesChatRulesProvider';
import { GAME_CHAT_TYPES } from '@/modules/Roleplay/Game/Constant/Chat/GAME_CHAT_TYPES';
import { GAME_CHAT_TABS } from '@/modules/Roleplay/Game/Constant/Chat/GAME_CHAT_TABS';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { ATTACK_CALC_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Attack/ATTACK_CALC_ATTACHMENT_TYPE';
import { INJURY_DETAILS_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Injury/INJURY_DETAILS_ATTACHMENT_TYPE';
import { DOT_TICK_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Dot/DOT_TICK_ATTACHMENT_TYPE';
import { registerGameSheetRoles } from '@/modules/Roleplay/Game/Sheet/gameSheetRoles';
import { inGameSheetSource } from '@/modules/Roleplay/Game/Sheet/inGameSheetSource';
import { registerCharacterCardExtension, registerInGameSheetSource } from '@/modules/Roleplay/Character/init';

// Компоненты регистрируются асинхронно (defineAsyncComponent): init.ts не тянет .vue/CSS
// при импорте в node-тестах (routes → store → init) и код-сплитится по месту использования.
const DiceRollResult = defineAsyncComponent(() => import('@/modules/Roleplay/Game/Component/DiceRollResult.vue'));
const AttackCalcAttachment = defineAsyncComponent(
  () => import('@/modules/Roleplay/Game/Component/AttackCalcAttachment.vue'),
);
const InjuryDetailsAttachment = defineAsyncComponent(
  () => import('@/modules/Roleplay/Game/Component/InjuryDetailsAttachment.vue'),
);
const DotTickAttachment = defineAsyncComponent(() => import('@/modules/Roleplay/Game/Component/DotTickAttachment.vue'));
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
  registerInGameSheetSource(inGameSheetSource);
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
  registerContentRenderer({
    type: ATTACK_CALC_ATTACHMENT_TYPE,
    component: AttackCalcAttachment,
    layout: 'meta',
  });
  registerContentRenderer({
    type: INJURY_DETAILS_ATTACHMENT_TYPE,
    component: InjuryDetailsAttachment,
    layout: 'meta',
  });
  registerContentRenderer({
    type: DOT_TICK_ATTACHMENT_TYPE,
    component: DotTickAttachment,
    layout: 'meta',
  });
  const GameEntityChip = defineAsyncComponent(
    () => import('@/modules/Roleplay/Game/Component/Chat/GameEntityChip.vue'),
  );
  const ProcessContinueChip = defineAsyncComponent(
    () => import('@/modules/Roleplay/Game/Component/Chat/ProcessContinueChip.vue'),
  );
  registerInlineRenderer({
    type: 'character',
    component: GameEntityChip,
    describe: (segment) => segment.params.slice(1).join(',').trim() || segment.params[0] || null,
  });
  registerInlineRenderer({
    type: 'npc',
    component: GameEntityChip,
    describe: (segment) => segment.params.slice(1).join(',').trim() || segment.params[0] || null,
  });
  registerInlineRenderer({
    type: 'process-continue',
    component: ProcessContinueChip,
    describe: () => 'продолжить атаку',
  });
  // Процессор идемпотентен: готовый результат (payload с rolls) проходит как есть — нужен для
  // сообщений с заранее посчитанными бросками (шкала инициативы «Проверка на инициативу»),
  // чтобы mock не пере-бросал кубы и порядок в чате совпадал с порядком хода.
  registerAttachmentProcessor<DiceRollSpec | DiceRollResult>({
    type: ROLL_ATTACHMENT_TYPE,
    process: (payload: DiceRollSpec | DiceRollResult): Promise<DiceRollResult> => {
      if (rollService.isDiceRollResult(payload)) return Promise.resolve(payload);

      return Promise.resolve(simpleCheckRollService.withSimpleCheckZero(rollService.computeRollResult(payload)));
    },
    describe: (payload: DiceRollSpec | DiceRollResult) => {
      if (rollService.isDiceRollResult(payload)) return 'Бросок';
      const net = aggregateSourceDeltasService.netSourceDelta(payload.advantages);
      const adv = net ? (net > 0 ? ` +${net}` : ` ${net}`) : '';
      const label = payload.label ? ` (${payload.label})` : '';

      return `${rollService.formatPoolNotation(payload)} · ${rollService.formatEfficiencyLabel(payload)}${adv}${label}`;
    },
  });
  registerToolbarExtension({ id: 'roll-form', component: RollFormExtension, placement: 'actions' });
  registerToolbarExtension({ id: 'macro-bar', component: MacroBarExtension });
  registerProfileSection({ id: 'macros', component: MacrosSection });
}

export { gameAccessService } from '@/modules/Roleplay/Game/Service/Instance/gameAccessService';
export { gameStatusTransitionsService } from '@/modules/Roleplay/Game/Service/Instance/gameStatusTransitionsService';
export { hitRollService } from '@/modules/Roleplay/Game/Service/Instance/hitRollService';
export { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
export { endOfTurnDotsService } from '@/modules/Roleplay/Game/Service/Instance/endOfTurnDotsService';
export { injuryCheckService } from '@/modules/Roleplay/Game/Service/Instance/injuryCheckService';
export { exhaustionCheckService } from '@/modules/Roleplay/Game/Service/Instance/exhaustionCheckService';
export { bloodLossService } from '@/modules/Roleplay/Game/Service/Instance/bloodLossService';
export { dotTickMathService } from '@/modules/Roleplay/Game/Service/Instance/dotTickMathService';
export { checkRollService } from '@/modules/Roleplay/Game/Service/Instance/checkRollService';
export { characteristicRollService } from '@/modules/Roleplay/Game/Service/Instance/characteristicRollService';
export { injuryRollService } from '@/modules/Roleplay/Game/Service/Instance/injuryRollService';
export { combatCardModelService } from '@/modules/Roleplay/Game/Service/Instance/combatCardModelService';
export { combatChatFoldService } from '@/modules/Roleplay/Game/Service/Instance/combatChatFoldService';
export { combatChatSendService } from '@/modules/Roleplay/Game/Service/Instance/combatChatSendService';
export { combatOverlayService } from '@/modules/Roleplay/Game/Service/Instance/combatOverlayService';
export { damageTypeHooksService } from '@/modules/Roleplay/Game/Service/Instance/damageTypeHooksService';
export { gameChatRulesContextService } from '@/modules/Roleplay/Game/Service/Instance/gameChatRulesContextService';
export { rollService, simpleCheckRollService };
export { movementStateService } from '@/modules/Roleplay/Game/Service/Instance/movementStateService';
export { actionOperationResolutionService } from '@/modules/Roleplay/Game/Service/Instance/actionOperationResolutionService';
export { ACTION_POINTS_CODE } from '@/modules/Roleplay/Game/Constant/Combat/ACTION_POINTS_CODE';
export { DEFAULT_ATTACK_AP } from '@/modules/Roleplay/Game/Constant/Combat/DEFAULT_ATTACK_AP';
export { FLANK_DEFENSE_LABEL } from '@/modules/Roleplay/Game/Constant/Combat/FLANK_DEFENSE_LABEL';
export { SIMPLE_CHECK_ZERO_DIFFICULTY } from '@/modules/Roleplay/Game/Constant/Check/SIMPLE_CHECK_ZERO_DIFFICULTY';
export { GAME_STARTABLE_STATUSES } from '@/modules/Roleplay/Game/Constant/Game/GAME_STARTABLE_STATUSES';
export { GAME_STOPPABLE_STATUSES } from '@/modules/Roleplay/Game/Constant/Game/GAME_STOPPABLE_STATUSES';
