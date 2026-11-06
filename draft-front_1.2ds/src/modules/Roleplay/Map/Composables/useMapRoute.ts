import { computed, ref, watch } from 'vue';
import type { Ref } from 'vue';
import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import type { MapRoute } from '@/modules/Roleplay/Map/Dto/MapRoute';
import type { MapTerrain } from '@/modules/Roleplay/Map/Dto/MapTerrain';
import type { MapVariant } from '@/modules/Roleplay/Map/Dto/MapVariant';
import { MapApiError } from '@/modules/Roleplay/Map/Service/MapApiError';
import { MapRouteGraph } from '@/modules/Roleplay/Map/Service/MapRouteGraph';
import { mapGeometryService } from '@/modules/Roleplay/Map/Service/Instance/mapGeometryService';

/** Маршрут по черновику: локальный расчёт без запроса. */
export function useMapRoute(params: {
  map: Ref<MapDetail | null>;
  variant: Ref<MapVariant | null>;
  terrains: Ref<MapTerrain[]>;
}) {
  const route = ref<MapRoute | null>(null);
  const error = ref<string | null>(null);
  const graph = new MapRouteGraph(mapGeometryService);

  const nodeOptions = computed(() => {
    const detail = params.map.value;
    const variant = params.variant.value;
    if (!detail || !variant) return [];

    return detail.nodes
      .filter((node) => node.placements.some((item) => item.variantId === variant.id && item.isPresent))
      .map((node) => ({
        id: node.id,
        name: node.isPoi && node.name ? node.name : `Узел #${node.id}`,
      }));
  });

  const poiOptions = computed(() => {
    const detail = params.map.value;
    if (!detail) return [];

    return nodeOptions.value.filter((option) => {
      const node = detail.nodes.find((candidate) => candidate.id === option.id);

      return node?.isPoi === true && node.name !== '';
    });
  });

  function build(fromNodeId: number, toNodeId: number): void {
    const detail = params.map.value;
    const variant = params.variant.value;
    if (!detail || !variant) return;
    error.value = null;
    try {
      const edges = detail.edges.filter((edge) => edge.variantId === variant.id);
      const placements = mapGeometryService.placementPoints(detail.nodes, variant.id);
      route.value = graph.findRoute(
        edges,
        placements,
        params.terrains.value,
        variant.millimetersPerPixel,
        fromNodeId,
        toNodeId,
      );
    } catch (caught) {
      route.value = null;
      error.value = caught instanceof MapApiError ? caught.message : 'Не удалось построить маршрут';
    }
  }

  function clear(): void {
    route.value = null;
    error.value = null;
  }

  watch(
    () => params.map.value,
    () => clear(),
    { deep: true },
  );

  watch(
    () => params.variant.value?.id,
    () => clear(),
  );

  return {
    route,
    error,
    nodeOptions,
    poiOptions,
    build,
    clear,
  };
}
