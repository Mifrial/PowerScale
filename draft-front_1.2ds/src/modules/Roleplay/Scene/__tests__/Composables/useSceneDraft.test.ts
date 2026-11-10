import { describe, expect, it } from 'vitest';
import type { SceneDetail } from '@/modules/Roleplay/Scene/Dto/SceneDetail';
import type { SceneDraftElement } from '@/modules/Roleplay/Scene/Dto/SceneDraftElement';
import { useSceneDraft } from '@/modules/Roleplay/Scene/Composables/useSceneDraft';
import { SCENE_TOOLS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_TOOLS';

function detail(): SceneDetail {
  return {
    id: 1,
    ownerId: 1,
    name: 'Локация',
    description: '',
    resolution: 64,
    schemaVersion: 1,
    isArchived: false,
    version: 1,
    updatedBy: null,
    createdAt: 0,
    updatedAt: 0,
    canEdit: true,
    boards: [
      {
        id: 10,
        name: 'Двор',
        sortOrder: 0,
        baseFile: null,
        boundWidth: 1000,
        boundHeight: 800,
        occupancyCell: 16,
        zQuantum: 16,
        gridStep: 0,
      },
    ],
    elements: [],
    enclosures: [],
  };
}

const pathTool = SCENE_TOOLS.find((tool) => tool.id === 'terrain-path')!;

function pathElement(state: ReturnType<typeof useSceneDraft>): SceneDraftElement {
  return state.draft.value.elements[0];
}

describe('useSceneDraft history', () => {
  it('undoes and redoes a created path', () => {
    const state = useSceneDraft(detail());

    state.createPath(
      pathTool,
      [
        [0, 100],
        [500, 100],
        [1000, 200],
      ],
      40,
    );

    expect(state.draft.value.elements).toHaveLength(1);
    expect(state.canUndo.value).toBe(true);

    state.undo();
    expect(state.draft.value.elements).toHaveLength(0);
    expect(state.canRedo.value).toBe(true);

    state.redo();
    expect(state.draft.value.elements).toHaveLength(1);
  });

  it('groups a drag into one history step', () => {
    const state = useSceneDraft(detail());
    const elementId = state.createPath(
      pathTool,
      [
        [0, 100],
        [500, 100],
      ],
      40,
    )!;

    state.beginEdit();
    state.moveVertex(elementId, 0, [10, 120]);
    state.moveVertex(elementId, 0, [30, 140]);
    state.moveVertex(elementId, 0, [50, 160]);
    state.endEdit();

    expect(pathElement(state).shape.type).toBe('path');

    state.undo();
    const shape = pathElement(state).shape;
    if (shape.type !== 'path') return;
    expect(shape.points[0]).toEqual([0, 100]);

    state.undo();
    expect(state.draft.value.elements).toHaveLength(0);
  });

  it('moves bezier handles symmetrically', () => {
    const state = useSceneDraft(detail());
    const elementId = state.createPath(
      pathTool,
      [
        [0, 100],
        [500, 100],
        [1000, 100],
      ],
      40,
    )!;

    state.moveHandle(elementId, 1, 'out', [40, 30], true);
    const middle = pathElement(state).shape;
    if (middle.type !== 'path') return;

    expect(middle.handles?.[1].out).toEqual([40, 30]);
    expect(middle.handles?.[1].in).toEqual([-40, -30]);
  });

  it('inserts and removes a path vertex', () => {
    const state = useSceneDraft(detail());
    const elementId = state.createPath(
      pathTool,
      [
        [0, 100],
        [500, 100],
      ],
      40,
    )!;

    expect(state.insertVertex(elementId, [250, 100], 10)).toBe(true);
    const inserted = pathElement(state).shape;
    if (inserted.type !== 'path') return;
    expect(inserted.points).toHaveLength(3);

    state.removeVertex(elementId, 1);
    const removed = pathElement(state).shape;
    if (removed.type !== 'path') return;
    expect(removed.points).toHaveLength(2);
  });

  it('keeps dirty state consistent across undo', () => {
    const state = useSceneDraft(detail());
    state.setMeta({ name: 'Новая' });

    expect(state.isDirty.value).toBe(true);
    state.undo();
    expect(state.isDirty.value).toBe(false);
  });
});

describe('useSceneDraft duplicate', () => {
  it('copies the selection and selects the copies', () => {
    const state = useSceneDraft(detail());
    const elementId = state.createPath(
      pathTool,
      [
        [0, 100],
        [500, 100],
      ],
      40,
    )!;
    state.select([elementId]);

    state.duplicateSelected();

    expect(state.draft.value.elements).toHaveLength(2);
    expect(state.selection.value).toHaveLength(1);
    expect(state.selection.value[0]).not.toBe(elementId);
    expect(state.draft.value.elements[1].shape).not.toBe(state.draft.value.elements[0].shape);

    state.undo();
    expect(state.draft.value.elements).toHaveLength(1);
  });
});
