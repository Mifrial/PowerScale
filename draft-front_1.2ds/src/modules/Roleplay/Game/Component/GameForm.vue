<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useSpaceStore } from '@/modules/Roleplay/Space/Store/spaces';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
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
const osPointsLimit = ref<number | null>(null);
const olPointsLimit = ref<number | null>(null);
const orPointsLimit = ref<number | null>(null);
const moneyLimit = ref<number | null>(null);
const tags = ref<string[]>([]);
const forbiddenTags = ref<string[]>([]);
const revisions = ref<number[]>([]);

const spaces = computed(() => spaceStore.spaces);
const selectedSpace = computed(() => spaces.value.find((space) => space.id === selectedSpaceId.value) ?? null);

const revisionItems = computed(() => revisions.value.map((value) => ({ title: `Ревизия ${value}`, value })));

const canSubmit = computed(
  () => name.value.trim().length > 0 && selectedSpace.value !== null && revision.value !== null,
);

/** Список ревизий, затем выбор: preferred если есть в списке, иначе последняя. */
async function loadRevisions(spaceId: number, preferred: number | null): Promise<void> {
  try {
    const meta = await spaceRevisionStore.fetchRevisionsMeta(spaceId, signal.value);
    revisions.value = meta.map((entry) => entry.revision).sort((a, b) => b - a);
  } catch {
    const space = spaces.value.find((entry) => entry.id === spaceId);
    revisions.value = space ? [space.revision] : [];
  }
  if (preferred != null && revisions.value.includes(preferred)) {
    revision.value = preferred;
  } else {
    revision.value = revisions.value[0] ?? null;
  }
}

async function onSpaceSelect(): Promise<void> {
  const space = selectedSpace.value;
  if (!space) {
    revisions.value = [];
    revision.value = null;

    return;
  }
  await loadRevisions(space.id, null);
}

function setLimit(field: 'os' | 'ol' | 'or' | 'money', value: number): void {
  const next = value as number | null;
  if (field === 'os') osPointsLimit.value = next;
  else if (field === 'ol') olPointsLimit.value = next;
  else if (field === 'or') orPointsLimit.value = next;
  else moneyLimit.value = next;
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
    osPointsLimit: osPointsLimit.value,
    olPointsLimit: olPointsLimit.value,
    orPointsLimit: orPointsLimit.value,
    moneyLimit: moneyLimit.value,
    tags: tags.value,
    forbiddenTags: forbiddenTags.value,
  });
}

onMounted(async () => {
  await spaceStore.fetchSpaces();
  const initial = props.initial;
  if (!initial) return;
  name.value = initial.name;
  shortDescription.value = initial.shortDescription ?? '';
  description.value = initial.description ?? '';
  selectedSpaceId.value = initial.spaceId;
  status.value = initial.status;
  visibility.value = initial.visibility;
  joinPolicy.value = initial.joinPolicy;
  osPointsLimit.value = initial.osPointsLimit;
  olPointsLimit.value = initial.olPointsLimit;
  orPointsLimit.value = initial.orPointsLimit;
  moneyLimit.value = initial.moneyLimit;
  tags.value = [...initial.tags];
  forbiddenTags.value = [...initial.forbiddenTags];
  if (initial.spaceId != null) {
    await loadRevisions(initial.spaceId, initial.rulesRevision);
  }
});
</script>

<template>
  <v-card class="mb-6" max-width="640">
    <v-card-text>
      <v-alert v-if="submitError" type="error" variant="tonal" density="compact" class="mb-4">
        {{ submitError }}
      </v-alert>

      <v-text-field v-model="name" label="Название" hide-details="auto" class="mb-3" />
      <v-text-field
        v-model="shortDescription"
        label="Краткое описание (для карточки в списке)"
        hide-details="auto"
        class="mb-3"
      />
      <v-textarea v-model="description" label="Полное описание" rows="3" hide-details="auto" class="mb-3" />

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
        class="mb-3"
        @update:model-value="onSpaceSelect"
      />
      <v-select
        :key="selectedSpaceId ?? 'none'"
        v-model="revision"
        label="Ревизия правил"
        :items="revisionItems"
        item-title="title"
        item-value="value"
        :disabled="spaceReadonly"
        hint="При смене ревизии/пространства персонажи игры потребуют перевода на новую версию правил"
        persistent-hint
        class="mb-3"
      />

      <v-divider class="my-4" />

      <div class="text-subtitle-2 mb-2">Статус и доступ</div>
      <v-select
        v-model="status"
        label="Статус"
        :items="GAME_STATUS_OPTIONS"
        item-title="label"
        item-value="value"
        hide-details="auto"
        class="mb-3"
      />
      <v-select
        v-model="visibility"
        label="Видимость"
        :items="GAME_VISIBILITY_OPTIONS"
        item-title="label"
        item-value="value"
        hide-details="auto"
        class="mb-3"
      />
      <v-select
        v-model="joinPolicy"
        label="Вступление"
        :items="GAME_JOIN_POLICY_OPTIONS"
        item-title="label"
        item-value="value"
        hide-details="auto"
        class="mb-3"
      />

      <v-divider class="my-4" />

      <div class="text-subtitle-2 mb-2">Лимиты создания персонажей</div>
      <div class="text-caption text-medium-emphasis mb-3">Пустое поле — лимит не задан.</div>
      <div class="d-flex ga-4 mb-3">
        <ClampedNumberField
          :model-value="osPointsLimit as number"
          label="ОС"
          :min="0"
          nullable
          hide-details="auto"
          class="flex-grow-1"
          @update:model-value="setLimit('os', $event)"
        />
        <ClampedNumberField
          :model-value="olPointsLimit as number"
          label="ОЛ"
          :min="0"
          nullable
          hide-details="auto"
          class="flex-grow-1"
          @update:model-value="setLimit('ol', $event)"
        />
      </div>
      <div class="d-flex ga-4 mb-3">
        <ClampedNumberField
          :model-value="orPointsLimit as number"
          label="ОР"
          :min="0"
          nullable
          hide-details="auto"
          class="flex-grow-1"
          @update:model-value="setLimit('or', $event)"
        />
        <ClampedNumberField
          :model-value="moneyLimit as number"
          label="Деньги (гм)"
          :min="0"
          nullable
          hide-details="auto"
          class="flex-grow-1"
          @update:model-value="setLimit('money', $event)"
        />
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
