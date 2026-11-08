import { nextTick, ref } from 'vue';
import { describe, expect, it } from 'vitest';
import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import { useMapRoute } from '@/modules/Roleplay/Map/Composables/useMapRoute';

function detail(id = 1): MapDetail {
  return {
    id,
    ownerId: 1,
    name: 'Аустан',
    description: '',
    visibility: 'private',
    fillOpacity: 55,
    isArchived: false,
    version: 1,
    updatedBy: 1,
    createdAt: 0,
    updatedAt: 0,
    canEdit: true,
    variants: [
      {
        id: 1,
        label: 'Основная',
        fromMinutes: null,
        sortOrder: 0,
        baseFile: null,
        millimetersPerPixel: 1000,
        isDefault: true,
      },
    ],
    nodes: [],
    areas: [
      {
        id: 1,
        layer: 'object',
        color: '#8b6b4a',
        decor: {},
        speed: 0,
        label: '',
        nodeId: null,
        groupId: null,
        sortOrder: 0,
        shapes: [
          {
            variantId: 1,
            path: [
              [0, 0],
              [10, 0],
              [10, 10],
            ],
            handles: [],
            isPresent: true,
          },
        ],
      },
    ],
    groups: [],
    lines: [],
  };
}

describe('useMapRoute', () => {
  it('does not clear the route on a deep draft edit', async () => {
    const map = ref(detail());
    const variant = ref(map.value.variants[0]);
    const state = useMapRoute({ map, variant });
    state.from.value = [1, 1];
    state.to.value = [2, 2];

    // Раньше здесь стоял deep-watch по всему черновику: на 6000 объектах
    // он обходил весь граф на каждое движение мыши.
    map.value.areas[0].label = 'правка';
    await nextTick();

    expect(state.from.value).toEqual([1, 1]);
    expect(state.to.value).toEqual([2, 2]);
  });

  it('clears the route when the map reference changes', async () => {
    const map = ref(detail());
    const variant = ref(map.value.variants[0]);
    const state = useMapRoute({ map, variant });
    state.from.value = [1, 1];
    state.to.value = [2, 2];

    map.value = detail(2);
    await nextTick();

    expect(state.from.value).toBeNull();
    expect(state.to.value).toBeNull();
  });
});
