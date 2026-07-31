<template>
  <div>
    <v-expansion-panels v-model="expandedPanels" multiple>
      <v-expansion-panel value="general">
        <v-expansion-panel-title>Общее</v-expansion-panel-title>
        <v-expansion-panel-text>
          <v-select
            :model-value="typeModel"
            @update:model-value="(v) => (typeModel = v as AbilityType | null)"
            :items="typeOptions"
            item-title="label"
            item-value="value"
            label="Тип способности"
            density="compact"
            hide-details
            clearable
            class="mb-2"
          />
          <div class="text-body-2 text-medium-emphasis mb-2">
            Тип определяет видимые блоки и карточку. Типообразующие признаки проставляются автоматически.
          </div>
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

      <v-expansion-panel value="zones">
        <v-expansion-panel-title>Зоны и стоимость</v-expansion-panel-title>
        <v-expansion-panel-text>
          <div class="text-body-2 text-medium-emphasis mb-2">
            Очки определяются зоной: каждая зона — очки-правило пространства. Зона без галочки —
            способность в ней не представлена.
          </div>
          <div class="d-flex flex-wrap ga-4">
            <v-checkbox
              v-for="zone in zoneOptions"
              :key="zone.value"
              :model-value="hasZone(zone.value)"
              @update:model-value="(v: boolean | null) => toggleZone(zone.value, !!v)"
              :label="zone.label"
              density="compact"
              hide-details
            />
          </div>

          <div
            v-for="zone in zoneOptions"
            :key="`zone-editor-${zone.value}`"
            class="mt-3"
          >
            <template v-if="hasZone(zone.value)">
              <div class="text-subtitle-2 mb-1">{{ zone.label }}</div>
              <v-radio-group
                :model-value="zoneCost(zone.value).kind"
                @update:model-value="(v) => patchZone(zone.value, 'kind', v)"
                density="compact"
                hide-details
                class="mb-1"
              >
                <v-radio label="Массив" value="array" />
                <v-radio label="Прогрессия" value="progression" />
                <v-radio label="Авто-получение" value="automatic" />
              </v-radio-group>

              <template v-if="zoneCost(zone.value).kind === 'array'">
                <div class="text-body-2 text-medium-emphasis mb-1">
                  Стоимости по уровням (отрицательная = даёт очки). Длина = макс. уровень.
                </div>
                <div class="d-flex ga-2 align-center flex-wrap">
                  <ClampedNumberField
                    v-for="(cost, levelIndex) in arrayCosts(zone.value)"
                    :key="levelIndex"
                    :model-value="cost"
                    @update:model-value="updateArrayCost(zone.value, levelIndex, $event)"
                    :label="`Ур. ${levelIndex + 1}`"
                    density="compact"
                    hide-details
                    style="min-width: 90px;"
                  />
                  <v-btn
                    icon
                    size="small"
                    variant="text"
                    @click="addArrayCost(zone.value)"
                  >
                    <v-icon>mdi-plus</v-icon>
                  </v-btn>
                  <v-btn
                    icon
                    size="small"
                    variant="text"
                    @click="removeArrayCost(zone.value)"
                  >
                    <v-icon>mdi-minus</v-icon>
                  </v-btn>
                </div>
              </template>

              <template v-else-if="zoneCost(zone.value).kind === 'progression'">
                <div class="d-flex gap-2 mt-1">
                  <ClampedNumberField
                    :model-value="progressionZone(zone.value)?.max_level ?? 1"
                    @update:model-value="patchZone(zone.value, 'max_level', $event)"
                    label="Макс. уровень"
                    :min="1"
                    density="compact"
                    hide-details
                    style="min-width: 140px;"
                  />
                  <ClampedNumberField
                    :model-value="progressionZone(zone.value)?.base_cost ?? 0"
                    @update:model-value="patchZone(zone.value, 'base_cost', $event)"
                    label="Базовая стоимость"
                    density="compact"
                    hide-details
                    style="min-width: 140px;"
                  />
                  <ClampedNumberField
                    :model-value="progressionZone(zone.value)?.step ?? 0"
                    @update:model-value="patchZone(zone.value, 'step', $event)"
                    label="Шаг за уровень"
                    density="compact"
                    hide-details
                    style="min-width: 140px;"
                  />
                </div>
              </template>

              <template v-else>
                <div class="text-body-2 text-medium-emphasis">
                  Способность получается автоматически при выполнении требований и не покупается.
                </div>
              </template>
            </template>
          </div>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel value="requirements">
        <v-expansion-panel-title>Требования</v-expansion-panel-title>
        <v-expansion-panel-text>
          <div class="text-body-2 text-medium-emphasis mb-2">
            Требования по уровням: уровень 1 — получение способности, уровни N — для взятия
            N-го уровня. Требования накапливаются (взял уровень N — уровни ниже уже выполнены).
          </div>
          <div
            v-for="(entry, levelIndex) in innerSpec.requirements"
            :key="`rbl-${levelIndex}`"
            class="pa-1 mb-2 rounded bg-accent"
          >
            <div class="bg-surface rounded pa-2">
              <div class="d-flex align-center mb-1">
                <ClampedNumberField
                  :model-value="entry.level"
                  @update:model-value="updateReqLevel(levelIndex, $event)"
                  label="Уровень"
                  :min="1"
                  density="compact"
                  hide-details
                  style="min-width: 120px;"
                />
                <v-chip
                  v-if="entry.level === 1"
                  class="ml-2"
                  size="x-small"
                  color="primary"
                  variant="tonal"
                >
                  Получение
                </v-chip>
                <v-spacer />
                <v-btn
                  icon
                  size="x-small"
                  color="error"
                  variant="text"
                  @click="removeRequirementLevel(levelIndex)"
                >
                  <v-icon>mdi-delete</v-icon>
                </v-btn>
              </div>
              <RequirementListEditor
                :model-value="entry.requirements"
                @update:model-value="(v) => updateRequirementLevelRequirements(levelIndex, v)"
                :characteristics="characteristics"
                :resources="resources"
                :abilities="abilities"
                :tags="tags"
                :ability-tags="abilityTags"
              />
            </div>
          </div>
          <v-btn
            variant="text"
            color="primary"
            size="small"
            @click="addRequirementLevel"
          >
            <v-icon start>mdi-plus</v-icon>
            Добавить уровень
          </v-btn>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel value="grants">
        <v-expansion-panel-title>Дары</v-expansion-panel-title>
        <v-expansion-panel-text>
          <div class="text-body-2 text-medium-emphasis mb-2">
            Дары по уровням: уровень 1 — при получении, уровни N — при достижении N-го уровня.
            «Постоянный» — дар действует на всех уровнях ≥ этого (по умолчанию);
            без него — строго на своём уровне. Формулы уровня масштабируются сами — не дублируйте.
          </div>
          <div
            v-for="(entry, levelIndex) in innerSpec.grants"
            :key="`l-${levelIndex}`"
            class="pa-1 mb-2 rounded bg-accent"
          >
            <div class="bg-surface rounded pa-2">
              <div class="d-flex align-center mb-1">
                <ClampedNumberField
                  :model-value="entry.level"
                  @update:model-value="updateGrantLevel(levelIndex, $event)"
                  label="Уровень"
                  :min="1"
                  density="compact"
                  hide-details
                  style="min-width: 120px;"
                />
                <v-chip
                  v-if="entry.level === 1"
                  class="ml-2"
                  size="x-small"
                  color="primary"
                  variant="tonal"
                >
                  Получение
                </v-chip>
                <v-spacer />
                <v-btn
                  icon
                  size="x-small"
                  color="error"
                  variant="text"
                  @click="removeGrantLevel(levelIndex)"
                >
                  <v-icon>mdi-delete</v-icon>
                </v-btn>
              </div>
              <div
                v-for="(grant, grantIndex) in entry.grants"
                :key="`l-${levelIndex}-${grantIndex}`"
                class="mb-1"
              >
                <GrantEditor
                  :model-value="grant"
                  :characteristics="characteristics"
                  :resources="resources"
                  :abilities="abilities"
                  :tags="tags"
                  :items="items"
                  :sources="sources"
                  @update:model-value="(v) => updateGrant(levelIndex, grantIndex, v)"
                  @remove="removeGrant(levelIndex, grantIndex)"
                />
              </div>
              <v-btn
                variant="text"
                color="primary"
                size="small"
                @click="addGrant(levelIndex)"
              >
                <v-icon start>mdi-plus</v-icon>
                Добавить дар
              </v-btn>
            </div>
          </div>
          <v-btn
            variant="text"
            color="primary"
            size="small"
            @click="addGrantLevel"
          >
            <v-icon start>mdi-plus</v-icon>
            Добавить уровень
          </v-btn>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel v-if="showAction" value="action">
        <v-expansion-panel-title>Действие</v-expansion-panel-title>
        <v-expansion-panel-text>
          <div class="text-body-2 text-medium-emphasis mb-2">
            Любое действие стоит минимум 1 ОД. {{ isSpell ? 'У заклинаний ОД называется «Сотворение».' : '' }}
          </div>
          <div
            v-for="(cost, index) in innerSpec.action_costs"
            :key="`a-${index}`"
            class="d-flex gap-2 mb-1"
          >
            <v-autocomplete
              :model-value="cost.resource_code"
              @update:model-value="updateActionCost(index, 'resource_code', $event)"
              :items="resources"
              item-title="name"
              item-value="code"
              label="Ресурс"
              density="compact"
              hide-details
              :clearable="!isMandatoryActionPointCost(index)"
              :disabled="isMandatoryActionPointCost(index)"
              class="flex-grow-1"
            />
            <DimensionalNumberInput
              v-if="actionResourceIsDimensional(cost.resource_code)"
              :model-value="(cost.amount as any) ?? { base: 0, size: 0 }"
              @update:model-value="(v) => updateActionCost(index, 'amount', v)"
              label="Стоимость"
              style="flex: 1 1 auto;"
            />
            <ClampedNumberField
              v-else
              :model-value="typeof cost.amount === 'number' ? cost.amount : 0"
              @update:model-value="updateActionCost(index, 'amount', $event)"
              label="Стоимость"
              :min="1"
              density="compact"
              hide-details
              style="min-width: 120px;"
            />
            <v-btn
              icon
              size="small"
              color="error"
              variant="text"
              :disabled="isMandatoryActionPointCost(index)"
              @click="removeActionCost(index)"
            >
              <v-icon>mdi-delete</v-icon>
            </v-btn>
          </div>
          <v-btn
            variant="text"
            color="primary"
            size="small"
            @click="addActionCost"
          >
            <v-icon start>mdi-plus</v-icon>
            Добавить стоимость
          </v-btn>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel v-if="isProcess" value="process">
        <v-expansion-panel-title>Процесс</v-expansion-panel-title>
        <v-expansion-panel-text>
          <ProcessEditor
            :model-value="innerSpec.process ?? null"
            @update:model-value="(v) => patchSpec('process', v)"
            :resources="resources"
          />
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel v-if="isSpell" value="spell">
        <v-expansion-panel-title>Заклинание</v-expansion-panel-title>
        <v-expansion-panel-text>
          <SpellEditor
            :model-value="innerSpec.spell ?? null"
            @update:model-value="(v) => patchSpec('spell', v)"
            :items="items"
          />
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel value="upgrade">
        <v-expansion-panel-title>Улучшение</v-expansion-panel-title>
        <v-expansion-panel-text>
          <v-autocomplete
            :model-value="innerSpec.parent_ability_code"
            @update:model-value="patchSpec('parent_ability_code', $event ?? null)"
            :items="abilities"
            item-title="name"
            item-value="code"
            label="Родительская способность"
            density="compact"
            hide-details
            clearable
          />
          <div class="text-body-2 text-medium-emphasis mt-2">
            Если способность — улучшение другой, укажите родительскую способность.
          </div>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision'
import { useTagStore } from '@/modules/Roleplay/Rule/Tag/Store/tags'
import { useSourceStore } from '@/modules/Roleplay/Rule/Source/Store/sources'
import type { Rule } from '@/modules/Roleplay/Rule/Interface/types'
import type {
  AbilitySpec,
  AbilitySpecDraft,
  AbilityType,
  ZoneId,
  AbilityCost,
  Grant,
  CharacteristicRef,
  ResourceRef,
  AbilityRef,
  TagRef,
  SourceRef,
} from '@/modules/Roleplay/Rule/Interface/abilityTypes'
import {
  ABILITY_TYPE_LABELS,
  ABILITY_TYPE_TAGS,
  ABILITY_SPEC_FIELDS,
  resolveAbilityTypeFromTags,
  pruneAbilitySpecForType,
} from '@/modules/Roleplay/Rule/Interface/abilityTypes'
import RuleEditorBase from './RuleEditorBase.vue'
import RequirementListEditor from './RequirementListEditor.vue'
import GrantEditor from './GrantEditor.vue'
import ProcessEditor from './ProcessEditor.vue'
import SpellEditor from './SpellEditor.vue'
import DimensionalNumberInput from '@/modules/Core/Engine/Components/DimensionalNumberInput.vue'
import ClampedNumberField from '@/modules/Core/UI/Components/Input/ClampedNumberField.vue'

export type { AbilitySpec, ZoneId, AbilityCost, Grant } from '@/modules/Roleplay/Rule/Interface/abilityTypes'

const props = defineProps<{
  name: string
  code: string
  description: string
  mechanicId: number | null
  tagIds: number[]
  spec: AbilitySpecDraft | null
  mechanicOptions: { title: string; value: number }[]
  tagOptions: { title: string; value: number }[]
  spaceId: number
}>()

const emit = defineEmits<{
  'update:name': [value: string]
  'update:code': [value: string]
  'update:description': [value: string]
  'update:mechanicId': [value: number | null]
  'update:tagIds': [value: number[]]
  'update:spec': [value: AbilitySpec]
}>()

const revisionStore = useSpaceRevisionStore()
const tagStore = useTagStore()
const sourceStore = useSourceStore()

const expandedPanels = ref<string[]>(['general', 'zones', 'requirements', 'grants', 'action', 'upgrade'])

const typeOptions = Object.entries(ABILITY_TYPE_LABELS).map(([value, label]) => ({
  label,
  value: value as AbilityType,
}))

const allTypeTagCodes = new Set(Object.values(ABILITY_TYPE_TAGS).flat())

const currentType = computed<AbilityType | null>(() => {
  if (innerSpec.value.type) return innerSpec.value.type
  const codes = props.tagIds
    .map(id => tagStore.tags.find(t => t.id === id)?.code)
    .filter((c): c is string => !!c)
  return resolveAbilityTypeFromTags(codes)
})

const currentFields = computed(() => (currentType.value ? ABILITY_SPEC_FIELDS[currentType.value] : []))
const showAction = computed(() => currentFields.value.includes('action_costs'))
const isProcess = computed(() => currentFields.value.includes('process'))
const isSpell = computed(() => currentFields.value.includes('spell'))

const typeModel = computed<AbilityType | null>({
  get: () => currentType.value,
  set: (v) => setType(v),
})

const innerSpec = ref<AbilitySpecDraft>({
  zones: {},
  requirements: [],
  grants: [],
  action_costs: [],
  parent_ability_code: null,
})

const spaceRules = computed(() => revisionStore.effectiveRules)

const characteristics = computed<CharacteristicRef[]>(() =>
  spaceRules.value
    .filter((rule: Rule) => rule.type === 'characteristic' && rule.spaceId === props.spaceId && !rule.spec?.formula)
    .map(rule => ({ code: rule.code, name: rule.name }))
)

const resources = computed<ResourceRef[]>(() =>
  spaceRules.value
    .filter((rule: Rule) => rule.type === 'resource')
    .map(rule => ({ code: rule.code, name: rule.name, isDimensional: !!rule.spec?.is_dimensional }))
)

const abilities = computed<AbilityRef[]>(() =>
  spaceRules.value
    .filter((rule: Rule) => rule.type === 'ability')
    .map(rule => ({ code: rule.code, name: rule.name }))
)

const items = computed(() =>
  spaceRules.value
    .filter((rule: Rule) => rule.type === 'item')
    .map(rule => ({ code: rule.code, name: rule.name }))
)

const tags = computed<TagRef[]>(() =>
  tagStore.tags.map(t => ({ code: t.code, name: t.name }))
)

const abilityTags = computed<TagRef[]>(() => tags.value)

const sources = computed<SourceRef[]>(() => sourceStore.sources)

const zoneOptions = computed<{ label: string; value: string }[]>(() =>
  spaceRules.value
    .filter((rule: Rule) => rule.type === 'points')
    .map(rule => ({ label: rule.name, value: rule.code }))
)

function hasZone(zone: ZoneId): boolean {
  return !!innerSpec.value.zones[zone]
}

function zoneCost(zone: ZoneId): AbilityCost {
  return innerSpec.value.zones[zone] ?? { kind: 'array', levels_cost: [0] }
}

function progressionZone(zone: ZoneId) {
  const cost = innerSpec.value.zones[zone]
  if (cost && cost.kind === 'progression') return cost
  return null
}

function toggleZone(zone: ZoneId, checked: boolean) {
  const zones = { ...innerSpec.value.zones }
  if (checked) {
    zones[zone] = { kind: 'array', levels_cost: [0] }
  } else {
    delete zones[zone]
  }
  innerSpec.value = { ...innerSpec.value, zones }
}

function patchZone(zone: ZoneId, key: string, value: any) {
  const current = innerSpec.value.zones[zone]
  if (!current) return
  innerSpec.value = {
    ...innerSpec.value,
    zones: {
      ...innerSpec.value.zones,
      [zone]: { ...current, [key]: value } as AbilityCost,
    },
  }
}

function arrayCosts(zone: ZoneId): number[] {
  const cost = innerSpec.value.zones[zone]
  if (cost && cost.kind === 'array') return cost.levels_cost
  return []
}

function updateArrayCost(zone: ZoneId, index: number, value: number) {
  const cost = innerSpec.value.zones[zone]
  if (!cost || cost.kind !== 'array') return
  const levels_cost = [...cost.levels_cost]
  levels_cost[index] = Number(value) || 0
  innerSpec.value = {
    ...innerSpec.value,
    zones: {
      ...innerSpec.value.zones,
      [zone]: { kind: 'array', levels_cost },
    },
  }
}

function addArrayCost(zone: ZoneId) {
  const cost = innerSpec.value.zones[zone]
  if (!cost || cost.kind !== 'array') return
  innerSpec.value = {
    ...innerSpec.value,
    zones: {
      ...innerSpec.value.zones,
      [zone]: { kind: 'array', levels_cost: [...cost.levels_cost, 0] },
    },
  }
}

function removeArrayCost(zone: ZoneId) {
  const cost = innerSpec.value.zones[zone]
  if (!cost || cost.kind !== 'array' || cost.levels_cost.length <= 1) return
  innerSpec.value = {
    ...innerSpec.value,
    zones: {
      ...innerSpec.value.zones,
      [zone]: { kind: 'array', levels_cost: cost.levels_cost.slice(0, -1) },
    },
  }
}

function updateReqLevel(levelIndex: number, value: number) {
  const requirements = innerSpec.value.requirements.map((entry, i) =>
    i === levelIndex ? { ...entry, level: Number(value) || 1 } : entry
  )
  innerSpec.value = { ...innerSpec.value, requirements }
}

function updateRequirementLevelRequirements(levelIndex: number, reqs: any[]) {
  const requirements = innerSpec.value.requirements.map((entry, i) =>
    i === levelIndex ? { ...entry, requirements: reqs } : entry
  )
  innerSpec.value = { ...innerSpec.value, requirements }
}

function removeRequirementLevel(levelIndex: number) {
  const requirements = innerSpec.value.requirements.filter((_, i) => i !== levelIndex)
  innerSpec.value = { ...innerSpec.value, requirements }
}

function addRequirementLevel() {
  const requirements = [
    ...innerSpec.value.requirements,
    { level: 1, requirements: [] as any[] },
  ]
  innerSpec.value = { ...innerSpec.value, requirements }
}

function updateGrantLevel(levelIndex: number, value: number) {
  const grants = innerSpec.value.grants.map((entry, i) =>
    i === levelIndex ? { ...entry, level: Number(value) || 1 } : entry
  )
  innerSpec.value = { ...innerSpec.value, grants }
}

function updateGrant(levelIndex: number, grantIndex: number, grant: Grant) {
  const grants = innerSpec.value.grants.map((entry, i) => {
    if (i !== levelIndex) return entry
    const levelGrants = entry.grants.map((g, j) => (j === grantIndex ? grant : g))
    return { ...entry, grants: levelGrants }
  })
  innerSpec.value = { ...innerSpec.value, grants }
}

function removeGrant(levelIndex: number, grantIndex: number) {
  const grants = innerSpec.value.grants.map((entry, i) => {
    if (i !== levelIndex) return entry
    const levelGrants = entry.grants.filter((_, j) => j !== grantIndex)
    return { ...entry, grants: levelGrants }
  })
  innerSpec.value = { ...innerSpec.value, grants }
}

function addGrant(levelIndex: number) {
  const grants = innerSpec.value.grants.map((entry, i) => {
    if (i !== levelIndex) return entry
    const levelGrants = [...entry.grants, { type: 'tag', tag_code: '', remove: false } as Grant]
    return { ...entry, grants: levelGrants }
  })
  innerSpec.value = { ...innerSpec.value, grants }
}

function removeGrantLevel(levelIndex: number) {
  const grants = innerSpec.value.grants.filter((_, i) => i !== levelIndex)
  innerSpec.value = { ...innerSpec.value, grants }
}

function addGrantLevel() {
  const grants = [
    ...innerSpec.value.grants,
    { level: 1, grants: [] as Grant[] },
  ]
  innerSpec.value = { ...innerSpec.value, grants }
}

function actionResourceIsDimensional(resource_code: string): boolean {
  return resources.value.find(r => r.code === resource_code)?.isDimensional ?? false
}

function updateActionCost(index: number, key: 'resource_code' | 'amount', value: any) {
  let cost = { ...innerSpec.value.action_costs[index], [key]: value }
  if (key === 'resource_code') {
    const isDim = actionResourceIsDimensional(value)
    if (isDim && typeof cost.amount === 'number') {
      cost = { ...cost, amount: { base: cost.amount, size: 0 } }
    } else if (!isDim && cost.amount && typeof cost.amount === 'object' && !Array.isArray(cost.amount)) {
      cost = { ...cost, amount: cost.amount.base }
    }
    if (value === 'action-points' && isSpell.value) {
      cost = { ...cost, label: 'Сотворение' }
    }
  }
  const action_costs = innerSpec.value.action_costs.map((c, i) =>
    i === index ? cost : c
  )
  innerSpec.value = { ...innerSpec.value, action_costs }
}

function removeActionCost(index: number) {
  if (isMandatoryActionPointCost(index)) return
  innerSpec.value = {
    ...innerSpec.value,
    action_costs: innerSpec.value.action_costs.filter((_, i) => i !== index),
  }
}

function addActionCost() {
  innerSpec.value = {
    ...innerSpec.value,
    action_costs: [
      ...innerSpec.value.action_costs,
      { resource_code: '', amount: 0, label: isSpell.value ? 'Сотворение' : undefined },
    ],
  }
}

function patchSpec(key: string, value: any) {
  innerSpec.value = { ...innerSpec.value, [key]: value }
}

function syncTypeTags(type: AbilityType | null) {
  if (!type) return
  const wanted = ABILITY_TYPE_TAGS[type]
  const tagIds = props.tagIds.filter(id => {
    const tag = tagStore.tags.find(t => t.id === id)
    return tag && !allTypeTagCodes.has(tag.code)
  })
  for (const code of wanted) {
    const tag = tagStore.tags.find(t => t.code === code)
    if (tag && !tagIds.includes(tag.id)) tagIds.push(tag.id)
  }
  emit('update:tagIds', tagIds)
}

function setType(value: string | null) {
  const type = (value as AbilityType | null) ?? null
  innerSpec.value = { ...innerSpec.value, type: type ?? undefined }
  if (type) syncTypeTags(type)
  if (type === 'spell' || type === 'action') {
    ensureActionCost()
  }
}

function ensureActionCost() {
  const hasOd = innerSpec.value.action_costs.some(c => c.resource_code === 'action-points')
  if (hasOd) return
  innerSpec.value = {
    ...innerSpec.value,
    action_costs: [
      ...innerSpec.value.action_costs,
      {
        resource_code: 'action-points',
        amount: 1,
        label: isSpell.value ? 'Сотворение' : undefined,
      },
    ],
  }
}

function isMandatoryActionPointCost(index: number): boolean {
  const costs = innerSpec.value.action_costs
  const odIndex = costs.findIndex(c => c.resource_code === 'action-points')
  return odIndex === index
}

const specToEmit = computed<AbilitySpec | AbilitySpecDraft>(() => {
  const type = currentType.value
  if (!type) return innerSpec.value
  return pruneAbilitySpecForType(innerSpec.value, type)
})

watch(specToEmit, (value) => {
  emit('update:spec', JSON.parse(JSON.stringify(value)))
}, { deep: true })

onMounted(async () => {
  if (tagStore.tags.length === 0) {
    await tagStore.fetchTags()
  }
  if (sourceStore.sources.length === 0) {
    await sourceStore.fetchSources()
  }
  if (props.spec) {
    const loaded = JSON.parse(JSON.stringify(props.spec))
    innerSpec.value = {
      type: loaded.type,
      zones: loaded.zones ?? {},
      requirements: loaded.requirements ?? [],
      grants: loaded.grants ?? [],
      action_costs: loaded.action_costs ?? [],
      process: loaded.process,
      spell: loaded.spell,
      parent_ability_code: loaded.parent_ability_code ?? null,
    }
    if ((innerSpec.value.type === 'spell' || innerSpec.value.type === 'action') && !hasActionPointCost()) {
      ensureActionCost()
    }
  }
})

function hasActionPointCost(): boolean {
  return innerSpec.value.action_costs.some(c => c.resource_code === 'action-points')
}
</script>

<style scoped>
.gap-2 {
  gap: 8px;
}
.gap-4 {
  gap: 16px;
}
</style>
