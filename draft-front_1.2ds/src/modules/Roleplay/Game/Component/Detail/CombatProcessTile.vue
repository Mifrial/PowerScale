<script setup lang="ts">
import { ref } from 'vue';
import type { CombatProcessRow } from '@/modules/Roleplay/Game/Dto/CombatProcessRow';

defineProps<{
  row: CombatProcessRow;
  canEdit: boolean;
}>();

const emit = defineEmits<{
  abort: [];
  'charge-cast': [];
}>();

const menuOpen = ref(false);

function submitAbort(): void {
  emit('abort');
  menuOpen.value = false;
}

function submitChargeCast(): void {
  emit('charge-cast');
  menuOpen.value = false;
}
</script>

<template>
  <v-menu v-model="menuOpen" location="right top" :close-on-content-click="false" :z-index="2200">
    <template #activator="{ props: menuProps }">
      <div v-bind="menuProps" class="combat-card-process" role="button" tabindex="0">
        <v-icon :icon="row.iconCode" size="16" class="flex-shrink-0" />
        <span class="combat-card-process__label text-truncate">{{ row.leftLabel }}</span>
        <span class="combat-card-process__value">{{ row.valueLabel }}</span>
      </div>
    </template>
    <v-card class="rounded border" elevation="8" style="width: max-content; min-width: 280px; max-width: 420px">
      <v-card-title class="text-body-1">{{ row.name }}</v-card-title>
      <v-card-text class="pt-0">
        <div
          v-for="detail in row.details"
          :key="detail.label"
          class="d-flex align-center justify-space-between py-1 text-body-2 ga-3"
        >
          <span class="text-medium-emphasis">{{ detail.label }}</span>
          <span class="font-weight-medium">{{ detail.value }}</span>
        </div>
        <v-btn
          v-if="canEdit && row.canChargeCast"
          class="mt-3"
          color="primary"
          variant="tonal"
          size="small"
          block
          @click="submitChargeCast"
        >
          {{ row.chargeCastLabel }}
        </v-btn>
        <v-btn
          v-if="canEdit && row.canAbort"
          class="mt-3"
          color="error"
          variant="tonal"
          size="small"
          block
          @click="submitAbort"
        >
          {{ row.abortLabel }}
        </v-btn>
        <div v-else-if="canEdit && !row.canAbort" class="mt-3 text-medium-emphasis text-body-2">
          Сейчас оборвать нельзя
        </div>
      </v-card-text>
    </v-card>
  </v-menu>
</template>

<style scoped>
.combat-card-process {
  display: flex;
  align-items: center;
  gap: 6px;
  min-height: 32px;
  padding: 3px 8px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.14);
  border-radius: 8px;
  cursor: pointer;
  background: rgb(var(--v-theme-surface));
  transition:
    border-color 0.15s ease,
    background-color 0.15s ease;
}
.combat-card-process:hover {
  border-color: rgba(var(--v-theme-primary), 0.5);
  background-color: rgba(var(--v-theme-primary), 0.05);
}
.combat-card-process__label {
  flex: 1;
  font-size: 13px;
  min-width: 0;
}
.combat-card-process__value {
  font-size: 13px;
  font-weight: 500;
  flex-shrink: 0;
}
</style>
