<script setup lang="ts">
import { computed, provide, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useSpaceStore } from '@/modules/Roleplay/Space/Store/spaces';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { spaceContextKey } from '@/modules/Roleplay/Space/Constant/spaceContextKey';
import type { ISpaceContext } from '@/modules/Roleplay/Space/Interface/ISpaceContext';

const route = useRoute();
const router = useRouter();
const spaceStore = useSpaceStore();
const revisionStore = useSpaceRevisionStore();
const { signal } = useAbortable();

const loading = ref(true);
const error = ref<string | null>(null);
const loadedCode = ref('');

const code = computed(() => route.params.code as string | undefined);
const ctx = computed(() => route.params.ctx as string | undefined);
const isDraftContext = computed(() => ctx.value === 'draft');
const isRevisionContext = computed(() => !!ctx.value && ctx.value !== 'draft');

async function loadSpace(): Promise<void> {
  if (!code.value) return;
  const space = await spaceStore.fetchSpaceByCode(code.value, signal.value);
  loadedCode.value = code.value;
  await revisionStore.fetchRevisionsMeta(space.id, signal.value);
}

async function syncContext(): Promise<void> {
  const space = spaceStore.currentSpace;
  if (!space) return;
  if (isDraftContext.value) {
    await revisionStore.syncFromContext(space.id, 'draft', space.revision, signal.value);
  } else if (/^\d+$/.test(ctx.value ?? '')) {
    await revisionStore.syncFromContext(space.id, 'rev', Number(ctx.value), signal.value);
  }
}

async function resolve(): Promise<void> {
  loading.value = true;
  error.value = null;
  try {
    if (code.value !== loadedCode.value) {
      await loadSpace();
    }
    const space = spaceStore.currentSpace;
    if (!space) return;

    if (ctx.value === undefined) {
      revisionStore.clearContext();

      return;
    }
    if (ctx.value !== 'draft' && !/^\d+$/.test(ctx.value)) {
      router.replace(`/space/${code.value}`);

      return;
    }
    await syncContext();
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки пространства';
  } finally {
    loading.value = false;
  }
}

function retry() {
  loadedCode.value = '';
  resolve();
}

watch(() => [route.params.code, route.params.ctx], resolve, { immediate: true });

const context = computed<ISpaceContext>(() => ({
  space: spaceStore.currentSpace,
  spaceId: spaceStore.currentSpace?.id ?? null,
  effectiveRules: revisionStore.effectiveRules,
  ctx: ctx.value,
  isDraftContext: isDraftContext.value,
  isRevisionContext: isRevisionContext.value,
  loading: loading.value,
  error: error.value,
  retry,
}));

provide(spaceContextKey, context);
</script>

<template>
  <v-container v-if="error" class="text-center pa-8">
    <v-icon icon="mdi-alert-circle" size="64" color="error" class="mb-4" />
    <p class="text-body-1 mb-4">{{ error }}</p>
    <v-btn color="primary" @click="retry">Попробовать снова</v-btn>
  </v-container>
  <div v-else-if="loading" class="d-flex justify-center pa-8">
    <v-progress-circular indeterminate width="2" size="28" color="primary" />
  </div>
  <RouterView v-else />
</template>
