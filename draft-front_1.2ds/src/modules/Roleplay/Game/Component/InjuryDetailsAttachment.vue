<script setup lang="ts">
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { InjuryDetailsPayload } from '@/modules/Roleplay/Game/Dto/InjuryDetailsPayload';
import { injuryDifficultyDetailRows } from '@/modules/Roleplay/Game/Utils/injuryCheckMessage';
import { computed } from 'vue';

function healUnitLabel(unit: string): string {
  if (unit === 'days') return 'дн.';
  if (unit === 'decades') return 'дек.';
  if (unit === 'months') return 'мес.';

  return 'лет';
}

const props = defineProps<{
  attachment: ChatAttachment<InjuryDetailsPayload>;
  index?: number;
  context?: { openEntity?: (key: string) => void };
}>();

const details = computed(() => props.attachment.payload);
const difficultyRows = computed(() =>
  details.value.injury.breakdown ? injuryDifficultyDetailRows(details.value.injury.breakdown) : [],
);
</script>

<template>
  <v-menu location="bottom start" :close-on-content-click="false">
    <template #activator="{ props: menuProps }">
      <v-btn
        v-bind="menuProps"
        icon
        variant="text"
        size="x-small"
        title="Подробности увечья"
        aria-label="Подробности увечья"
        @click.stop
      >
        <v-icon>mdi-information-outline</v-icon>
      </v-btn>
    </template>
    <v-card class="rounded border" elevation="3" style="width: max-content; min-width: 280px; max-width: 420px">
      <v-card-title class="text-body-1">Увечье</v-card-title>
      <v-card-text class="pt-0">
        <div v-if="details.damageTypeName" class="d-flex align-center justify-space-between py-1 text-body-2 ga-3">
          <span class="text-medium-emphasis">Тип урона</span>
          <span class="font-weight-medium">{{ details.damageTypeName }}</span>
        </div>
        <div
          v-for="row in difficultyRows"
          :key="row.label"
          class="d-flex align-center justify-space-between py-1 text-body-2 ga-3"
        >
          <span class="text-medium-emphasis">{{ row.label }}</span>
          <span class="font-weight-medium">{{ row.value }}</span>
        </div>
        <div
          v-if="!difficultyRows.length && details.injury.difficulty != null"
          class="d-flex align-center justify-space-between py-1 text-body-2"
        >
          <span class="text-medium-emphasis">Сложность</span>
          <span class="font-weight-medium">{{ details.injury.difficulty }}</span>
        </div>
        <div v-if="details.injury.rating != null" class="d-flex align-center justify-space-between py-1 text-body-2">
          <span class="text-medium-emphasis">РУ</span>
          <span class="font-weight-medium">{{ details.injury.rating }}</span>
        </div>
        <div class="d-flex align-center justify-space-between py-1 text-body-2">
          <span class="text-medium-emphasis">Сила</span>
          <span class="font-weight-medium">{{ details.injury.strength }}</span>
        </div>
        <div class="d-flex align-center justify-space-between py-1 text-body-2">
          <span class="text-medium-emphasis">Срок</span>
          <span class="font-weight-medium">{{ details.injury.permanent ? 'постоянное' : 'временное' }}</span>
        </div>
        <div v-if="details.injury.heal" class="d-flex align-center justify-space-between py-1 text-body-2 ga-3">
          <span class="text-medium-emphasis">−1 силы за</span>
          <span class="font-weight-medium">
            {{ details.injury.heal.diceCount }}д{{ details.injury.heal.dieFaces }}
            {{ healUnitLabel(details.injury.heal.unit) }} → {{ details.injury.heal.total }}
          </span>
        </div>
        <div class="d-flex align-center justify-space-between py-1 text-body-2">
          <span class="text-medium-emphasis">Обезображивающее</span>
          <span class="font-weight-medium">{{ details.injury.disfiguring ? 'да' : 'нет' }}</span>
        </div>
        <div class="d-flex align-center justify-space-between py-1 text-body-2">
          <span class="text-medium-emphasis">Смертельное</span>
          <span class="font-weight-medium">{{ details.injury.lethal ? 'да' : 'нет' }}</span>
        </div>
      </v-card-text>
    </v-card>
  </v-menu>
</template>
