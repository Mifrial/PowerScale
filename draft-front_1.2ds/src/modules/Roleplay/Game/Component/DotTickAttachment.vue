<script setup lang="ts">
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { DotTickCalcPayload } from '@/modules/Roleplay/Game/Dto/DotTickCalcPayload';
import { computed } from 'vue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';

const props = defineProps<{
  attachment: ChatAttachment<DotTickCalcPayload>;
  index?: number;
  context?: { openEntity?: (key: string) => void };
}>();

const calc = computed(() => props.attachment.payload);
const damageLabel = computed(() => new DimensionalNumber(calc.value.damage).toString());
</script>

<template>
  <v-menu location="bottom start" :close-on-content-click="false">
    <template #activator="{ props: menuProps }">
      <v-btn
        v-bind="menuProps"
        icon
        variant="text"
        size="x-small"
        title="Подробности урона со временем"
        aria-label="Подробности урона со временем"
        @click.stop
      >
        <v-icon>mdi-information-outline</v-icon>
      </v-btn>
    </template>
    <v-card class="rounded border" elevation="3" style="width: max-content; min-width: 280px; max-width: 420px">
      <v-card-title class="text-body-1">{{ calc.label }}</v-card-title>
      <v-card-text class="pt-0">
        <div class="d-flex align-center justify-space-between py-1 text-body-2 ga-3">
          <span class="text-medium-emphasis">Сила</span>
          <span class="font-weight-medium">{{ damageLabel }} {{ calc.damageTypeName }}</span>
        </div>
        <div class="d-flex align-center justify-space-between py-1 text-body-2">
          <span class="text-medium-emphasis">Сопротивление</span>
          <span class="font-weight-medium">
            {{ calc.resistance }}<template v-if="calc.defenseIgnored"> · защита не помогает</template>
          </span>
        </div>
        <div v-if="(calc.layers ?? []).length" class="pt-1">
          <div v-for="(layer, index) in calc.layers ?? []" :key="index" class="text-caption py-1">
            <span class="text-medium-emphasis">{{ layer.itemName }} · </span>
            <span>{{ layer.kind === 'defense' ? 'защита' : 'сопротивление' }} {{ layer.value }}</span>
            <span class="text-medium-emphasis"> · надёжность {{ layer.durability }}</span>
            <span v-if="layer.ignored && layer.reason === 'sr'"> — игнор</span>
            <span v-else-if="layer.ignored && layer.reason === 'defense_flag'"> — игнор (тип не считает защиту)</span>
            <span v-else> — учтено</span>
          </div>
        </div>
        <v-divider class="my-2" />
        <div class="d-flex align-center justify-space-between py-1 text-body-2">
          <span class="text-medium-emphasis">Повреждения</span>
          <span class="font-weight-medium">{{ calc.hpDamage }}</span>
        </div>
        <div class="d-flex align-center justify-space-between py-1 text-body-2">
          <span class="text-medium-emphasis">Истощение</span>
          <span class="font-weight-medium">{{ calc.exhaustion }}</span>
        </div>
      </v-card-text>
    </v-card>
  </v-menu>
</template>
