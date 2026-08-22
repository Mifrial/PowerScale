<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useSpaceStore } from '@/modules/Roleplay/Space/Store/spaces';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { GAME_STATUS_OPTIONS } from '@/modules/Roleplay/Game/Constant/GameStatus/GAME_STATUS_OPTIONS';
import { GAME_VISIBILITY_OPTIONS } from '@/modules/Roleplay/Game/Constant/GameVisibility/GAME_VISIBILITY_OPTIONS';
import { GAME_JOIN_POLICY_OPTIONS } from '@/modules/Roleplay/Game/Constant/GameJoinPolicy/GAME_JOIN_POLICY_OPTIONS';
import type { CreateGameData } from '@/modules/Roleplay/Game/Dto/CreateGameData';
import type { GameStatus } from '@/modules/Roleplay/Game/Enum/GameStatus';
import type { GameVisibility } from '@/modules/Roleplay/Game/Enum/GameVisibility';
import type { GameJoinPolicy } from '@/modules/Roleplay/Game/Enum/GameJoinPolicy';

const props = withDefaults(
  defineProps<{
    initial?: CreateGameData | null;
    spaceReadonly?: boolean;
    submitLabel: string;
    submitLoading?: boolean;
    submitError?: string | null;
  }>(),
  {
    initial: null,
    spaceReadonly: false,
    submitLoading: false,
    submitError: null,
  },
);

const emit = defineEmits<{
  submit: [data: CreateGameData];
  cancel: [];
}>();

const spaceStore = useSpaceStore();
const spaceRevisionStore = useSpaceRevisionStore();
const { signal } = useAbortable();

const name = ref('');
const shortDescription = ref('');
const description = ref('');
const selectedSpaceId = ref<number | null>(null);
const revision = ref<number | null>(null);
const status = ref<GameStatus>('draft');
const visibility = ref<GameVisibility>('all');
const joinPolicy = ref<GameJoinPolicy>('anyone');
const osPointsLimit = ref('');
const olPointsLimit = ref('');
const orPointsLimit = ref('');
const moneyLimit = ref('');
const tags = ref<string[]>([]);
const forbiddenTags = ref<string[]>([]);
const revisions = ref<number[]>([]);

const spaces = computed(() => spaceStore.spaces);
const selectedSpace = computed(() => spaces.value.find((space) => space.id === selectedSpaceId.value) ?? null);

const revisionItems = computed(() => revisions.value.map((value) => ({ title: `Ревизия ${value}`, value })));

const canSubmit = computed(
  () => name.value.trim().length > 0 && selectedSpace.value !== null && revision.value !== null,
);

function limitToText(value: number | null): string {
  return value === null ? '' : String(value);
}

// Пустое поле лимита = NULL = лимит не задан (ТР §8); невалидный ввод трактуется так же.
function parseLimit(text: string): number | null {
  const value = text.trim();
  if (value === '') return null;
  const num = Number(value);

  return Number.isFinite(num) ? num : null;
}

async function onSpaceSelect(): Promise<void> {
  const space = selectedSpace.value;
  if (!space) {
    revisions.value = [];
    revision.value = null;

    return;
  }
  revision.value = space.revision;
  try {
    const meta = await spaceRevisionStore.fetchRevisionsMeta(space.id, signal.value);
    revisions.value = meta.map((entry) => entry.revision).sort((a, b) => b - a);
  } catch {
    revisions.value = [space.revision];
  }
}

function submit(): void {
  const space = selectedSpace.value;
  if (!space || revision.value === null || !canSubmit.value) return;
  emit('submit', {
    name: name.value.trim(),
    shortDescription: shortDescription.value.trim() || null,
    description: description.value.trim() || null,
    spaceId: space.id,
    spaceCode: space.code,
    rulesRevision: revision.value,
    status: status.value,
    visibility: visibility.value,
    joinPolicy: joinPolicy.value,
    osPointsLimit: parseLimit(osPointsLimit.value),
    olPointsLimit: parseLimit(olPointsLimit.value),
    orPointsLimit: parseLimit(orPointsLimit.value),
    moneyLimit: parseLimit(moneyLimit.value),
    tags: tags.value,
    forbiddenTags: forbiddenTags.value,
  });
}

onMounted(() => {
  void spaceStore.fetchSpaces();
  const initial = props.initial;
  if (!initial) return;
  name.value = initial.name;
  shortDescription.value = initial.shortDescription ?? '';
  description.value = initial.description ?? '';
  selectedSpaceId.value = initial.spaceId;
  revision.value = initial.rulesRevision;
  status.value = initial.status;
  visibility.value = initial.visibility;
  joinPolicy.value = initial.joinPolicy;
  osPointsLimit.value = limitToText(initial.osPointsLimit);
  olPointsLimit.value = limitToText(initial.olPointsLimit);
  orPointsLimit.value = limitToText(initial.orPointsLimit);
  moneyLimit.value = limitToText(initial.moneyLimit);
  tags.value = [...initial.tags];
  forbiddenTags.value = [...initial.forbiddenTags];
  void onSpaceSelect();
});
</script>

<template>
  <v-card class="mb-6" max-width="640">
    <v-card-text>
      <v-alert v-if="submitError" type="error" variant="tonal" density="compact" class="mb-4">
        {{ submitError }}
      </v-alert>

      <v-text-field v-model="name" label="Название" />
      <v-text-field v-model="shortDescription" label="Краткое описание (для карточки в списке)" />
      <v-textarea v-model="description" label="Полное описание" rows="3" />

      <v-divider class="my-4" />

      <v-select
        v-model="selectedSpaceId"
        label="Пространство правил"
        :items="spaces"
        item-title="name"
        item-value="id"
        :disabled="spaceReadonly"
        hint="Правила и лимиты берутся из выбранного пространства"
        persistent-hint
        @update:model-value="onSpaceSelect"
      />
      <v-autocomplete
        v-model="revision"
        label="Ревизия правил"
        :items="revisionItems"
        item-title="title"
        item-value="value"
        :disabled="spaceReadonly"
        hint="При смене ревизии/пространства персонажи игры потребуют перевода на новую версию правил"
        persistent-hint
      />

      <v-divider class="my-4" />

      <div class="text-subtitle-2 mb-2">Статус и доступ</div>
      <v-select v-model="status" label="Статус" :items="GAME_STATUS_OPTIONS" item-title="label" item-value="value" />
      <v-select
        v-model="visibility"
        label="Видимость"
        :items="GAME_VISIBILITY_OPTIONS"
        item-title="label"
        item-value="value"
      />
      <v-select
        v-model="joinPolicy"
        label="Вступление"
        :items="GAME_JOIN_POLICY_OPTIONS"
        item-title="label"
        item-value="value"
      />

      <v-divider class="my-4" />

      <div class="text-subtitle-2 mb-2">Лимиты создания персонажей</div>
      <div class="text-caption text-medium-emphasis mb-3">Пустое поле — лимит не задан.</div>
      <div class="d-flex ga-4">
        <v-text-field v-model="osPointsLimit" label="ОС" type="number" class="flex-grow-1" />
        <v-text-field v-model="olPointsLimit" label="ОЛ" type="number" class="flex-grow-1" />
      </div>
      <div class="d-flex ga-4">
        <v-text-field v-model="orPointsLimit" label="ОР" type="number" class="flex-grow-1" />
        <v-text-field v-model="moneyLimit" label="Деньги (гм)" type="number" class="flex-grow-1" />
      </div>

      <v-divider class="my-4" />

      <div class="text-subtitle-2 mb-2">Теги</div>
      <v-combobox v-model="tags" label="Теги игры (жанр, сеттинг)" multiple chips small-chips class="mb-2" />
      <v-combobox v-model="forbiddenTags" label="Запретные теги (ограничения)" multiple chips small-chips />
    </v-card-text>
    <v-card-actions class="pa-4">
      <v-btn color="primary" :disabled="!canSubmit || submitLoading" :loading="submitLoading" @click="submit">
        {{ submitLabel }}
      </v-btn>
      <v-btn variant="text" :disabled="submitLoading" @click="emit('cancel')"> Отмена </v-btn>
    </v-card-actions>
  </v-card>
</template>
