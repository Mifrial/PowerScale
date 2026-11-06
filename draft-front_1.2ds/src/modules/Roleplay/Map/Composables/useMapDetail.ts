import { computed, onUnmounted, ref } from 'vue';
import type { Ref } from 'vue';
import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import type { MapHit } from '@/modules/Roleplay/Map/Dto/MapHit';
import type { MapSummary } from '@/modules/Roleplay/Map/Dto/MapSummary';
import type { MapVariant } from '@/modules/Roleplay/Map/Dto/MapVariant';
import { getMapApi } from '@/modules/Roleplay/Map/init';
import { MapApiError } from '@/modules/Roleplay/Map/Service/MapApiError';

/** Состояние страницы карты: загрузка снимка, справочники и выбор варианта. */
export function useMapDetail(params: { map: Ref<MapDetail | null> }) {
  const catalog = ref<MapSummary[]>([]);
  const selectedVariantId = ref<number | null>(null);
  const selected = ref<MapHit | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  let controller: AbortController | null = null;

  const variant = computed<MapVariant | null>(() => {
    const detail = params.map.value;
    if (!detail) return null;
    const selectedVariant = detail.variants.find((candidate) => candidate.id === selectedVariantId.value);
    if (selectedVariant) return selectedVariant;

    return detail.variants.find((candidate) => candidate.isDefault) ?? detail.variants[0] ?? null;
  });

  const canEdit = computed(() => params.map.value?.canEdit ?? false);

  const selectedNode = computed(() => {
    if (selected.value?.kind !== 'node') return null;

    return params.map.value?.nodes.find((node) => node.id === selected.value?.id) ?? null;
  });

  const selectedArea = computed(() => {
    if (selected.value?.kind !== 'area') return null;

    return params.map.value?.areas.find((area) => area.id === selected.value?.id) ?? null;
  });

  const selectedLine = computed(() => {
    if (selected.value?.kind !== 'line') return null;

    return params.map.value?.lines.find((line) => line.id === selected.value?.id) ?? null;
  });

  async function load(mapId: number): Promise<MapDetail | null> {
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = null;
    try {
      const [detail, page] = await Promise.all([
        getMapApi().get(mapId, controller.signal),
        getMapApi().findPage({ limit: 100, offset: 0 }, controller.signal),
      ]);
      catalog.value = page.items;
      selected.value = null;

      return detail;
    } catch (caught) {
      if (controller.signal.aborted) return null;
      error.value = messageOf(caught);

      return null;
    } finally {
      if (!controller?.signal.aborted) loading.value = false;
    }
  }

  function selectVariant(variantId: number): void {
    selectedVariantId.value = variantId;
    selected.value = null;
  }

  function resetVariant(detail: MapDetail): void {
    const currentId = selectedVariantId.value;
    const hasCurrent = detail.variants.some((candidate) => candidate.id === currentId);
    selectedVariantId.value = hasCurrent
      ? currentId
      : (detail.variants.find((candidate) => candidate.isDefault)?.id ?? detail.variants[0]?.id ?? null);
  }

  function messageOf(caught: unknown): string {
    if (caught instanceof MapApiError) return caught.message;
    if (caught instanceof Error) return caught.message;

    return 'Не удалось загрузить карту';
  }

  onUnmounted(() => controller?.abort());

  return {
    catalog,
    variant,
    selectedVariantId,
    selected,
    selectedNode,
    selectedArea,
    selectedLine,
    loading,
    error,
    canEdit,
    load,
    selectVariant,
    resetVariant,
  };
}
