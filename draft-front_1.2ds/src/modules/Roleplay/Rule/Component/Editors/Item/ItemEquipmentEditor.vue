<script setup lang="ts">
import { ref, watch } from 'vue'
import type { WeaponBlock } from '@/modules/Roleplay/Rule/Dto/Item/WeaponBlock'
import type { ArmorBlock } from '@/modules/Roleplay/Rule/Dto/Item/ArmorBlock'
import type { ShieldBlock } from '@/modules/Roleplay/Rule/Dto/Item/ShieldBlock'
import { ITEM_SUBTYPES } from '@/modules/Roleplay/Rule/Constant/Item/ITEM_SUBTYPES'
import WeaponEditor from '@/modules/Roleplay/Rule/Component/Editors/Item/WeaponEditor.vue'
import ArmorEditor from '@/modules/Roleplay/Rule/Component/Editors/Item/ArmorEditor.vue'
import ShieldEditor from '@/modules/Roleplay/Rule/Component/Editors/Item/ShieldEditor.vue'

const props = defineProps<{
  subtypes: string[]
  weapon: WeaponBlock | null
  armor: ArmorBlock | null
  shield: ShieldBlock | null
  damageTypes: { code: string; name: string }[]
  sources: { code: string; name: string }[]
  characteristics: { code: string; name: string }[]
  strengthCode: string
  dexterityCode: string
}>()

const emit = defineEmits<{
  'update:subtypes': [value: string[]]
  'update:weapon': [value: WeaponBlock]
  'update:armor': [value: ArmorBlock]
  'update:shield': [value: ShieldBlock]
}>()

const subtypes = ref<string[]>([...props.subtypes])
const expandedEquipmentPanels = ref<string[]>(['weapon', 'armor', 'shield'])

watch(() => props.subtypes, (value) => {
  if (JSON.stringify(value) !== JSON.stringify(subtypes.value)) {
    subtypes.value = [...value]
  }
}, { deep: true })

watch(subtypes, (value) => {
  emit('update:subtypes', [...value])
}, { deep: true })
</script>

<template>
  <div>
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
          <WeaponEditor
            v-if="weapon"
            :weapon="weapon"
            @update:weapon="(v: WeaponBlock) => emit('update:weapon', v)"
            :damage-types="damageTypes"
            :sources="sources"
            :characteristics="characteristics"
            :strength-code="strengthCode"
          />
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel v-if="subtypes.includes('armor')" value="armor">
        <v-expansion-panel-title>Броня</v-expansion-panel-title>
        <v-expansion-panel-text>
          <ArmorEditor
            v-if="armor"
            :armor="armor"
            @update:armor="(v: ArmorBlock) => emit('update:armor', v)"
            :damage-types="damageTypes"
            :sources="sources"
            :characteristics="characteristics"
            :dexterity-code="dexterityCode"
          />
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel v-if="subtypes.includes('shield')" value="shield">
        <v-expansion-panel-title>Щит</v-expansion-panel-title>
        <v-expansion-panel-text>
          <ShieldEditor
            v-if="shield"
            :shield="shield"
            @update:shield="(v: ShieldBlock) => emit('update:shield', v)"
            :damage-types="damageTypes"
            :sources="sources"
          />
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
