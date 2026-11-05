import { ref } from 'vue';
import type { AvatarCrop } from '@/modules/Core/User/Dto/AvatarCrop';
import type { AvatarSource } from '@/modules/Core/User/Dto/AvatarSource';
import type { FileRecord } from '@/modules/Core/File/Dto/FileRecord';
import type { User } from '@/modules/Core/User/Dto/User';
import type { AvatarEditorStep } from '@/modules/Core/User/Enum/AvatarEditorStep';
import { getUserApi } from '@/modules/Core/User/init';
import { getFileApi } from '@/modules/Core/File/init';
import { useAvatarUpload } from '@/modules/Core/User/Composables/useAvatarUpload';
import { avatarErrorService } from '@/modules/Core/User/Service/Instance/avatarErrorService';
import { AVATAR_SOURCES_PAGE_SIZE } from '@/modules/Core/User/Constant/Avatar/AVATAR_SOURCES_PAGE_SIZE';

/** Состояние диалога аватара: источник, «Мои изображения» и кроп. */
export function useAvatarEditor() {
  const open = ref(false);
  const step = ref<AvatarEditorStep>('pick');
  const stateLoading = ref(false);
  const source = ref<AvatarSource | null>(null);
  const initialCrop = ref<AvatarCrop | null>(null);
  const sources = ref<AvatarSource[]>([]);
  const sourcesTotal = ref(0);
  const sourcesLoading = ref(false);
  const { uploading, error, uploadFile, applyCrop, clear } = useAvatarUpload();

  let selectionVersion = 0;

  async function loadSources(reset: boolean): Promise<void> {
    if (sourcesLoading.value) return;

    const version = selectionVersion;
    sourcesLoading.value = true;
    try {
      const offset = reset ? 0 : sources.value.length;
      const page = await getFileApi().findOwnImages(AVATAR_SOURCES_PAGE_SIZE, offset);
      if (version !== selectionVersion) return;

      const pageSources = page.items.map((record) => toSource(record));
      sources.value = reset ? pageSources : mergeSources(sources.value, pageSources);
      sourcesTotal.value = page.total;
    } catch (caught) {
      if (version === selectionVersion) {
        error.value = avatarErrorService.describe(caught, 'Не удалось загрузить ваши изображения');
      }
    } finally {
      sourcesLoading.value = false;
    }
  }

  async function start(): Promise<void> {
    open.value = true;
    step.value = 'pick';
    stateLoading.value = true;
    source.value = null;
    initialCrop.value = null;
    sources.value = [];
    sourcesTotal.value = 0;
    error.value = null;
    const version = selectionVersion;
    try {
      const state = await getUserApi().getAvatar();
      if (!open.value || version !== selectionVersion) return;

      if (state.source) {
        source.value = state.source;
        initialCrop.value = state.crop;
        step.value = 'crop';

        return;
      }

      await loadSources(true);
    } catch (caught) {
      if (version === selectionVersion) {
        error.value = avatarErrorService.describe(caught, 'Не удалось загрузить аватар');
      }
    } finally {
      if (version === selectionVersion) stateLoading.value = false;
    }
  }

  async function startUpload(file: File): Promise<void> {
    selectionVersion += 1;
    stateLoading.value = false;
    const uploaded = await uploadFile(file);
    if (!uploaded) return;

    source.value = uploaded;
    initialCrop.value = null;
    step.value = 'crop';
    if (!sources.value.some((item) => item.publicId === uploaded.publicId)) {
      sources.value = [uploaded, ...sources.value];
      sourcesTotal.value += 1;
    }
  }

  function pick(selected: AvatarSource): void {
    selectionVersion += 1;
    stateLoading.value = false;
    source.value = selected;
    initialCrop.value = null;
    step.value = 'crop';
  }

  function back(): void {
    step.value = 'pick';
    source.value = null;
    initialCrop.value = null;
    void loadSources(true);
  }

  function loadMore(): void {
    if (sourcesLoading.value || sources.value.length >= sourcesTotal.value) return;
    void loadSources(false);
  }

  async function apply(crop: AvatarCrop): Promise<User | null> {
    if (!source.value) return null;
    const user = await applyCrop(source.value.publicId, crop);
    if (user) open.value = false;

    return user;
  }

  async function clearAvatar(): Promise<User | null> {
    const user = await clear();
    if (user) open.value = false;

    return user;
  }

  return {
    open,
    step,
    stateLoading,
    source,
    initialCrop,
    sources,
    sourcesTotal,
    sourcesLoading,
    uploading,
    error,
    start,
    startUpload,
    pick,
    back,
    loadMore,
    apply,
    clearAvatar,
  };
}

function toSource(record: FileRecord): AvatarSource {
  return {
    publicId: record.publicId,
    originalName: record.originalName,
    width: record.width,
    height: record.height,
  };
}

function mergeSources(current: AvatarSource[], next: AvatarSource[]): AvatarSource[] {
  const known = new Set(current.map((item) => item.publicId));
  const merged = [...current];

  for (const item of next) {
    if (!known.has(item.publicId)) merged.push(item);
  }

  return merged;
}
