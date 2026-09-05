<script setup lang="ts">
import { watch } from 'vue';
import { useRouter } from 'vue-router';
import { useRuleDrafts } from '@/modules/Roleplay/Rule/init';
import { useSpaceContext } from '@/modules/Roleplay/Space/Composables/useSpaceContext';

const router = useRouter();
const drafts = useRuleDrafts();
const context = useSpaceContext();

watch(
  () => context.value.space,
  (space) => {
    if (!space) return;
    const target = drafts.hasDraft(space.id) ? 'draft' : String(space.revision);
    router.replace(`/space/${space.code}/${target}`);
  },
  { immediate: true },
);
</script>

<template>
  <v-container fluid class="d-flex justify-center">
    <v-progress-circular indeterminate width="2" size="28" color="primary" />
  </v-container>
</template>
