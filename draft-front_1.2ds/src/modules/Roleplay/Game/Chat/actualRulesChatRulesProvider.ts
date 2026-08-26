import { getRuleApi } from '@/modules/Roleplay/Rule/init';
import { ACTUAL_RULES_SPACE_CODE } from '@/modules/Roleplay/Space/init';
import { useSpaceStore } from '@/modules/Roleplay/Space/Store/spaces';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import type { IChatRulesProvider } from '@/modules/Messages/Chat/Interface/IChatRulesProvider';
import { gameChatRulesContextService } from '@/modules/Roleplay/Game/Service/Instance/gameChatRulesContextService';

/**
 * Правила обычных чатов (private/group): последняя опубликованная ревизия пространства
 * «Актуальные правила» — единый источник чипов [[rule:...]] и бросков в мессенджере.
 */
export const actualRulesChatRulesProvider: IChatRulesProvider = {
  types: [],
  resolve: async () => {
    const spaceStore = useSpaceStore();
    if (spaceStore.spaces.length === 0) await spaceStore.fetchSpaces();
    const space = spaceStore.spaces.find((candidate) => candidate.code === ACTUAL_RULES_SPACE_CODE);
    if (!space) {
      throw new Error('Не найдено пространство актуальных правил');
    }
    const revision = await useSpaceRevisionStore().fetchRevision(space.id, space.revision);
    const mechanics = await getRuleApi().getMechanics();

    return gameChatRulesContextService.buildChatRulesContext(revision.rules, mechanics, space.id, space.revision);
  },
};
