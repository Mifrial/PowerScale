<script setup lang="ts">
defineProps<{
  items: { key: string; label: string }[];
  modelValue: string;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: string];
}>();
</script>

<template>
  <div class="d-flex ga-1">
    <v-btn
      v-for="f in items"
      :key="f.key"
      :variant="modelValue === f.key ? 'flat' : 'outlined'"
      color="primary"
      size="x-small"
      class="rounded-pill"
      @click="emit('update:modelValue', f.key)"
    >
      {{ f.label }}
    </v-btn>
  </div>
</template>
