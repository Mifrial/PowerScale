<script setup lang="ts">
import { computed, ref, watch } from 'vue';

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
  pixels: number;
  submitting: boolean;
}>();

const emit = defineEmits<{
  apply: [millimetersPerPixel: number];
}>();

const meters = ref<number | null>(null);

const millimetersPerPixel = computed(() => {
  if (!meters.value || meters.value <= 0 || props.pixels <= 0) return null;

  return Math.round((meters.value * 1000) / props.pixels);
});

watch(open, (value) => {
  if (value) meters.value = null;
});

function apply(): void {
  if (millimetersPerPixel.value === null) return;
  emit('apply', millimetersPerPixel.value);
}
</script>

<template>
  <v-dialog v-model="open" max-width="420" :persistent="props.submitting">
    <v-card>
      <v-card-title class="text-body-1 font-weight-medium">Калибровка масштаба</v-card-title>
      <v-card-text>
        <p class="text-body-2 mb-3">
          Отрезок на подложке: {{ Math.round(props.pixels) }} px. Укажите, сколько это метров в мире.
        </p>
        <v-text-field
          v-model.number="meters"
          type="number"
          min="1"
          label="Метров"
          variant="outlined"
          density="comfortable"
          autofocus
        />
        <p v-if="millimetersPerPixel !== null" class="text-caption mt-2">{{ millimetersPerPixel }} мм на пиксель</p>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" :disabled="props.submitting" @click="open = false">Отмена</v-btn>
        <v-btn
          color="primary"
          variant="tonal"
          :loading="props.submitting"
          :disabled="millimetersPerPixel === null"
          @click="apply"
        >
          Применить
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
