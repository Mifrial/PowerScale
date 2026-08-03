<script setup lang="ts">
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { useVModelSync } from '@/modules/Core/UI/Composables/useVModelSync';

interface ResistanceSlot {
  damage_type_code: string | null;
  value: number;
  durability: number;
  source_code: string | null;
}

const props = defineProps<{
  modelValue: ResistanceSlot[];
  damageTypes: { code: string; name: string }[];
  sources: { code: string; name: string }[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: ResistanceSlot[]];
}>();

const { inner: localSlots } = useVModelSync<ResistanceSlot[]>({
  modelValue: () => props.modelValue,
  onCommit: (value) => emit('update:modelValue', value),
  clone: false,
});

function addSlot() {
  localSlots.value.push({
    damage_type_code: null,
    value: 1,
    durability: 1,
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
      <v-card-title class="text-subtitle-1">Слоты сопротивлений</v-card-title>
      <v-table density="compact">
        <thead>
          <tr>
            <th>Тип урона</th>
            <th>Значение</th>
            <th>Надёжность</th>
            <th>Источник</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(slot, index) in localSlots" :key="index">
            <td>
              <v-autocomplete
                v-model="slot.damage_type_code"
                :items="damageTypes"
                item-title="name"
                item-value="code"
                label="Тип урона"
                density="compact"
                hide-details
              />
            </td>
            <td>
              <ClampedNumberField v-model="slot.value" :min="1" density="compact" hide-details />
            </td>
            <td>
              <ClampedNumberField v-model="slot.durability" :min="1" density="compact" hide-details />
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
