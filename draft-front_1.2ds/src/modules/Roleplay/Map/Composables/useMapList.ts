import { computed, ref } from 'vue';
import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import type { MapMeta } from '@/modules/Roleplay/Map/Dto/MapMeta';
import type { MapPage } from '@/modules/Roleplay/Map/Dto/MapPage';
import type { MapSnapshot } from '@/modules/Roleplay/Map/Dto/MapSnapshot';
import type { MapSummary } from '@/modules/Roleplay/Map/Dto/MapSummary';
import { getMapApi } from '@/modules/Roleplay/Map/init';
import { MapApiError } from '@/modules/Roleplay/Map/Service/MapApiError';

/** Список карт: страница, поиск, архив и создание через публикацию. */
export function useMapList() {
  const items = ref<MapSummary[]>([]);
  const total = ref(0);
  const page = ref(1);
  const itemsPerPage = ref(20);
  const searchQuery = ref('');
  const isArchived = ref(false);
  const loading = ref(false);
  const error = ref<string | null>(null);

  let controller: AbortController | null = null;

  const pageCount = computed(() => Math.max(1, Math.ceil(total.value / itemsPerPage.value)));

  async function load(): Promise<void> {
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = null;
    try {
      const result: MapPage = await getMapApi().findPage(
        {
          limit: itemsPerPage.value,
          offset: (page.value - 1) * itemsPerPage.value,
          q: searchQuery.value.trim() || undefined,
          isArchived: isArchived.value,
        },
        controller.signal,
      );
      items.value = result.items;
      total.value = result.total;
    } catch (caught) {
      if (controller.signal.aborted) return;
      error.value = caught instanceof MapApiError ? caught.message : 'Не удалось загрузить карты';
    } finally {
      if (!controller?.signal.aborted) loading.value = false;
    }
  }

  async function createMap(meta: MapMeta): Promise<MapDetail> {
    const result = await getMapApi().save({ map: emptySnapshot(meta) });
    await load();

    return result;
  }

  async function archiveMap(mapId: number): Promise<void> {
    await getMapApi().archive(mapId);
    await load();
  }

  function setPage(value: number): void {
    page.value = value;
    void load();
  }

  function setSearch(value: string): void {
    searchQuery.value = value;
    page.value = 1;
  }

  function setArchived(value: boolean): void {
    isArchived.value = value;
    page.value = 1;
    void load();
  }

  return {
    items,
    total,
    page,
    itemsPerPage,
    pageCount,
    searchQuery,
    isArchived,
    loading,
    error,
    load,
    createMap,
    archiveMap,
    setPage,
    setSearch,
    setArchived,
  };
}

function emptySnapshot(meta: MapMeta): MapSnapshot {
  return {
    name: meta.name,
    description: meta.description,
    visibility: meta.visibility,
    fillOpacity: meta.fillOpacity,
    variants: [
      {
        id: -1,
        label: 'Основная',
        fromMinutes: null,
        sortOrder: 0,
        filePublicId: null,
        millimetersPerPixel: null,
        isDefault: true,
      },
    ],
    nodes: [],
    areas: [],
    groups: [],
    lines: [],
  };
}
