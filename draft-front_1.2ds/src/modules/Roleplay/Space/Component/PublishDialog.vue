<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import type { Space } from '@/modules/Roleplay/Space/Dto/Space';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useDraftRuleStore } from '@/modules/Roleplay/Rule/Store/draftRules';
import { useKeywordStore } from '@/modules/Roleplay/Rule/Store/keywords';
import { RULE_TYPE_LABELS } from '@/modules/Roleplay/Rule/init';
import { publishService } from '@/modules/Roleplay/Space/Service/Instance/publishService';
import type { PublishSummary } from '@/modules/Roleplay/Space/Dto/PublishSummary';

const props = defineProps<{
  modelValue: boolean;
  space: Space | null;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  published: [revision: number];
  error: [message: string];
}>();

const revisionStore = useSpaceRevisionStore();
const draftStore = useDraftRuleStore();
const keywordStore = useKeywordStore();

const open = computed({
  get: () => props.modelValue,
  set: (v: boolean) => emit('update:modelValue', v),
});

const publishing = ref(false);
const summary = ref<PublishSummary | null>(null);

const publishAdded = computed(() => summary.value?.added ?? []);
const publishChanged = computed(() => summary.value?.changed ?? []);
const publishProblems = computed(() => summary.value?.problems ?? []);
const publishSpaceErrors = computed(() => summary.value?.spaceErrors ?? []);

const hasPublishProblems = computed(() => publishProblems.value.length > 0 || publishSpaceErrors.value.length > 0);

watch(
  () => props.modelValue,
  (openNow) => {
    if (openNow && props.space) prepare();
  },
);

function prepare() {
  const space = props.space;
  if (!space) return;
  summary.value = publishService.prepare(
    revisionStore.activeRevision?.rules ?? [],
    draftStore.getDraftRules(space.id),
    revisionStore.effectiveRules,
    keywordStore.keywords,
  );
}

async function publishDraft() {
  const space = props.space;
  if (!space || hasPublishProblems.value) return;
  publishing.value = true;
  try {
    const rules = draftStore.getDraftRules(space.id);
    const result = await revisionStore.commitDraft(space.id, rules);
    draftStore.discardDraft(space.id);
    emit('update:modelValue', false);
    emit('published', result.revision);
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    emit('error', e instanceof Error ? e.message : 'Ошибка публикации');
  } finally {
    publishing.value = false;
  }
}
</script>

<template>
  <v-dialog v-model="open" max-width="640">
    <v-card>
      <v-card-title>Публикация черновика</v-card-title>
      <v-card-text>
        <div class="text-body-2 mb-4">
          Будут опубликованы {{ publishAdded.length + publishChanged.length }} правил поверх версии
          {{ space?.revision }}.
        </div>

        <div v-if="publishChanged.length > 0" class="mb-3">
          <div class="text-subtitle-2 font-weight-medium mb-1">Изменённые ({{ publishChanged.length }})</div>
          <div
            v-for="rule in publishChanged"
            :key="rule.id"
            class="d-flex align-center pa-2 mb-1 bg-surface-variant rounded"
          >
            <span class="text-body-2">{{ rule.name }}</span>
            <v-chip size="x-small" class="ml-2" variant="tonal">{{ RULE_TYPE_LABELS[rule.type] }}</v-chip>
          </div>
        </div>

        <div v-if="publishAdded.length > 0" class="mb-3">
          <div class="text-subtitle-2 font-weight-medium mb-1">Новые ({{ publishAdded.length }})</div>
          <div
            v-for="rule in publishAdded"
            :key="rule.id"
            class="d-flex align-center pa-2 mb-1 bg-surface-variant rounded"
          >
            <span class="text-body-2">{{ rule.name }}</span>
            <v-chip size="x-small" class="ml-2" variant="tonal">{{ RULE_TYPE_LABELS[rule.type] }}</v-chip>
          </div>
        </div>

        <div v-if="hasPublishProblems">
          <div class="text-subtitle-2 font-weight-medium text-error mb-1">
            Проблемные ({{ publishProblems.length + publishSpaceErrors.length }})
          </div>
          <div class="text-body-2 text-error mb-2">
            В черновике есть правила с ошибками. Исправьте их перед публикацией.
          </div>
          <div v-for="entry in publishProblems" :key="entry.ruleCode" class="pa-2 mb-1 bg-error-lighten-5 rounded">
            <div class="text-body-2 font-weight-medium">{{ entry.ruleName }}</div>
            <div v-for="(msg, i) in entry.messages" :key="i" class="text-body-2 text-error pa-1">
              {{ msg }}
            </div>
          </div>
          <div v-for="(msg, i) in publishSpaceErrors" :key="'s' + i" class="text-body-2 text-error pa-1">
            {{ msg }}
          </div>
        </div>
      </v-card-text>
      <v-card-actions>
        <v-btn variant="text" @click="open = false">Отмена</v-btn>
        <v-btn
          color="primary"
          variant="tonal"
          :loading="publishing"
          :disabled="hasPublishProblems"
          @click="publishDraft"
        >
          Подтвердить публикацию
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
