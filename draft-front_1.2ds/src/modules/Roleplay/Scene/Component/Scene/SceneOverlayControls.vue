<script setup lang="ts">
defineProps<{
  costs: boolean;
  visibility: boolean;
}>();

defineEmits<{
  'update:costs': [value: boolean];
  'update:visibility': [value: boolean];
}>();
</script>

<template>
  <div class="scene-overlay-controls" @pointerdown.stop @wheel.stop>
    <v-btn
      icon="mdi-routes"
      size="small"
      variant="flat"
      :color="costs ? 'primary' : 'surface-variant'"
      :aria-pressed="costs"
      title="Маршруты: скорость клеток"
      @click="$emit('update:costs', !costs)"
    />
    <v-btn
      icon="mdi-eye-outline"
      size="small"
      variant="flat"
      :color="visibility ? 'primary' : 'surface-variant'"
      :aria-pressed="visibility"
      title="Видимость: скрытые клетки"
      @click="$emit('update:visibility', !visibility)"
    />
  </div>
</template>

<style scoped>
.scene-overlay-controls {
  position: absolute;
  top: 12px;
  left: 12px;
  z-index: 3;
  display: flex;
  gap: 6px;
  padding: 4px;
  border: 1px solid rgba(148, 163, 184, 0.35);
  border-radius: 10px;
  background: rgba(15, 23, 42, 0.82);
  backdrop-filter: blur(4px);
}
</style>
