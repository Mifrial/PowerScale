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

      <v-expansion-panel value="race">
        <v-expansion-panel-title>Раса</v-expansion-panel-title>
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
            class="mb-2"
          />
          <div class="text-body-2 text-medium-emphasis mb-2">
            Раса — терминальная точка цепочки (Вид → … → Раса). Родитель — всегда вид/подвид.
          </div>
          <ClampedNumberField
            :model-value="innerSpec.cost_os"
            @update:model-value="(v) => (innerSpec = { ...innerSpec, cost_os: v })"
            label="Стоимость (ОС)"
            density="compact"
            hide-details
          />
          <div class="text-body-2 text-medium-emphasis mt-2">
            Раса тратит ОС из бюджета игры. Стоимость — это цена расы; отрицательная стоимость
            даёт ОС.
          </div>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel value="characteristics">
        <v-expansion-panel-title>Характеристики</v-expansion-panel-title>
        <v-expansion-panel-text>
          <div class="text-body-2 text-medium-emphasis mb-2">
            «Базовая» — фиксированное значение (дальше правит только дары черт). «Докупаемая» —
            минимум (за 0 ОС) + таблица закупки «за N ОС → значение».
          </div>
          <div
            v-for="(c, index) in innerSpec.characteristics"
            :key="index"
            class="pa-2 mb-2 rounded bg-accent"
          >
            <div class="bg-surface rounded pa-2">
              <div class="d-flex ga-2 align-center mb-1">
                <v-autocomplete
                  :model-value="c.characteristic_code"
                  @update:model-value="(v) => updateCharacteristic(index, 'characteristic_code', v)"
                  :items="characteristicOptions"
                  item-title="name"
                  item-value="code"
                  label="Характеристика"
                  density="compact"
                  hide-details
                  class="flex-grow-1"
                />
                <v-radio-group
                  :model-value="c.mode"
                  @update:model-value="(v) => updateCharacteristic(index, 'mode', v)"
                  density="compact"
                  hide-details
                  inline
                >
                  <v-radio label="Базовая" value="fixed" />
                  <v-radio label="Докупаемая" value="purchased" />
                </v-radio-group>
                <v-btn
                  icon
                  size="small"
                  color="error"
                  variant="text"
                  @click="removeCharacteristic(index)"
                >
                  <v-icon>mdi-delete</v-icon>
                </v-btn>
              </div>

              <div v-if="c.mode === 'fixed'">
                <DimensionalNumberInput
                  :model-value="c.base"
                  @update:model-value="(v) => updateCharacteristicBase(index, v)"
                  label="Значение"
                  mode="characteristic"
                />
              </div>

              <div v-else>
                <DimensionalNumberInput
                  :model-value="c.base"
                  @update:model-value="(v) => updateCharacteristicBase(index, v)"
                  label="Минимум (за 0 ОС)"
                  mode="characteristic"
                />
                <div class="text-subtitle-2 mt-2 mb-1">Закупка</div>
                <div
                  v-for="(level, levelIndex) in c.purchase ?? []"
                  :key="levelIndex"
                  class="d-flex ga-2 align-center mb-1"
                >
                  <ClampedNumberField
                    :model-value="level.cost"
                    @update:model-value="(v) => updatePurchaseLevel(index, levelIndex, 'cost', v)"
                    label="ОС"
                    :min="1"
                    density="compact"
                    hide-details
                    style="min-width: 90px;"
                  />
                  <DimensionalNumberInput
                    :model-value="level.value"
                    @update:model-value="(v) => updatePurchaseLevel(index, levelIndex, 'value', v)"
                    label="Значение"
                  />
                  <v-btn
                    icon
                    size="small"
                    color="error"
                    variant="text"
                    @click="removePurchaseLevel(index, levelIndex)"
                  >
                    <v-icon>mdi-delete</v-icon>
                  </v-btn>
                </div>
                <v-btn
                  variant="text"
                  color="primary"
                  size="small"
                  @click="addPurchaseLevel(index)"
                >
                  <v-icon start>mdi-plus</v-icon>
                  Добавить уровень
                </v-btn>
              </div>
            </div>
          </div>
          <v-btn
            variant="text"
            color="primary"
            size="small"
            @click="addCharacteristic"
          >
            <v-icon start>mdi-plus</v-icon>
            Добавить характеристику
          </v-btn>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel value="abilities">
        <v-expansion-panel-title>Способности</v-expansion-panel-title>
        <v-expansion-panel-text>
          <div class="text-body-2 text-medium-emphasis mb-2">
            Свои способности расы. Способности предков-видов наследуются автоматически
            (см. «Превью наследования»).
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

      <v-expansion-panel value="preview">
        <v-expansion-panel-title>Превью наследования</v-expansion-panel-title>
        <v-expansion-panel-text>
          <div class="text-body-2 text-medium-emphasis mb-2">
            Способности, которые раса получит от цепочки предков-видов (ближний → дальний).
          </div>
          <div v-if="inheritedAbilities.length === 0" class="text-body-2 text-medium-emphasis">
            Родитель не выбран или у предков нет способностей.
          </div>
          <v-chip
            v-for="(ref, index) in inheritedAbilities"
            :key="index"
            size="small"
            class="mr-2 mb-2"
          >
            {{ abilityName(ref.ability_code) }} · от «{{ ref.fromName }}»
          </v-chip>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision'
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule'
import type { RaceSpec } from '@/modules/Roleplay/Rule/Dto/Race/RaceSpec'
import type { RuleSpec } from '@/modules/Roleplay/Rule/Enum/RuleSpec'
import type { RaceCharacteristic } from '@/modules/Roleplay/Rule/Dto/Race/RaceCharacteristic'
import type { RacePurchaseLevel } from '@/modules/Roleplay/Rule/Dto/Race/RacePurchaseLevel'
import type { RaceCharacteristicMode } from '@/modules/Roleplay/Rule/Enum/Race/RaceCharacteristicMode'
import type { InheritedAbilityRef } from '@/modules/Roleplay/Rule/Dto/Race/InheritedAbilityRef'
import type { CharacteristicSpec } from '@/modules/Roleplay/Rule/Dto/CharacteristicSpec'
import { raceSpecService } from '@/modules/Roleplay/Rule/Service/Spec/RaceSpecService'
import RuleEditorBase from './RuleEditorBase.vue'
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue'
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Value/DimensionalNumber'
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
  ruleId?: string
}>()

const emit = defineEmits<{
  'update:name': [value: string]
  'update:code': [value: string]
  'update:description': [value: string]
  'update:mechanicId': [value: number | null]
  'update:keywordIds': [value: number[]]
  'update:spec': [value: RaceSpec]
}>()

const revisionStore = useSpaceRevisionStore()

const expandedPanels = ref<string[]>(['general', 'race', 'characteristics', 'abilities', 'preview'])
const innerSpec = ref<RaceSpec>(raceSpecService.createEmptyRace())

const spaceRules = computed(() => revisionStore.effectiveRules)

const speciesOptions = computed(() =>
  spaceRules.value
    .filter((r: Rule) => r.type === 'species' && r.id !== props.ruleId)
    .map(r => ({ code: r.code, name: r.name }))
)

const characteristicOptions = computed(() =>
  spaceRules.value
    .filter((r: Rule) => r.type === 'characteristic' && r.spaceId === props.spaceId && !(r.spec as CharacteristicSpec | undefined)?.formula)
    .map(r => ({ code: r.code, name: r.name }))
)

const abilityOptions = computed(() =>
  spaceRules.value
    .filter((r: Rule) => r.type === 'ability')
    .map(r => ({ code: r.code, name: r.name }))
)

const abilityNameMap = computed(() => {
  const map = new Map<string, string>()
  for (const r of spaceRules.value) {
    if (r.type === 'ability') map.set(r.code, r.name)
  }
  return map
})

const inheritedAbilities = computed<InheritedAbilityRef[]>(() => {
  const byCode = new Map<string, Rule>()
  for (const r of spaceRules.value) byCode.set(r.code, r)
  return raceSpecService.collectInheritedAbilities(innerSpec.value.parent_race_code, byCode)
})

function abilityName(code: string): string {
  return abilityNameMap.value.get(code) ?? code
}

function updateCharacteristic(index: number, key: 'characteristic_code' | 'mode', value: string | null) {
  const v = value ?? (key === 'mode' ? 'fixed' : '')
  const list = innerSpec.value.characteristics.map((c, i) => {
    if (i !== index) return c
    const next: RaceCharacteristic = key === 'characteristic_code'
      ? { ...c, characteristic_code: v }
      : { ...c, mode: v as RaceCharacteristicMode }
    if (key === 'mode' && v === 'fixed') {
      delete next.purchase
    }
    return next
  })
  innerSpec.value = { ...innerSpec.value, characteristics: list }
}

function updateCharacteristicBase(index: number, v: DimensionalNumberValue | null) {
  const list = innerSpec.value.characteristics.map((c, i) =>
    i === index ? { ...c, base: v ?? { base: 3, size: 0 } } : c
  )
  innerSpec.value = { ...innerSpec.value, characteristics: list }
}

function removeCharacteristic(index: number) {
  innerSpec.value = {
    ...innerSpec.value,
    characteristics: innerSpec.value.characteristics.filter((_, i) => i !== index),
  }
}

function addCharacteristic() {
  const entry: RaceCharacteristic = {
    characteristic_code: '',
    mode: 'fixed',
    base: { base: 3, size: 0 },
  }
  innerSpec.value = {
    ...innerSpec.value,
    characteristics: [...innerSpec.value.characteristics, entry],
  }
}

function updatePurchaseLevel(index: number, levelIndex: number, key: 'cost' | 'value', value: number | DimensionalNumberValue | null) {
  const list = innerSpec.value.characteristics.map((c, i) => {
    if (i !== index) return c
    const purchase = (c.purchase ?? []).map((level, j) =>
      j === levelIndex
        ? key === 'cost'
          ? { ...level, cost: value as number }
          : { ...level, value: (value ?? { base: 3, size: 0 }) as DimensionalNumberValue }
        : level
    )
    return { ...c, purchase }
  })
  innerSpec.value = { ...innerSpec.value, characteristics: list }
}

function removePurchaseLevel(index: number, levelIndex: number) {
  const list = innerSpec.value.characteristics.map((c, i) =>
    i === index
      ? { ...c, purchase: (c.purchase ?? []).filter((_, j) => j !== levelIndex) }
      : c
  )
  innerSpec.value = { ...innerSpec.value, characteristics: list }
}

function addPurchaseLevel(index: number) {
  const list = innerSpec.value.characteristics.map((c, i) => {
    if (i !== index) return c
    const purchase: RacePurchaseLevel[] = [...(c.purchase ?? []), { cost: 1, value: { base: 3, size: 0 } }]
    return { ...c, purchase }
  })
  innerSpec.value = { ...innerSpec.value, characteristics: list }
}

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

const specToEmit = computed<RaceSpec>(() => structuredClone(innerSpec.value))

watch(specToEmit, (value) => {
  emit('update:spec', value)
}, { deep: true })

onMounted(() => {
  if (props.spec) {
    const loaded = structuredClone(props.spec as RaceSpec)
    innerSpec.value = {
      parent_race_code: loaded.parent_race_code ?? null,
      cost_os: loaded.cost_os ?? 0,
      characteristics: loaded.characteristics ?? [],
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
