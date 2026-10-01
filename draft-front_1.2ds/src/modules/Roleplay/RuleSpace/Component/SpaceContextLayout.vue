<script setup lang="ts">
import { computed, provide } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useSpaceContextResolve } from '@/modules/Roleplay/RuleSpace/Composables/useSpaceContextResolve';
import { spaceContextKey } from '@/modules/Roleplay/RuleSpace/Constant/spaceContextKey';
import type { ISpaceContext } from '@/modules/Roleplay/RuleSpace/Interface/ISpaceContext';
import { ruleHostContextKey } from '@/modules/Roleplay/Rule/Constant/ruleHostContextKey';
import type { IRuleHostContext } from '@/modules/Roleplay/Rule/Interface/IRuleHostContext';
import { useSpaceStore } from '@/modules/Roleplay/RuleSpace/Store/spaces';
import { useSpaceRevisionStore } from '@/modules/Roleplay/RuleSpace/Store/spaceRevision';

const route = useRoute();
const router = useRouter();
const spaceStore = useSpaceStore();
const revisionStore = useSpaceRevisionStore();

const code = computed(() => route.params.code as string | undefined);
const ctx = computed(() => route.params.ctx as string | undefined);
const isDraftContext = computed(() => ctx.value === 'draft');
const isRevisionContext = computed(() => !!ctx.value && ctx.value !== 'draft');
const { loading, error, retry } = useSpaceContextResolve(code, ctx, (path) => {
  void router.replace(path);
});

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

const ruleHost = computed<IRuleHostContext>(() => ({
  spaceId: spaceStore.currentSpace?.id ?? null,
  effectiveRules: revisionStore.effectiveRules,
  sections: revisionStore.effectiveSections,
}));
provide(ruleHostContextKey, ruleHost);
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
