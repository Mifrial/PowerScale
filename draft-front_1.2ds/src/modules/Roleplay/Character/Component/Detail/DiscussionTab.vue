<script setup lang="ts">
import { useSpaceRevision } from '@/modules/Roleplay/Space/init';
import { computed, ref, watch } from 'vue';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { characterChatRulesContextService } from '@/modules/Roleplay/Character/Service/Instance/characterChatRulesContextService';
import type { ChatRulesContext } from '@/modules/Messages/Chat/Dto/ChatRulesContext';
import { ChatThread, chatInlineRendererContext } from '@/modules/Messages/Chat/init';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const props = defineProps<{
  discussionChatId: number | null;
  /** Ревизия персонажа — правила для чипов/ссылок обсуждения. */
  spaceId: number;
  rulesRevision: number;
}>();

const spaceRevision = useSpaceRevision();
const { signal } = useAbortable();
const rules = ref<Rule[]>([]);
const rulesLoading = ref(false);
const rulesError = ref<string | null>(null);

const context = computed<ChatRulesContext>(() =>
  characterChatRulesContextService.build(rules.value, props.spaceId, props.rulesRevision),
);

const inlineContext = computed(() => chatInlineRendererContext(context.value));

async function loadRules(): Promise<void> {
  rulesLoading.value = true;
  rulesError.value = null;
  try {
    const revision = await spaceRevision.fetchRevision(props.spaceId, props.rulesRevision, signal.value);
    rules.value = revision.rules;
  } catch (caught) {
    if (caught instanceof DOMException && caught.name === 'AbortError') return;
    rules.value = [];
    rulesError.value = 'Не удалось загрузить правила ревизии';
  } finally {
    rulesLoading.value = false;
  }
}

watch(
  () => [props.spaceId, props.rulesRevision] as const,
  () => void loadRules(),
  { immediate: true },
);
</script>

<template>
  <div>
    <div v-if="rulesLoading" class="d-flex justify-center pa-2">
      <v-progress-circular indeterminate width="2" size="24" color="primary" />
    </div>
    <div v-else-if="rulesError" class="pa-4">
      <div class="text-error text-body-2 mb-2">{{ rulesError }}</div>
      <v-btn variant="tonal" color="primary" size="small" @click="loadRules">Попробовать снова</v-btn>
    </div>
    <ChatThread
      :chat-id="discussionChatId"
      :renderer-context="inlineContext"
      :token-sources="context.tokenSources"
      :process-attachments="context.processAttachments"
      empty-label="Обсуждение доступно в мессенджере"
    />
  </div>
</template>
