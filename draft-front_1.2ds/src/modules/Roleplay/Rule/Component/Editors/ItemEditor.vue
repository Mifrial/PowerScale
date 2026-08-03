<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision'
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule'
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec'
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec'
import type { ItemSpecDraft } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpecDraft'
import type { WeaponBlock } from '@/modules/Roleplay/Rule/Dto/Item/WeaponBlock'
import type { ArmorBlock } from '@/modules/Roleplay/Rule/Dto/Item/ArmorBlock'
import type { ShieldBlock } from '@/modules/Roleplay/Rule/Dto/Item/ShieldBlock'
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue'
import ItemEquipmentEditor from '@/modules/Roleplay/Rule/Component/Editors/Item/ItemEquipmentEditor.vue'
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue'
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue'
import { ITEM_CATEGORIES } from '@/modules/Roleplay/Rule/Constant/Item/ITEM_CATEGORIES'
import { itemSpecService } from '@/modules/Roleplay/Rule/Service/Spec/ItemSpecService'
import { ruleReferenceService } from '@/modules/Roleplay/Rule/Service/RuleReferenceService'

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
  'update:spec': [value: ItemSpec]
}>()

const revisionStore = useSpaceRevisionStore()

const spec = ref<ItemSpecDraft>({
  category: 'other',
  cost_gm: null,
  weight: { base: 1, size: -1 },
  special_rule_codes: [],
  innate: false,
})

const subtypes = ref<string[]>([])
const expandedPanels = ref<string[]>(['general', 'item'])

const spaceRules = computed(() => revisionStore.effectiveRules)

const simpleRules = computed(() => {
  return spaceRules.value
    .filter((rule: Rule) => rule.type === 'simple')
    .map(rule => ({ code: rule.code, name: rule.name }))
})

const damageTypes = computed(() => {
  const map = new Map<string, { code: string; name: string }>()
  for (const rule of spaceRules.value) {
    if (rule.type === 'damage_type') {
      const code = rule.code
      if (!map.has(code)) {
        map.set(code, { code, name: rule.name })
      }
    }
  }
  return Array.from(map.values())
})

const characteristics = computed(() => {
  return ruleReferenceService.characteristicOptions(spaceRules.value, props.spaceId)
})

const sources = computed(() => {
  return ruleReferenceService.sourceOptions(spaceRules.value)
})

const strengthCode = computed(() => {
  const found = characteristics.value.find(c => c.name === 'Сила')
  return found ? found.code : ''
})

const dexterityCode = computed(() => {
  const found = characteristics.value.find(c => c.name === 'Ловкость')
  return found ? found.code : ''
})

watch(subtypes, (newSubtypes) => {
  if (newSubtypes.includes('weapon')) {
    itemSpecService.ensureWeapon(spec.value)
  }
  if (newSubtypes.includes('armor')) {
    itemSpecService.ensureArmor(spec.value)
  }
  if (newSubtypes.includes('shield')) {
    itemSpecService.ensureShield(spec.value)
  }
})

const specToEmit = computed<ItemSpec>(() => itemSpecService.prune(spec.value, subtypes.value))

watch(specToEmit, (value) => {
  emit('update:spec', structuredClone(value))
}, { deep: true })

function updateWeapon(value: WeaponBlock) {
  spec.value.weapon = value
}

function updateArmor(value: ArmorBlock) {
  spec.value.armor = value
}

function updateShield(value: ShieldBlock) {
  spec.value.shield = value
}

onMounted(() => {
  if (props.spec) {
    const loaded = props.spec as ItemSpecDraft
    spec.value = structuredClone(loaded)

    if (loaded.weapon) {
      subtypes.value.push('weapon')
    }
    if (loaded.armor) {
      subtypes.value.push('armor')
    }
    if (loaded.shield) {
      subtypes.value.push('shield')
    }
  }
})
</script>

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

      <v-expansion-panel value="item">
        <v-expansion-panel-title>
          <div class="d-flex align-center w-100">
            <span>Предмет</span>
            <v-spacer />
            <v-select
              v-model="spec.category"
              :items="ITEM_CATEGORIES"
              label="Тип предмета"
              item-title="label"
              item-value="value"
              density="compact"
              hide-details
              variant="plain"
              style="max-width: 140px;"
              @click.stop
            />
          </div>
        </v-expansion-panel-title>
        <v-expansion-panel-text>
          <v-switch
            v-model="spec.innate"
            label="Естественный предмет"
            color="primary"
            hide-details
          />
          <div class="text-body-2 text-medium-emphasis mt-1 mb-2">
            Естественные предметы (врождённое оружие/броня) скрывают вес и стоимость.
          </div>

          <div class="d-flex gap-2 mb-2">
            <ClampedNumberField
              :model-value="spec.cost_gm ?? 0"
              @update:model-value="(v: number) => spec.cost_gm = v"
              label="Стоимость (gm)"
              :min="0"
              density="compact"
              hide-details
              style="flex: 1 1 auto;"
            />
            <DimensionalNumberInput
              v-model="spec.weight"
              label="Вес"
            />
          </div>

          <v-autocomplete
            v-model="spec.special_rule_codes"
            :items="simpleRules"
            item-title="name"
            item-value="code"
            label="Спецправила"
            multiple
            chips
            closable-chips
            density="compact"
          />

          <template v-if="spec.category === 'equipment'">
            <ItemEquipmentEditor
              v-model:subtypes="subtypes"
              :weapon="spec.weapon ?? null"
              @update:weapon="updateWeapon"
              :armor="spec.armor ?? null"
              @update:armor="updateArmor"
              :shield="spec.shield ?? null"
              @update:shield="updateShield"
              :damage-types="damageTypes"
              :sources="sources"
              :characteristics="characteristics"
              :strength-code="strengthCode"
              :dexterity-code="dexterityCode"
            />
          </template>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
