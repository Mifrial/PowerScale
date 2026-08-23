<script setup lang="ts">
import { computed, ref } from 'vue';
import type { DefenseTierOverview } from '@/modules/Roleplay/Character/Dto/Overview/DefenseOverview';

const props = defineProps<{
  /** Ступени защиты по надёжности, отсортированы по возрастанию threshold. */
  tiers: DefenseTierOverview[];
}>();

// По умолчанию — самая дешёвая ступень: вся защита (враг ещё не вкладывал «РУ Атаки»).
const selected = ref(0);

const selectedTier = computed(() => props.tiers[selected.value] ?? null);

function tierLabel(tier: DefenseTierOverview): string {
  return `${tier.defense} защиты с надёжностью ${tier.threshold}+`;
}
</script>

<template>
  <v-menu v-if="tiers.length" attach location="bottom" :z-index="2200">
    <template #activator="{ props: menuProps }">
      <v-btn v-bind="menuProps" variant="text" size="small" class="text-success font-weight-bold pa-0">
        <template v-if="selectedTier">{{ tierLabel(selectedTier) }}</template>
        <v-icon size="small" class="ml-1">mdi-chevron-down</v-icon>
      </v-btn>
    </template>

    <v-card class="rounded border" elevation="3" style="min-width: 300px; max-width: 400px">
      <v-card-text class="pt-3">
        <div class="text-caption text-medium-emphasis mb-2">
          Надёжность — сколько «РУ Атаки» враг должен вложить, чтобы игнорировать слой защиты: слой с надёжностью N
          игнорируется за N «РУ Атаки». Защита пересчитывается по уцелевшим слоям.
        </div>
        <v-list density="compact">
          <v-list-item
            v-for="(tier, index) in tiers"
            :key="tier.threshold"
            :active="index === selected"
            @click="selected = index"
          >
            <template #title>
              <span class="font-weight-medium">{{ tier.defense }} защиты</span>
            </template>
            <template #subtitle>с надёжностью {{ tier.threshold }}+</template>
          </v-list-item>
        </v-list>
      </v-card-text>
    </v-card>
  </v-menu>
</template>
