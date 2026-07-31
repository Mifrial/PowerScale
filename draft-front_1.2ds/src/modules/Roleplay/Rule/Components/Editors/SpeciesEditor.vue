<template>
  <div>
    <v-expansion-panels v-model="expandedPanels" multiple>
      <v-expansion-panel value="general">
        <v-expansion-panel-title>Общее</v-expansion-panel-title>
        <v-expansion-panel-text>
          <RuleEditorBase
            :name="name"
            @update:name="(v) => emit('update:name', v)"
            :code="code"
            @update:code="(v) => emit('update:code', v)"
            :description="description"
            @update:description="(v) => emit('update:description', v)"
            :mechanic-id="mechanicId"
            @update:mechanic-id="(v) => emit('update:mechanicId', v)"
            :tag-ids="tagIds"
            @update:tag-ids="(v) => emit('update:tagIds', v)"
            :mechanic-options="mechanicOptions"
            :tag-options="tagOptions"
          >
            <template #spec></template>
          </RuleEditorBase>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel value="parent">
        <v-expansion-panel-title>Родитель</v-expansion-panel-title>
        <v-expansion-panel-text>
          <v-autocomplete
            v-model="innerSpec.parent_race_code"
            :items="speciesOptions"
            item-title="name"
            item-value="code"
            label="Родительский вид/подвид"
            clearable
            density="compact"
            hide-details
          />
          <div class="text-body-2 text-medium-emphasis mt-2">
            Вид — корневой узел без ссылки (пусто). Подвид — с ссылкой на родителя.
          </div>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel value="abilities">
        <v-expansion-panel-title>Способности</v-expansion-panel-title>
        <v-expansion-panel-text>
          <div class="text-body-2 text-medium-emphasis mb-2">
            Способности вида/подвида наследуются расами цепочки (вид → подвид → раса).
          </div>
          <div
            v-for="(ref, index) in innerSpec.abilities"
            :key="index"
            class="d-flex ga-2 align-center mb-1"
          >
            <v-autocomplete
              :model-value="ref.ability_code"
              @update:model-value="(v) => updateAbility(index, 'ability_code', v)"
              :items="abilityOptions"
              item-title="name"
              item-value="code"
              label="Способность"
              density="compact"
              hide-details
              class="flex-grow-1"
            />
            <v-switch
              :model-value="ref.automatic"
              @update:model-value="(v) => updateAbility(index, 'automatic', !!v)"
              label="Бесплатная"
              density="compact"
              hide-details
            />
            <v-btn
              icon
              size="small"
              color="error"
              variant="text"
              @click="removeAbility(index)"
            >
              <v-icon>mdi-delete</v-icon>
            </v-btn>
          </div>
          <v-btn
            variant="text"
            color="primary"
            size="small"
            @click="addAbility"
          >
            <v-icon start>mdi-plus</v-icon>
            Добавить способность
          </v-btn>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision'
import type { Rule } from '@/modules/Roleplay/Rule/Interface/types'
import type { SpeciesSpec } from '@/modules/Roleplay/Rule/Interface/raceTypes'
import { createEmptySpeciesSpec } from '@/modules/Roleplay/Rule/Interface/raceTypes'
import RuleEditorBase from './RuleEditorBase.vue'

const props = defineProps<{
  name: string
  code: string
  description: string
  mechanicId: number | null
  tagIds: number[]
  spec: SpeciesSpec | null
  mechanicOptions: { title: string; value: number }[]
  tagOptions: { title: string; value: number }[]
  spaceId: number
  ruleId?: string
}>()

const emit = defineEmits<{
  'update:name': [value: string]
  'update:code': [value: string]
  'update:description': [value: string]
  'update:mechanicId': [value: number | null]
  'update:tagIds': [value: number[]]
  'update:spec': [value: SpeciesSpec]
}>()

const revisionStore = useSpaceRevisionStore()

const expandedPanels = ref<string[]>(['general', 'parent', 'abilities'])
const innerSpec = ref<SpeciesSpec>(createEmptySpeciesSpec())

const spaceRules = computed(() => revisionStore.effectiveRules)

const speciesOptions = computed(() =>
  spaceRules.value
    .filter((r: Rule) => r.type === 'species' && r.id !== props.ruleId)
    .map(r => ({ code: r.code, name: r.name }))
)

const abilityOptions = computed(() =>
  spaceRules.value
    .filter((r: Rule) => r.type === 'ability')
    .map(r => ({ code: r.code, name: r.name }))
)

function updateAbility(index: number, key: 'ability_code' | 'automatic', value: string | boolean) {
  const abilities = innerSpec.value.abilities.map((a, i) =>
    i === index ? { ...a, [key]: value } : a
  )
  innerSpec.value = { ...innerSpec.value, abilities }
}

function removeAbility(index: number) {
  innerSpec.value = {
    ...innerSpec.value,
    abilities: innerSpec.value.abilities.filter((_, i) => i !== index),
  }
}

function addAbility() {
  innerSpec.value = {
    ...innerSpec.value,
    abilities: [...innerSpec.value.abilities, { ability_code: '', automatic: false }],
  }
}

const specToEmit = computed<SpeciesSpec>(() => JSON.parse(JSON.stringify(innerSpec.value)))

watch(specToEmit, (value) => {
  emit('update:spec', value)
}, { deep: true })

onMounted(() => {
  if (props.spec) {
    const loaded = JSON.parse(JSON.stringify(props.spec))
    innerSpec.value = {
      parent_race_code: loaded.parent_race_code ?? null,
      abilities: loaded.abilities ?? [],
    }
  }
})
</script>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
