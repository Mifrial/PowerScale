<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { getRuleApi } from '@/modules/Roleplay/Rule/init';
import { getChatRulesContext } from '@/modules/Messages/Chat/init';
import type { ChatRulesContext } from '@/modules/Messages/Chat/Dto/ChatRulesContext';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import ChatThread from '@/modules/Messages/Chat/Component/ChatThread.vue';

const props = defineProps<{
  discussionChatId: number | null;
  /** Ревизия персонажа — правила и механики для чипов/ссылок/бросков обсуждения. */
  spaceId: number;
  rulesRevision: number;
}>();

const spaceRevisionStore = useSpaceRevisionStore();
const { signal } = useAbortable();
const rules = ref<Rule[]>([]);
const mechanics = ref<Mechanic[]>([]);

// Сборку контекста (включая броски через RollEngine) отдаёт Game через реестр Chat —
// Character не зависит от Game (направление зависимостей).
const context = computed<ChatRulesContext>(() =>
  getChatRulesContext(rules.value, mechanics.value, props.spaceId, props.rulesRevision),
);

async function load(): Promise<void> {
  try {
    const revision = await spaceRevisionStore.fetchRevision(props.spaceId, props.rulesRevision, signal.value);
    rules.value = revision.rules;
    mechanics.value = await getRuleApi().getMechanics(signal.value);
  } catch {
    rules.value = [];
    mechanics.value = [];
  }
}

watch(
  () => [props.spaceId, props.rulesRevision] as const,
  () => void load(),
  { immediate: true },
);
</script>

<template>
  <ChatThread
    :chat-id="discussionChatId"
    :rule-names="context.ruleNames"
    :space-id="context.spaceId"
    :rules-revision="context.rulesRevision"
    :token-sources="context.tokenSources"
    :process-attachments="context.processAttachments"
    empty-label="Обсуждение доступно в мессенджере"
  />
</template>
