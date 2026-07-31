<template>
  <v-container v-if="rule">
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">{{ rule.name }}</h1>
      <v-chip class="ml-3" variant="tonal" size="small">
        {{ typeLabels[rule.type] }}
      </v-chip>
      <v-chip
        v-if="isDraftContext"
        class="ml-2"
        color="warning"
        variant="tonal"
        size="small"
      >
        Черновик
      </v-chip>
      <v-chip
        v-else
        class="ml-2"
        color="info"
        variant="tonal"
        size="small"
      >
        Версия {{ route.params.ctx }}
      </v-chip>
      <v-spacer />
      <v-btn variant="text" prepend-icon="mdi-pencil" @click="router.push(editLink)">
        Редактировать
      </v-btn>
    </div>

    <v-card class="mb-4">
      <v-card-title>Описание</v-card-title>
      <v-card-text>{{ rule.description }}</v-card-text>
    </v-card>

    <AbilityCard
      v-if="rule.type === 'ability'"
      :rule="rule"
      :rules="revisionStore.effectiveRules"
      :tags="tagStore.tags"
      class="mb-4"
    />

    <RaceCard
      v-if="rule.type === 'race'"
      :rule="rule"
      :rules="revisionStore.effectiveRules"
      class="mb-4"
    />

    <SpeciesCard
      v-if="rule.type === 'species'"
      :rule="rule"
      :rules="revisionStore.effectiveRules"
      class="mb-4"
    />

    <PointsCard
      v-if="rule.type === 'points'"
      :rule="rule"
      class="mb-4"
    />

    <v-card v-if="mechanic" class="mb-4">
      <v-card-title>Механика</v-card-title>
      <v-card-text>
        <div class="d-flex align-center">
          <strong>{{ mechanic.name }}</strong>
          <v-chip class="ml-2" size="x-small" variant="tonal">
            v{{ mechanic.version }}
          </v-chip>
        </div>
        <div class="text-body-2 mt-2">{{ mechanic.description }}</div>
      </v-card-text>
    </v-card>

    <v-card v-if="ruleTags.length > 0" class="mb-4">
      <v-card-title>Признаки</v-card-title>
      <v-card-text>
        <v-chip v-for="tag in ruleTags" :key="tag.id" class="mr-2 mb-2" size="small">
          {{ tag.name }}
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
        <div v-else class="text-body-2 text-medium-emphasis">
          Нет версий
        </div>
      </v-card-text>
    </v-card>
  </v-container>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSpaceStore } from '@/modules/Roleplay/Space/Store/spaces'
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision'
import { useRuleStore } from '../Store/rules'
import { useTagStore } from '@/modules/Roleplay/Rule/Tag/Store/tags'
import { useAbortable } from '@/modules/Core/Composables/useAbortable'
import type { Rule, RuleVersion } from '../Interface/types'
import type { Mechanic } from '../Service/mockMechanics'
import { fetchMechanics } from '../Service/mockMechanics'
import AbilityCard from '../Components/Cards/AbilityCard.vue'
import RaceCard from '../Components/Cards/RaceCard.vue'
import SpeciesCard from '../Components/Cards/SpeciesCard.vue'
import PointsCard from '../Components/Cards/PointsCard.vue'

const route = useRoute()
const router = useRouter()
const spaceStore = useSpaceStore()
const revisionStore = useSpaceRevisionStore()
const store = useRuleStore()
const tagStore = useTagStore()
const { signal } = useAbortable()

const code = computed(() => route.params.code as string)
const ctx = computed(() => route.params.ctx as string)
const ruleId = computed(() => route.params.ruleId as string)
const isDraftContext = computed(() => ctx.value === 'draft')

const rule = ref<Rule | null>(null)
const ruleVersions = ref<RuleVersion[]>([])
const mechanics = ref<Mechanic[]>([])
const loaded = ref<string | null>(null)

const typeLabels: Record<string, string> = {
  simple: 'Простое',
  race: 'Раса',
  species: 'Вид/Подвид',
  characteristic: 'Характеристика',
  resource: 'Ресурс',
  points: 'Очки',
  ability: 'Способность',
  item: 'Предмет',
  damage_type: 'Тип урона',
}

const mechanic = computed(() => {
  if (!rule.value?.mechanicId) return null
  return mechanics.value.find(m => m.id === rule.value?.mechanicId) ?? null
})

const ruleTags = computed(() => {
  if (!rule.value?.tagIds || rule.value.tagIds.length === 0) return []
  return tagStore.tags.filter(t => rule.value!.tagIds!.includes(t.id))
})

const editLink = computed(() => `/space/${code.value}/${ctx.value}/rules/${ruleId.value}/edit`)

async function resolveRoute(): Promise<void> {
  const key = `${code.value}|${ctx.value}|${ruleId.value}`
  if (loaded.value === key) return
  loaded.value = key

  try {
    const space = await spaceStore.fetchSpaceByCode(code.value, signal.value)

    if (isDraftContext.value) {
      await revisionStore.syncFromContext(space.id, 'draft', space.revision, signal.value)
    } else if (/^\d+$/.test(ctx.value)) {
      await revisionStore.syncFromContext(space.id, 'rev', Number(ctx.value), signal.value)
    } else {
      router.replace(`/space/${code.value}`)
      return
    }
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return
    router.replace(`/space/${code.value}`)
    return
  }

  // Ищем правило в effectiveRules (уже смержено с черновиками в draft-контексте)
  const found = revisionStore.effectiveRules.find(r => r.id === ruleId.value)

  if (found) {
    rule.value = found
  } else {
    // Fallback: загружаем напрямую из API
    rule.value = await store.fetchRule(ruleId.value, signal.value)
  }

  await store.fetchRuleVersions(ruleId.value, signal.value)
  ruleVersions.value = store.ruleVersions

  await tagStore.fetchTags(signal.value)
  mechanics.value = await fetchMechanics(signal.value)
}

onMounted(resolveRoute)
watch(() => [route.params.code, route.params.ctx, route.params.ruleId], resolveRoute)
</script>
