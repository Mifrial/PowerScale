<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision'
import { useKeywordStore } from '@/modules/Roleplay/Rule/Store/keywords'
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule'
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec'
import type { AbilitySpecDraft } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecDraft'
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec'
import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType'
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement'
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant'
import type { CharacteristicRef } from '@/modules/Roleplay/Rule/Dto/Ability/CharacteristicRef'
import type { ResourceRef } from '@/modules/Roleplay/Rule/Dto/Ability/ResourceRef'
import type { CharacteristicSpec } from '@/modules/Roleplay/Rule/Dto/CharacteristicSpec'
import type { ResourceSpec } from '@/modules/Roleplay/Rule/Dto/ResourceSpec'
import type { AbilityRef } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityRef'
import type { KeywordRef } from '@/modules/Roleplay/Rule/Dto/Ability/KeywordRef'
import type { SourceRef } from '@/modules/Roleplay/Rule/Dto/Ability/SourceRef'
import { ABILITY_TYPE_LABELS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_TYPE_LABELS'
import { ABILITY_SPEC_FIELDS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_SPEC_FIELDS'
import { abilitySpecService } from '@/modules/Roleplay/Rule/Service/Spec/AbilitySpecService'
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue'
import RequirementListEditor from '@/modules/Roleplay/Rule/Component/Editors/RequirementListEditor.vue'
import GrantEditor from '@/modules/Roleplay/Rule/Component/Editors/GrantEditor.vue'
import ProcessEditor from '@/modules/Roleplay/Rule/Component/Editors/ProcessEditor.vue'
import SpellEditor from '@/modules/Roleplay/Rule/Component/Editors/SpellEditor.vue'
import ZoneCostsEditor from '@/modules/Roleplay/Rule/Component/Editors/ZoneCostsEditor.vue'
import ActionCostsEditor from '@/modules/Roleplay/Rule/Component/Editors/ActionCostsEditor.vue'
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue'

const props = defineProps<{
  name: string
  code: string
  codeDisabled?: boolean
  description: string
  mechanicId: number | null
  keywordIds: number[]
  spec: RuleSpec | null
  mechanicOptions: { title: string; value: number }[]
  keywordOptions: { title: string; value: number }[]
  spaceId: number
}>()

const emit = defineEmits<{
  'update:name': [value: string]
  'update:code': [value: string]
  'update:description': [value: string]
  'update:mechanicId': [value: number | null]
  'update:keywordIds': [value: number[]]
  'update:spec': [value: AbilitySpec]
}>()

const revisionStore = useSpaceRevisionStore()
const keywordStore = useKeywordStore()

const expandedPanels = ref<string[]>(['general', 'zones', 'requirements', 'grants', 'action', 'upgrade'])

const typeOptions = Object.entries(ABILITY_TYPE_LABELS).map(([value, label]) => ({
  label,
  value: value as AbilityType,
}))

const currentType = computed<AbilityType | null>(() => {
  if (innerSpec.value.type) return innerSpec.value.type
  const codes = props.keywordIds
    .map(id => keywordStore.keywords.find(t => t.id === id)?.code)
    .filter((c): c is string => !!c)
  return abilitySpecService.resolveTypeFromKeywords(codes)
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
    .filter((rule: Rule) => rule.type === 'characteristic' && rule.spaceId === props.spaceId && !(rule.spec as CharacteristicSpec | undefined)?.formula)
    .map(rule => ({ code: rule.code, name: rule.name }))
)

const resources = computed<ResourceRef[]>(() =>
  spaceRules.value
    .filter((rule: Rule) => rule.type === 'resource')
    .map(rule => ({ code: rule.code, name: rule.name, isDimensional: !!(rule.spec as ResourceSpec | undefined)?.is_dimensional }))
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

const keywords = computed<KeywordRef[]>(() =>
  keywordStore.keywords.map(t => ({ code: t.code, name: t.name }))
)

const abilityKeywords = computed<KeywordRef[]>(() => keywords.value)

const sources = computed<SourceRef[]>(() =>
  spaceRules.value
    .filter((rule: Rule) => rule.type === 'source')
    .map(rule => ({ code: rule.code, name: rule.name }))
)

const zoneOptions = computed<{ label: string; value: string }[]>(() =>
  spaceRules.value
    .filter((rule: Rule) => rule.type === 'points')
    .map(rule => ({ label: rule.name, value: rule.code }))
)

function updateReqLevel(levelIndex: number, value: number) {
  innerSpec.value = abilitySpecService.updateReqLevel(innerSpec.value, levelIndex, value)
}

function updateRequirementLevelRequirements(levelIndex: number, reqs: Requirement[]) {
  innerSpec.value = abilitySpecService.updateRequirementLevelRequirements(innerSpec.value, levelIndex, reqs)
}

function removeRequirementLevel(levelIndex: number) {
  innerSpec.value = abilitySpecService.removeRequirementLevel(innerSpec.value, levelIndex)
}

function addRequirementLevel() {
  innerSpec.value = abilitySpecService.addRequirementLevel(innerSpec.value)
}

function updateGrantLevel(levelIndex: number, value: number) {
  innerSpec.value = abilitySpecService.updateGrantLevel(innerSpec.value, levelIndex, value)
}

function updateGrant(levelIndex: number, grantIndex: number, grant: Grant) {
  innerSpec.value = abilitySpecService.updateGrant(innerSpec.value, levelIndex, grantIndex, grant)
}

function removeGrant(levelIndex: number, grantIndex: number) {
  innerSpec.value = abilitySpecService.removeGrant(innerSpec.value, levelIndex, grantIndex)
}

function addGrant(levelIndex: number) {
  innerSpec.value = abilitySpecService.addGrant(innerSpec.value, levelIndex)
}

function removeGrantLevel(levelIndex: number) {
  innerSpec.value = abilitySpecService.removeGrantLevel(innerSpec.value, levelIndex)
}

function addGrantLevel() {
  innerSpec.value = abilitySpecService.addGrantLevel(innerSpec.value)
}

function patchSpec(key: string, value: unknown) {
  innerSpec.value = { ...innerSpec.value, [key]: value }
}

function setType(value: string | null) {
  const type = (value as AbilityType | null) ?? null
  innerSpec.value = { ...innerSpec.value, type: type ?? undefined }
  if (type) {
    emit('update:keywordIds', abilitySpecService.syncTypeTags(type, props.keywordIds, keywordStore.keywords))
  }
  if (type === 'spell' || type === 'action') {
    innerSpec.value = abilitySpecService.ensureActionCost(innerSpec.value, isSpell.value)
  }
}

const specToEmit = computed<AbilitySpec | AbilitySpecDraft>(() => {
  const type = currentType.value
  if (!type) return innerSpec.value
  return abilitySpecService.prune(innerSpec.value, type)
})

watch(specToEmit, (value) => {
  emit('update:spec', structuredClone(value) as AbilitySpec)
}, { deep: true })

onMounted(async () => {
  if (keywordStore.keywords.length === 0) {
    await keywordStore.fetchTags()
  }
  if (props.spec) {
    const loaded = structuredClone(props.spec as AbilitySpecDraft)
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
      innerSpec.value = abilitySpecService.ensureActionCost(innerSpec.value, isSpell.value)
    }
  }
})

function hasActionPointCost(): boolean {
  return innerSpec.value.action_costs.some(c => c.resource_code === 'action-points')
}
</script>

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
            :code-disabled="codeDisabled"
            :description="description"
            @update:description="(v) => emit('update:description', v)"
            :mechanic-id="mechanicId"
            @update:mechanic-id="(v) => emit('update:mechanicId', v)"
            :keyword-ids="keywordIds"
            @update:keyword-ids="(v) => emit('update:keywordIds', v)"
            :mechanic-options="mechanicOptions"
            :keyword-options="keywordOptions"
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
          <ZoneCostsEditor
            :model-value="innerSpec.zones"
            @update:model-value="(v) => patchSpec('zones', v)"
            :zone-options="zoneOptions"
          />
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
                :keywords="keywords"
                :ability-keywords="abilityKeywords"
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
                  :keywords="keywords"
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
          <ActionCostsEditor
            :model-value="innerSpec.action_costs"
            @update:model-value="(v) => patchSpec('action_costs', v)"
            :resources="resources"
            :is-spell="isSpell"
          />
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

<style scoped>
.gap-2 {
  gap: 8px;
}
.gap-4 {
  gap: 16px;
}
</style>
