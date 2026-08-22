<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import type { ITokenOption } from '@/modules/Messages/Chat/Interface/ITokenOption';
import type { ITokenSource } from '@/modules/Messages/Chat/Interface/ITokenSource';
import type { GameTime } from '@/modules/Roleplay/Game/Dto/GameTime';
import type { ChronicleEntry } from '@/modules/Roleplay/Game/Dto/ChronicleEntry';
import type { CreateChronicleEntryData } from '@/modules/Roleplay/Game/Dto/CreateChronicleEntryData';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import InlineTokenPicker from '@/modules/Messages/Chat/Component/InlineTokenPicker.vue';

const props = defineProps<{
  /** null — создание записи; иначе — правка. */
  initial: ChronicleEntry | null;
  characterOptions: { id: number; name: string }[];
  npcOptions: { id: number; name: string }[];
}>();

const open = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{
  save: [data: CreateChronicleEntryData];
}>();

const title = ref('');
const content = ref('');
const contentRef = ref<{ $el: HTMLElement } | null>(null);
// Сдвиг от точки отсчёта: поля единиц — пустые трактуются как 0 (ClampedNumberField клампит
// очистку в min=0); лимиты — «порог» следующей единицы (месяцы ≤ 9, минуты ≤ 59); хранится/
// показывается нормализованно (мок нормализует при сохранении).
const offset = reactive<GameTime>({
  years: 0,
  months: 0,
  decades: 0,
  days: 0,
  hours: 0,
  minutes: 0,
});

const canSave = computed(() => title.value.trim().length > 0 && content.value.trim().length > 0);

// Источники «Вставить ссылку» (как в чате): approved-персонажи и активные НПС игры
// вставляются в содержимое инлайн-токенами `[[character:id]]` / `[[npc:id]]`.
const chronicleSources = computed<ITokenSource[]>(() => [
  {
    type: 'character',
    label: 'Персонаж',
    icon: 'mdi-account',
    search: (query) => searchOptions(props.characterOptions, query),
  },
  {
    type: 'npc',
    label: 'НПС',
    icon: 'mdi-account-question',
    search: (query) => searchOptions(props.npcOptions, query),
  },
]);

async function searchOptions(options: { id: number; name: string }[], query: string): Promise<ITokenOption[]> {
  const q = query.trim().toLowerCase();
  const matched = q ? options.filter((option) => option.name.toLowerCase().includes(q)) : options.slice(0, 10);

  return matched.map((option) => ({ value: String(option.id), label: option.name }));
}

function reset(): void {
  const initial = props.initial;
  title.value = initial?.title ?? '';
  content.value = initial?.content ?? '';
  const time = initial?.offset;
  offset.years = time?.years ?? 0;
  offset.months = time?.months ?? 0;
  offset.decades = time?.decades ?? 0;
  offset.days = time?.days ?? 0;
  offset.hours = time?.hours ?? 0;
  offset.minutes = time?.minutes ?? 0;
}

function save(): void {
  emit('save', {
    title: title.value.trim(),
    content: content.value.trim(),
    offset: { ...offset },
  });
}

watch(
  () => props.initial,
  () => {
    if (open.value) reset();
  },
);

watch(open, (value) => {
  if (value) reset();
});
</script>

<template>
  <v-dialog v-model="open" max-width="620">
    <v-card>
      <v-card-title class="text-subtitle-1">{{ initial ? 'Правка записи' : 'Новая запись летописи' }}</v-card-title>
      <v-card-text class="d-flex flex-column ga-3">
        <v-text-field v-model="title" label="Заголовок события" density="compact" hide-details />

        <div class="d-flex flex-column ga-1">
          <span class="text-caption text-medium-emphasis">Описание события</span>
          <div class="inline-editor">
            <v-textarea
              ref="contentRef"
              v-model="content"
              rows="4"
              density="compact"
              hide-details
              placeholder="Вставьте ссылки на персонажей и НПС кнопкой справа, например: Гаррик [[character:3]] первым заметил ловушку."
            />
            <div class="inline-editor__picker">
              <InlineTokenPicker v-model="content" :sources="chronicleSources" :target-ref="contentRef" />
            </div>
          </div>
          <span class="text-caption text-medium-emphasis">Ссылки на персонажей и НПС — через «Вставить ссылку»</span>
        </div>

        <div class="d-flex flex-column ga-1">
          <span class="text-caption text-medium-emphasis">Сдвиг от начала приключения</span>
          <div class="offset-grid">
            <ClampedNumberField v-model="offset.years" label="Годы" :min="0" min-width="0" hide-details />
            <ClampedNumberField v-model="offset.months" label="Месяцы" :min="0" :max="9" min-width="0" hide-details />
            <ClampedNumberField v-model="offset.decades" label="Декады" :min="0" :max="2" min-width="0" hide-details />
            <ClampedNumberField v-model="offset.days" label="Дни" :min="0" :max="9" min-width="0" hide-details />
            <ClampedNumberField v-model="offset.hours" label="Часы" :min="0" :max="29" min-width="0" hide-details />
            <ClampedNumberField v-model="offset.minutes" label="Минуты" :min="0" :max="59" min-width="0" hide-details />
          </div>
        </div>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn color="primary" variant="tonal" :disabled="!canSave" @click="save">
          {{ initial ? 'Сохранить' : 'Добавить' }}
        </v-btn>
        <v-btn variant="text" @click="open = false">Отмена</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.inline-editor {
  position: relative;
}
.inline-editor__picker {
  position: absolute;
  bottom: 4px;
  right: 4px;
}
.offset-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}
</style>
