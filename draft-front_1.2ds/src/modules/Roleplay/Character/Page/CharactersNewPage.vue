<script setup lang="ts">
import { useSpaceCatalog, useSpaceRevision } from '@/modules/Roleplay/Space/init';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';

const router = useRouter();
const spaceCatalog = useSpaceCatalog();
const spaceRevision = useSpaceRevision();
const draftStore = useCharacterDraftStore();
const { signal } = useAbortable();

const selectedSpaceId = ref<number | null>(null);
const revision = ref<number | null>(null);
const osTotal = ref<number>(12);
const orTotal = ref<number>(25);
const moneyBudget = ref<number>(10000);
const revisions = ref<number[]>([]);

const spaces = spaceCatalog.spaces;
const spacesError = spaceCatalog.error;

const selectedSpace = computed(() => spaces.value.find((space) => space.id === selectedSpaceId.value) ?? null);

const canStart = computed(() => selectedSpace.value !== null && revision.value !== null);

const revisionItems = computed(() => revisions.value.map((value) => ({ title: `Ревизия ${value}`, value })));

async function onSpaceSelect(): Promise<void> {
  const space = selectedSpace.value;
  if (!space) {
    revisions.value = [];
    revision.value = null;

    return;
  }
  revision.value = space.revision;
  try {
    const meta = await spaceRevision.fetchRevisionsMeta(space.id, signal.value);
    revisions.value = meta.map((entry) => entry.revision).sort((a, b) => b - a);
  } catch {
    revisions.value = [space.revision];
  }
}

function start(): void {
  const space = selectedSpace.value;
  if (!space || revision.value === null) return;

  const build: CharacterBuild = {
    name: '',
    shortDescription: null,
    fullDescription: null,
    spaceId: space.id,
    spaceCode: space.code,
    rulesRevision: revision.value,
    raceRuleCode: null,
    characteristicPurchases: [],
    abilities: [],
    resources: [],
    inventory: [],
    states: [],
    money: 0,
    ageYears: null,
    olTotal: 0,
  };
  const config: CharacterCreationConfig = {
    osTotal: osTotal.value,
    orTotal: orTotal.value,
    moneyBudget: moneyBudget.value,
  };
  draftStore.initDraft(null, build, config);
  void router.push('/characters/new/editor');
}

onMounted(() => {
  void spaceCatalog.fetchSpaces();
});
</script>

<template>
  <v-container>
    <h1 class="text-h5 font-weight-bold mb-1">Новый персонаж</h1>
    <p class="text-body-1 text-medium-emphasis mb-6">Свободное создание: выберите правила и лимиты.</p>

    <div v-if="spacesError" class="text-medium-emphasis mb-4">{{ spacesError }}</div>

    <v-card class="mb-6" max-width="640">
      <v-card-text>
        <v-select
          v-model="selectedSpaceId"
          label="Пространство правил"
          :items="spaces"
          item-title="name"
          item-value="id"
          hint="Правила берутся из выбранного пространства"
          persistent-hint
          @update:model-value="onSpaceSelect"
        />
        <v-autocomplete
          v-model="revision"
          label="Ревизия правил"
          :items="revisionItems"
          item-title="title"
          item-value="value"
          hint="Доступные ревизии пространства"
          persistent-hint
        />

        <v-divider class="my-4" />

        <div class="text-subtitle-2 mb-2">Лимиты создания</div>
        <div class="d-flex ga-4">
          <ClampedNumberField v-model="osTotal" label="ОС" :min="0" class="flex-grow-1" />
          <ClampedNumberField v-model="orTotal" label="ОР" :min="5" class="flex-grow-1" />
          <ClampedNumberField v-model="moneyBudget" label="Деньги (гм)" :min="0" class="flex-grow-1" />
        </div>
      </v-card-text>
      <v-card-actions class="pa-4">
        <v-btn color="primary" :disabled="!canStart" @click="start"> В редактор </v-btn>
        <v-btn variant="text" @click="router.push('/characters')"> Отмена </v-btn>
      </v-card-actions>
    </v-card>

    <v-alert type="info" variant="tonal" density="compact" max-width="640">
      Создание через игру — во вкладке «Персонажи» карточки игры («Создать в игре»).
    </v-alert>
  </v-container>
</template>
