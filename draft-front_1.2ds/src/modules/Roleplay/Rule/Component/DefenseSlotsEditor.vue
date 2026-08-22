<script setup lang="ts">
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import { useVModelSync } from '@/modules/Core/UI/Composables/useVModelSync';
import type { DefenseSlot } from '@/modules/Roleplay/Rule/Dto/Item/DefenseSlot';

const props = defineProps<{
  modelValue: DefenseSlot[];
  sources: { code: string; name: string }[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: DefenseSlot[]];
}>();

const { inner: localSlots } = useVModelSync<DefenseSlot[]>({
  modelValue: () => props.modelValue,
  onCommit: (value) => emit('update:modelValue', value),
  clone: false,
});

function addSlot() {
  localSlots.value.push({
    defense: { base: 0, size: 0 },
    durability: 0,
    source_code: null,
  });
}

function removeSlot(index: number) {
  localSlots.value.splice(index, 1);
}
</script>

<template>
  <div>
    <v-card variant="outlined" class="pa-3">
      <v-card-title class="text-subtitle-1">Слоты защиты</v-card-title>
      <v-table density="compact">
        <thead>
          <tr>
            <th>Защита</th>
            <th>Надёжность</th>
            <th>Источник</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(slot, index) in localSlots" :key="index">
            <td>
              <DimensionalNumberInput v-model="slot.defense" label="Защита" />
            </td>
            <td>
              <v-text-field v-model.number="slot.durability" type="number" density="compact" hide-details />
            </td>
            <td>
              <v-autocomplete
                v-model="slot.source_code"
                :items="sources"
                item-title="name"
                item-value="code"
                density="compact"
                hide-details
                clearable
              />
            </td>
            <td>
              <v-btn icon size="small" color="error" @click="removeSlot(index)">
                <v-icon>mdi-delete</v-icon>
              </v-btn>
            </td>
          </tr>
        </tbody>
      </v-table>
      <v-btn variant="text" color="primary" @click="addSlot" class="mt-2">
        <v-icon start>mdi-plus</v-icon>
        Добавить слот
      </v-btn>
    </v-card>
  </div>
</template>
