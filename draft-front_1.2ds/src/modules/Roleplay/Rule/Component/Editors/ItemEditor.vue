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
              :items="categories"
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
            <div class="d-flex gap-2 align-center mt-2">
              <label class="text-body-2 font-weight-medium" style="min-width: 120px;">Типы снаряжения</label>
              <v-checkbox
                v-for="st in ITEM_SUBTYPES"
                :key="st.value"
                v-model="subtypes"
                :label="st.label"
                :value="st.value"
                hide-details
              />
            </div>

            <v-expansion-panels v-model="expandedEquipmentPanels" multiple class="mt-2">
              <v-expansion-panel v-if="subtypes.includes('weapon')" value="weapon">
                <v-expansion-panel-title>Оружие</v-expansion-panel-title>
                <v-expansion-panel-text>
                  <template v-if="spec.weapon">
                    <DimensionalNumberInput
                      v-model="spec.weapon.min_strength"
                      label="Минимальная сила"
                      mode="characteristic"
                    />

                    <BlockProfileEditor
                      v-model="spec.weapon.block_profile"
                      :damage-types="damageTypes"
                      :sources="sources"
                    />

                  <v-expansion-panels v-model="expandedProfiles" multiple class="mt-2">
                    <v-expansion-panel
                      v-for="(profile, index) in spec.weapon.weapon_profiles"
                      :key="index"
                    >
                      <v-expansion-panel-title>
                        <div class="d-flex align-center w-100">
                          <span>Профиль {{ index + 1 }}</span>
                          <v-spacer />
                          <v-select
                            :model-value="spec.weapon.weapon_profiles[index].type"
                            @update:model-value="(v: string) => updateProfileType(index, v)"
                            :items="profileTypes"
                            item-title="label"
                            item-value="value"
                            density="compact"
                            hide-details
                            variant="plain"
                            style="max-width: 110px;"
                            @click.stop
                            label="Тип атаки"
                          />
                        </div>
                      </v-expansion-panel-title>
                      <v-expansion-panel-text>
                        <WeaponProfileEditor
                          v-model="spec.weapon.weapon_profiles[index]"
                          :damage-types="damageTypes"
                          :characteristics="characteristics"
                          @remove="removeWeaponProfile(index)"
                        />
                      </v-expansion-panel-text>
                    </v-expansion-panel>
                  </v-expansion-panels>
                  <v-btn
                    variant="text"
                    color="primary"
                    @click="addWeaponProfile"
                    class="mt-2"
                  >
                    <v-icon start>mdi-plus</v-icon>
                    Добавить профиль
                  </v-btn>
                  </template>
                </v-expansion-panel-text>
              </v-expansion-panel>

              <v-expansion-panel v-if="subtypes.includes('armor')" value="armor">
                <v-expansion-panel-title>Броня</v-expansion-panel-title>
                <v-expansion-panel-text>
                  <template v-if="spec.armor">
                  <DefenseSlotsEditor
                    v-model="spec.armor.defense_slots"
                    :sources="sources"
                  />
                  <ResistanceSlotsEditor
                    v-model="spec.armor.resistance_slots"
                    :damage-types="damageTypes"
                    :sources="sources"
                    class="mt-2"
                  />
                  <CharacteristicLimitsEditor
                    v-model="spec.armor.characteristic_limits"
                    :characteristics="characteristics"
                    :default-characteristic-code="dexterityCode"
                  />
                  </template>
                </v-expansion-panel-text>
              </v-expansion-panel>

              <v-expansion-panel v-if="subtypes.includes('shield')" value="shield">
                <v-expansion-panel-title>Щит</v-expansion-panel-title>
                <v-expansion-panel-text>
                  <template v-if="spec.shield">
                  <DimensionalNumberInput
                    v-model="spec.shield.min_strength"
                    label="Минимальная сила"
                    mode="characteristic"
                  />
                  <BlockProfileEditor
                    v-model="spec.shield.block"
                    :damage-types="damageTypes"
                    :sources="sources"
                    :show-toggle="false"
                  />
                  </template>
                </v-expansion-panel-text>
              </v-expansion-panel>
            </v-expansion-panels>
          </template>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision'
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule'
import RuleEditorBase from './RuleEditorBase.vue'
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue'
import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula'
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec'
import type { ItemSpecDraft } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpecDraft'
import type { RuleSpec } from '@/modules/Roleplay/Rule/Enum/RuleSpec'
import type { WeaponProfile } from '@/modules/Roleplay/Rule/Dto/Item/WeaponProfile'
import type { BlockProfile } from '@/modules/Roleplay/Rule/Dto/Item/BlockProfile'
import type { DefenseSlot } from '@/modules/Roleplay/Rule/Dto/Item/DefenseSlot'
import type { ResistanceSlot } from '@/modules/Roleplay/Rule/Dto/Item/ResistanceSlot'
import type { CharacteristicLimit } from '@/modules/Roleplay/Rule/Dto/Item/CharacteristicLimit'
import type { CharacteristicSpec } from '@/modules/Roleplay/Rule/Dto/CharacteristicSpec'
import { ITEM_SUBTYPES } from '@/modules/Roleplay/Rule/Constant/Item/ITEM_SUBTYPES'
import { itemSpecService } from '@/modules/Roleplay/Rule/Service/Spec/ItemSpecService'
import WeaponProfileEditor from '../WeaponProfileEditor.vue'
import BlockProfileEditor from '../BlockProfileEditor.vue'
import CharacteristicLimitsEditor from '../CharacteristicLimitsEditor.vue'
import DefenseSlotsEditor from '../DefenseSlotsEditor.vue'
import ResistanceSlotsEditor from '../ResistanceSlotsEditor.vue'
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
const expandedProfiles = ref<number[]>([])
const expandedPanels = ref<string[]>(['general', 'item'])
const expandedEquipmentPanels = ref<string[]>(['weapon', 'armor', 'shield'])

const categories = [
  { label: 'Деньги', value: 'money' },
  { label: 'Снаряжение', value: 'equipment' },
  { label: 'Прочее', value: 'other' }
]

const profileTypes = [
  { label: 'Удар', value: 'strike' },
  { label: 'Бросок', value: 'throw' },
  { label: 'Выстрел', value: 'shoot' }
]

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
  return spaceRules.value
    .filter((rule: Rule) => 
      rule.type === 'characteristic' && 
      rule.spaceId === props.spaceId &&
      !(rule.spec as CharacteristicSpec | undefined)?.formula
    )
    .map(rule => ({ code: rule.code, name: rule.name }))
})

const sources = computed(() => {
  return spaceRules.value
    .filter((rule: Rule) => rule.type === 'source')
    .map(rule => ({ code: rule.code, name: rule.name }))
})

const strengthCode = computed(() => {
  const found = characteristics.value.find(c => c.name === 'Сила')
  return found ? found.code : ''
})

const dexterityCode = computed(() => {
  const found = characteristics.value.find(c => c.name === 'Ловкость')
  return found ? found.code : ''
})

function addWeaponProfile() {
  if (!spec.value.weapon) {
    spec.value.weapon = {
      min_strength: { base: 3, size: 0 },
      block_profile: null,
      weapon_profiles: []
    }
  }
  spec.value.weapon.weapon_profiles.push({
    type: 'strike',
    distance: { type: 'fixed', value: 0 },
    range: null,
    damage: { formula: { type: 'characteristic', characteristic_code: strengthCode.value, modifier: 0 }, damage_type_code: null },
    penetration: { type: 'characteristic', characteristic_code: strengthCode.value, modifier: 0 },
    accuracy: { base: 3, size: 0 }
  })
}

function updateProfileType(index: number, type: string) {
  if (spec.value.weapon) {
    spec.value.weapon.weapon_profiles[index].type = type as 'strike' | 'throw' | 'shoot'
  }
}

function removeWeaponProfile(index: number) {
  spec.value.weapon?.weapon_profiles.splice(index, 1)
}

watch(subtypes, (newSubtypes) => {
  if (newSubtypes.includes('weapon') && !spec.value.weapon) {
    spec.value.weapon = {
      min_strength: { base: 3, size: 0 },
      block_profile: null,
      weapon_profiles: []
    }
  }
  if (newSubtypes.includes('armor') && !spec.value.armor) {
    spec.value.armor = {
      defense_slots: [],
      resistance_slots: [],
      characteristic_limits: []
    }
  }
  if (newSubtypes.includes('shield') && !spec.value.shield) {
    spec.value.shield = {
      min_strength: { base: 3, size: 0 },
      block: {
        efficiency: { base: 3, size: 0 },
        defense: 0,
        resistances: []
      }
    }
  }
})

const specToEmit = computed<ItemSpec>(() => itemSpecService.prune(spec.value, subtypes.value))

watch(specToEmit, (value) => {
  emit('update:spec', structuredClone(value))
}, { deep: true })

onMounted(async () => {
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

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
