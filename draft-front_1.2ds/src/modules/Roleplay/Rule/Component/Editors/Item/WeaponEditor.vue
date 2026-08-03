<script setup lang="ts">
import { ref, watch } from 'vue';
import type { WeaponBlock } from '@/modules/Roleplay/Rule/Dto/Item/WeaponBlock';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import BlockProfileEditor from '@/modules/Roleplay/Rule/Component/BlockProfileEditor.vue';
import WeaponProfileEditor from '@/modules/Roleplay/Rule/Component/WeaponProfileEditor.vue';
import { WEAPON_PROFILE_TYPES } from '@/modules/Roleplay/Rule/Constant/Item/WEAPON_PROFILE_TYPES';
import { itemSpecService } from '@/modules/Roleplay/Rule/Service/Spec/ItemSpecService';

const props = defineProps<{
  weapon: WeaponBlock;
  damageTypes: { code: string; name: string }[];
  sources: { code: string; name: string }[];
  characteristics: { code: string; name: string }[];
  strengthCode: string;
}>();

const emit = defineEmits<{
  'update:weapon': [value: WeaponBlock];
}>();

const inner = ref<WeaponBlock>(structuredClone(props.weapon));
const expandedProfiles = ref<number[]>([]);

watch(
  () => props.weapon,
  (value) => {
    if (JSON.stringify(value) !== JSON.stringify(inner.value)) {
      inner.value = structuredClone(value);
    }
  },
  { deep: true },
);

watch(
  inner,
  (value) => {
    emit('update:weapon', structuredClone(value));
  },
  { deep: true },
);

function addProfile() {
  itemSpecService.addWeaponProfile(inner.value, props.strengthCode);
}

function changeProfileType(index: number, type: string) {
  if (type === 'strike' || type === 'throw' || type === 'shoot') {
    itemSpecService.updateProfileType(inner.value, index, type);
  }
}

function removeProfile(index: number) {
  itemSpecService.removeWeaponProfile(inner.value, index);
}
</script>

<template>
  <div>
    <DimensionalNumberInput v-model="inner.min_strength" label="Минимальная сила" mode="characteristic" />

    <BlockProfileEditor v-model="inner.block_profile" :damage-types="damageTypes" :sources="sources" />

    <v-expansion-panels v-model="expandedProfiles" multiple class="mt-2">
      <v-expansion-panel v-for="(profile, index) in inner.weapon_profiles" :key="index">
        <v-expansion-panel-title>
          <div class="d-flex align-center w-100">
            <span>Профиль {{ index + 1 }}</span>
            <v-spacer />
            <v-select
              :model-value="inner.weapon_profiles[index].type"
              @update:model-value="(v: string) => changeProfileType(index, v)"
              :items="WEAPON_PROFILE_TYPES"
              item-title="label"
              item-value="value"
              density="compact"
              hide-details
              variant="plain"
              style="max-width: 110px"
              @click.stop
              label="Тип атаки"
            />
          </div>
        </v-expansion-panel-title>
        <v-expansion-panel-text>
          <WeaponProfileEditor
            v-model="inner.weapon_profiles[index]"
            :damage-types="damageTypes"
            :characteristics="characteristics"
            @remove="removeProfile(index)"
          />
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>

    <v-btn variant="text" color="primary" @click="addProfile" class="mt-2">
      <v-icon start>mdi-plus</v-icon>
      Добавить профиль
    </v-btn>
  </div>
</template>
