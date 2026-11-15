import { onScopeDispose, ref, watch } from 'vue';
import type { AssetLibraryOptions } from '@/modules/Library/Asset/Interface/AssetLibraryOptions';
import type { AssetItem } from '@/modules/Library/Asset/Dto/AssetItem';
import type { AssetQuery } from '@/modules/Library/Asset/Dto/AssetQuery';
import type { AssetScope } from '@/modules/Library/Asset/Enum/AssetScope';
import { AssetApiError } from '@/modules/Library/Asset/Dto/AssetApiError';
import { getAssetApi } from '@/modules/Library/Asset/init';
import { getFileApi } from '@/modules/Core/File/init';

const PAGE_SIZE = 24;

/**
 * Состояние диалога библиотеки: вкладки, поиск, страницы, загрузка и правки.
 */
export function useAssetLibrary(options: AssetLibraryOptions) {
  const scope = ref<AssetScope>(options.initialScope ?? 'shared');
  const items = ref<AssetItem[]>([]);
  const total = ref(0);
  const search = ref('');
  const loading = ref(false);
  const loadingMore = ref(false);
  const uploading = ref(false);
  const mutating = ref(false);
  const error = ref<string | null>(null);
  let searchTimer: ReturnType<typeof setTimeout> | null = null;
  let requestSequence = 0;

  async function reload(): Promise<void> {
    clearSearchTimer();
    const sequence = ++requestSequence;
    loading.value = true;
    error.value = null;
    try {
      const page = await getAssetApi().findPage(buildQuery(0));
      if (sequence !== requestSequence) return;
      items.value = page.items;
      total.value = page.total;
    } catch (caught) {
      if (sequence === requestSequence) {
        error.value = describeError(caught, 'Не удалось загрузить библиотеку');
      }
    } finally {
      if (sequence === requestSequence) {
        loading.value = false;
      }
    }
  }

  async function loadMore(): Promise<void> {
    if (loading.value || loadingMore.value || items.value.length >= total.value) return;

    const sequence = requestSequence;
    loadingMore.value = true;
    error.value = null;
    try {
      const page = await getAssetApi().findPage(buildQuery(items.value.length));
      if (sequence !== requestSequence) return;
      items.value = [...items.value, ...page.items];
      total.value = page.total;
    } catch (caught) {
      if (sequence === requestSequence) {
        error.value = describeError(caught, 'Не удалось догрузить ассеты');
      }
    } finally {
      if (sequence === requestSequence) {
        loadingMore.value = false;
      }
    }
  }

  async function setScope(nextScope: AssetScope): Promise<void> {
    scope.value = nextScope;
    await reload();
  }

  async function upload(file: File): Promise<AssetItem | null> {
    uploading.value = true;
    error.value = null;
    try {
      const fileRecord = await getFileApi().upload(file);
      const name = file.name.replace(/\.[^.]+$/u, '').trim();
      const item = await getAssetApi().create({
        collectionModule: options.getCollectionModule(),
        collectionCode: options.getCollectionCode(),
        filePublicId: fileRecord.publicId,
        scope: scope.value,
        ...(name === '' ? {} : { name }),
      });
      items.value = [item, ...items.value];
      total.value += 1;

      return item;
    } catch (caught) {
      error.value = describeError(caught, 'Не удалось загрузить ассет');

      return null;
    } finally {
      uploading.value = false;
    }
  }

  async function rename(item: AssetItem, name: string): Promise<void> {
    const normalizedName = name.trim();
    if (normalizedName === '' || normalizedName === item.name) return;

    mutating.value = true;
    error.value = null;
    try {
      const updated = await getAssetApi().update(item.id, normalizedName);
      items.value = items.value.map((entry) => (entry.id === updated.id ? updated : entry));
    } catch (caught) {
      error.value = describeError(caught, 'Не удалось переименовать ассет');
    } finally {
      mutating.value = false;
    }
  }

  async function archive(item: AssetItem): Promise<void> {
    mutating.value = true;
    error.value = null;
    try {
      await getAssetApi().archive(item.id);
      items.value = items.value.filter((entry) => entry.id !== item.id);
      total.value = Math.max(0, total.value - 1);
    } catch (caught) {
      error.value = describeError(caught, 'Не удалось убрать ассет в архив');
    } finally {
      mutating.value = false;
    }
  }

  function buildQuery(offset: number): AssetQuery {
    const query: AssetQuery = {
      collectionModule: options.getCollectionModule(),
      collectionCode: options.getCollectionCode(),
      scope: scope.value,
      limit: PAGE_SIZE,
      offset,
    };
    const trimmedSearch = search.value.trim();
    if (trimmedSearch !== '') query.q = trimmedSearch;
    if (options.getHasMeta?.() === true) query.hasMeta = true;

    return query;
  }

  function clearSearchTimer(): void {
    if (searchTimer !== null) {
      clearTimeout(searchTimer);
      searchTimer = null;
    }
  }

  watch(search, () => {
    clearSearchTimer();
    searchTimer = setTimeout(() => void reload(), 250);
  });
  onScopeDispose(clearSearchTimer);

  return {
    scope,
    items,
    total,
    search,
    loading,
    loadingMore,
    uploading,
    mutating,
    error,
    reload,
    loadMore,
    setScope,
    upload,
    rename,
    archive,
  };
}

function describeError(caught: unknown, fallback: string): string {
  return caught instanceof AssetApiError ? caught.message : fallback;
}
