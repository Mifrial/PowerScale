import { ref, watch } from 'vue';
import type { Ref } from 'vue';
import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';
import type { MapRoute } from '@/modules/Roleplay/Map/Dto/MapRoute';
import type { MapVariant } from '@/modules/Roleplay/Map/Dto/MapVariant';
import { MapRouteGrid } from '@/modules/Roleplay/Map/Service/MapRouteGrid';
import { mapGeometryService } from '@/modules/Roleplay/Map/Service/Instance/mapGeometryService';

/** Маршрут по черновику: локальная сетка без запроса. */
export function useMapRoute(params: {
  map: Ref<MapDetail | null>;
  variant: Ref<MapVariant | null>;
}) {
  const route = ref<MapRoute | null>(null);
  const error = ref<string | null>(null);
  const from = ref<MapPoint | null>(null);
  const to = ref<MapPoint | null>(null);
  const grid = new MapRouteGrid(mapGeometryService);

  function clampPoint(point: MapPoint): MapPoint {
    const variant = params.variant.value;
    const width = variant?.baseFile?.width ?? 1200;
    const height = variant?.baseFile?.height ?? 900;

    return [
      Math.max(0, Math.min(width, Math.round(point[0]))),
      Math.max(0, Math.min(height, Math.round(point[1]))),
    ];
  }

  function pick(point: MapPoint): void {
    const picked = clampPoint(point);
    if (from.value === null || to.value !== null) {
      from.value = picked;
      to.value = null;
      route.value = null;
      error.value = null;

      return;
    }

    to.value = picked;
    build();
  }

  function build(): void {
    const detail = params.map.value;
    const variant = params.variant.value;
    if (!detail || !variant || from.value === null || to.value === null) return;
    error.value = null;
    try {
      const field = grid.build(detail, variant);
      route.value = grid.findRoute(field, from.value, to.value, variant.millimetersPerPixel);
    } catch (caught) {
      route.value = null;
      error.value = caught instanceof Error ? caught.message : 'Не удалось построить маршрут';
    }
  }

  function clear(): void {
    route.value = null;
    error.value = null;
    from.value = null;
    to.value = null;
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
    from,
    to,
    pick,
    build,
    clear,
  };
}
