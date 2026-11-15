<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { AssetItem } from '@/modules/Library/Asset/Dto/AssetItem';
import type { AssetScope } from '@/modules/Library/Asset/Enum/AssetScope';
import { AssetApiError } from '@/modules/Library/Asset/Dto/AssetApiError';
import { getAssetApi } from '@/modules/Library/Asset/init';
import { useCurrentUser } from '@/modules/Core/User/init';

/**
 * Сохранение декора как объекта библиотеки.
 *
 * Объект — это отдельная запись ассета на том же файле: у неё своё имя и
 * `meta` с физикой. Поэтому один спрайт может дать «Дуб», «Сосну» и «Куст»,
 * а правка объекта не переписывает уже сохранённые карты.
 */
const props = defineProps<{
  modelValue: boolean;
  /** Файл, на котором создаётся объект; без файла сохранять нечего. */
  filePublicId: string | null;
  collectionModule: string;
  collectionCode: string;
  collectionTitle: string;
  /** Готовый `meta` шаблона. */
  meta: Record<string, unknown> | null;
  suggestedName: string;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  saved: [item: AssetItem];
}>();

/** Куда сохранять: общая часть доступна только с обходом прав. */
const SHARE_OPTIONS: { title: string; value: AssetScope }[] = [
  { title: 'В мои объекты', value: 'personal' },
  { title: 'В общие объекты', value: 'shared' },
];

const { currentUser } = useCurrentUser();
const name = ref('');
const scope = ref<AssetScope>('personal');
const saving = ref(false);
const error = ref<string | null>(null);
const canShare = computed(() => currentUser.value?.bypass === true);
const scopeOptions = computed(() => (canShare.value ? SHARE_OPTIONS : SHARE_OPTIONS.slice(0, 1)));
const canSubmit = computed(() => name.value.trim() !== '' && props.filePublicId !== null && props.meta !== null);

watch(
  () => props.modelValue,
  (isOpen) => {
    if (!isOpen) return;
    name.value = props.suggestedName;
    scope.value = 'personal';
    error.value = null;
  },
  { immediate: true },
);

async function submit(): Promise<void> {
  if (!canSubmit.value || props.filePublicId === null || props.meta === null) {
    return;
  }

  saving.value = true;
  error.value = null;
  try {
    const item = await getAssetApi().create({
      collectionModule: props.collectionModule,
      collectionCode: props.collectionCode,
      filePublicId: props.filePublicId,
      scope: scope.value,
      name: name.value.trim(),
      meta: props.meta,
    });
    emit('saved', item);
    emit('update:modelValue', false);
  } catch (caught) {
    error.value = caught instanceof AssetApiError ? caught.message : 'Не удалось сохранить объект';
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <v-dialog :model-value="props.modelValue" max-width="520" @update:model-value="emit('update:modelValue', $event)">
    <v-card>
      <v-card-title>Сохранить как объект</v-card-title>
      <v-card-text class="d-flex flex-column ga-3">
        <span class="text-caption text-medium-emphasis">
          Основание, силуэт и скорость сохранятся в библиотеку как готовый объект. Спрайт остаётся тем же файлом.
        </span>
        <v-text-field
          v-model="name"
          label="Название объекта"
          density="compact"
          variant="outlined"
          autofocus
          @keydown.enter.prevent="submit"
        />
        <v-select
          v-model="scope"
          :items="scopeOptions"
          label="Куда сохранить"
          density="compact"
          variant="outlined"
          hide-details
        />
        <v-alert v-if="error" type="error" density="compact">{{ error }}</v-alert>
        <span v-if="props.filePublicId === null" class="text-caption text-error">
          У декора нет ассета — сохранять нечего. Сначала выберите спрайт.
        </span>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="emit('update:modelValue', false)">Отмена</v-btn>
        <v-btn color="primary" :loading="saving" :disabled="!canSubmit" @click="submit">Сохранить</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
