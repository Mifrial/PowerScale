<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useSpaceStore } from '@/modules/Roleplay/Space/Store/spaces';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useRuleStore } from '@/modules/Roleplay/Rule/Store/rules';
import { useKeywordStore } from '@/modules/Roleplay/Rule/Store/keywords';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleVersion } from '@/modules/Roleplay/Rule/Dto/RuleVersion';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import { getRuleApi } from '@/modules/Roleplay/Rule/init';
import { RULE_TYPE_LABELS } from '@/modules/Roleplay/Rule/Constant/RULE_TYPE_LABELS';
import AbilityCard from '@/modules/Roleplay/Rule/Component/Cards/AbilityCard.vue';
import RaceCard from '@/modules/Roleplay/Rule/Component/Cards/RaceCard.vue';
import SpeciesCard from '@/modules/Roleplay/Rule/Component/Cards/SpeciesCard.vue';
import PointsCard from '@/modules/Roleplay/Rule/Component/Cards/PointsCard.vue';

const route = useRoute();
const router = useRouter();
const spaceStore = useSpaceStore();
const revisionStore = useSpaceRevisionStore();
const store = useRuleStore();
const keywordStore = useKeywordStore();
const { signal } = useAbortable();

const code = computed(() => route.params.code as string);
const ctx = computed(() => route.params.ctx as string);
const ruleId = computed(() => route.params.ruleId as string);
const isDraftContext = computed(() => ctx.value === 'draft');

const rule = ref<Rule | null>(null);
const ruleVersions = ref<RuleVersion[]>([]);
const mechanics = ref<Mechanic[]>([]);
const loaded = ref<string | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const mechanic = computed(() => {
  if (!rule.value?.mechanicId) return null;

  return mechanics.value.find((m) => m.id === rule.value?.mechanicId) ?? null;
});

const ruleTags = computed(() => {
  const keywords = rule.value?.keywordIds;
  if (!keywords || keywords.length === 0) return [];

  return keywordStore.keywords.filter((t) => keywords.includes(t.id));
});

const editLink = computed(() => `/space/${code.value}/${ctx.value}/rules/${ruleId.value}/edit`);

async function resolveRoute(): Promise<void> {
  const key = `${code.value}|${ctx.value}|${ruleId.value}`;
  if (loaded.value === key) return;
  loaded.value = key;

  loading.value = true;
  error.value = null;
  rule.value = null;

  try {
    const space = await spaceStore.fetchSpaceByCode(code.value, signal.value);

    if (isDraftContext.value) {
      await revisionStore.syncFromContext(space.id, 'draft', space.revision, signal.value);
    } else if (/^\d+$/.test(ctx.value)) {
      await revisionStore.syncFromContext(space.id, 'rev', Number(ctx.value), signal.value);
    } else {
      router.replace(`/space/${code.value}`);

      return;
    }

    // Ищем правило в effectiveRules (уже смержено с черновиками в draft-контексте)
    const found = revisionStore.effectiveRules.find((r) => r.id === ruleId.value);

    if (found) {
      rule.value = found;
    } else {
      // Fallback: загружаем напрямую из API
      rule.value = await store.fetchRule(ruleId.value, signal.value);
    }

    await store.fetchRuleVersions(ruleId.value, signal.value);
    ruleVersions.value = store.ruleVersions;

    await keywordStore.fetchTags(signal.value);
    mechanics.value = await getRuleApi().getMechanics(signal.value);
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки правила';
  } finally {
    loading.value = false;
  }
}

function retry() {
  loaded.value = null;
  resolveRoute();
}

onMounted(resolveRoute);
watch(() => [route.params.code, route.params.ctx, route.params.ruleId], resolveRoute);
</script>

<template>
  <v-container v-if="rule">
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">{{ rule.name }}</h1>
      <v-chip class="ml-3" variant="tonal" size="small">
        {{ RULE_TYPE_LABELS[rule.type] }}
      </v-chip>
      <v-chip v-if="isDraftContext" class="ml-2" color="warning" variant="tonal" size="small"> Черновик </v-chip>
      <v-chip v-else class="ml-2" color="info" variant="tonal" size="small"> Версия {{ route.params.ctx }} </v-chip>
      <v-spacer />
      <v-btn variant="text" prepend-icon="mdi-pencil" @click="router.push(editLink)"> Редактировать </v-btn>
    </div>

    <v-card class="mb-4">
      <v-card-title>Описание</v-card-title>
      <v-card-text>{{ rule.description }}</v-card-text>
    </v-card>

    <AbilityCard
      v-if="rule.type === 'ability'"
      :rule="rule"
      :rules="revisionStore.effectiveRules"
      :keywords="keywordStore.keywords"
      class="mb-4"
    />

    <RaceCard v-if="rule.type === 'race'" :rule="rule" :rules="revisionStore.effectiveRules" class="mb-4" />

    <SpeciesCard v-if="rule.type === 'species'" :rule="rule" :rules="revisionStore.effectiveRules" class="mb-4" />

    <PointsCard v-if="rule.type === 'points'" :rule="rule" class="mb-4" />

    <v-card v-if="mechanic" class="mb-4">
      <v-card-title>Механика</v-card-title>
      <v-card-text>
        <div class="d-flex align-center">
          <strong>{{ mechanic.name }}</strong>
          <v-chip class="ml-2" size="x-small" variant="tonal"> v{{ mechanic.version }} </v-chip>
        </div>
        <div class="text-body-2 mt-2">{{ mechanic.description }}</div>
      </v-card-text>
    </v-card>

    <v-card v-if="ruleTags.length > 0" class="mb-4">
      <v-card-title>Признаки</v-card-title>
      <v-card-text>
        <v-chip v-for="keyword in ruleTags" :key="keyword.id" class="mr-2 mb-2" size="small">
          {{ keyword.name }}
        </v-chip>
      </v-card-text>
    </v-card>

    <v-card>
      <v-card-title>История версий</v-card-title>
      <v-card-text>
        <v-list v-if="ruleVersions.length > 0">
          <v-list-item v-for="version in ruleVersions" :key="version.id">
            <v-list-item-title>
              v{{ version.versionA }}.{{ version.versionB }}.{{ version.versionC }}
            </v-list-item-title>
            <v-list-item-subtitle>
              {{ new Date(version.createdAt).toLocaleString('ru-RU') }}
            </v-list-item-subtitle>
          </v-list-item>
        </v-list>
        <div v-else class="text-body-2 text-medium-emphasis">Нет версий</div>
      </v-card-text>
    </v-card>
  </v-container>
  <div v-else-if="loading" class="d-flex justify-center pa-8">
    <v-progress-circular indeterminate width="2" size="28" color="primary" />
  </div>
  <div v-else-if="error" class="text-center pa-8">
    <v-icon icon="mdi-alert-circle" size="64" color="error" class="mb-4" />
    <p class="text-body-1 mb-4">{{ error }}</p>
    <v-btn color="primary" @click="retry">Попробовать снова</v-btn>
  </div>
</template>
