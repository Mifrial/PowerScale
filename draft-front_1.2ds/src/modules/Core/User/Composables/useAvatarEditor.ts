import { ref } from 'vue';
import type { AvatarCrop } from '@/modules/Core/User/Dto/AvatarCrop';
import type { AvatarSource } from '@/modules/Core/User/Dto/AvatarSource';
import type { FileRecord } from '@/modules/Core/File/Dto/FileRecord';
import type { User } from '@/modules/Core/User/Dto/User';
import { getUserApi } from '@/modules/Core/User/init';
import { getFileApi } from '@/modules/Core/File/init';
import { useAvatarUpload } from '@/modules/Core/User/Composables/useAvatarUpload';

export type AvatarEditorStep = 'pick' | 'crop';

/** Состояние диалога аватара: текущий источник, список своих картинок и кроп. */
export function useAvatarEditor() {
  const open = ref(false);
  const step = ref<AvatarEditorStep>('pick');
  const source = ref<AvatarSource | null>(null);
  const initialCrop = ref<AvatarCrop | null>(null);
  const sources = ref<FileRecord[]>([]);
  const sourcesLoading = ref(false);
  const { uploading, error, uploadFile, applyCrop, clear } = useAvatarUpload();

  async function loadSources(): Promise<void> {
    sourcesLoading.value = true;
    try {
      sources.value = (await getFileApi().findOwnImages(100, 0)).items;
    } catch (caught) {
      error.value = caught instanceof Error ? caught.message : 'Не удалось загрузить ваши изображения';
    } finally {
      sourcesLoading.value = false;
    }
  }

  async function start(): Promise<void> {
    open.value = true;
    step.value = 'pick';
    source.value = null;
    initialCrop.value = null;
    error.value = null;
    try {
      const state = await getUserApi().getAvatar();
      if (state.source) {
        source.value = state.source;
        initialCrop.value = state.crop;
        step.value = 'crop';

        return;
      }

      await loadSources();
    } catch (caught) {
      error.value = caught instanceof Error ? caught.message : 'Не удалось загрузить аватар';
    }
  }

  async function startUpload(file: File): Promise<void> {
    const uploaded = await uploadFile(file);
    if (!uploaded) return;

    source.value = uploaded;
    initialCrop.value = null;
    step.value = 'crop';
  }

  function pick(record: FileRecord): void {
    source.value = {
      publicId: record.publicId,
      originalName: record.originalName,
      width: record.width,
      height: record.height,
    };
    initialCrop.value = null;
    step.value = 'crop';
  }

  function back(): void {
    step.value = 'pick';
    source.value = null;
    initialCrop.value = null;
    if (sources.value.length === 0) void loadSources();
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

  function close(): void {
    open.value = false;
  }

  return {
    open,
    step,
    source,
    initialCrop,
    sources,
    sourcesLoading,
    uploading,
    error,
    start,
    startUpload,
    pick,
    back,
    apply,
    clearAvatar,
    close,
  };
}
