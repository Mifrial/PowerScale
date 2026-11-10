import { describe, expect, it } from 'vitest';
import type { SceneDetail } from '@/modules/Roleplay/Scene/Dto/SceneDetail';
import { SceneDraftMapper } from '@/modules/Roleplay/Scene/Service/SceneDraftMapper';

const mapper = new SceneDraftMapper();

function detail(): SceneDetail {
  return {
    id: 7,
    ownerId: 3,
    name: 'Таверна',
    description: 'Двор',
    resolution: 64,
    schemaVersion: 1,
    isArchived: false,
    version: 4,
    updatedBy: 3,
    createdAt: 1,
    updatedAt: 2,
    canEdit: true,
    boards: [
      {
        id: 10,
        name: 'Двор',
        sortOrder: 0,
        baseFile: { publicId: 'file-a', width: 800, height: 600 },
        boundWidth: 2560,
        boundHeight: 2560,
        occupancyCell: 16,
        zQuantum: 16,
        gridStep: 32,
      },
    ],
    elements: [
      {
        id: 20,
        boardId: 10,
        kind: 'decor',
        shape: { type: 'point', x: 10, y: 20 },
        props: { label: 'Бочка', rotation: 0, scale: 100 },
        sortOrder: 1,
        targetBoardId: null,
        asset: { publicId: 'file-b' },
      },
    ],
    enclosures: [],
  };
}

describe('SceneDraftMapper', () => {
  it('maps a detail snapshot into a draft without losing files', () => {
    const draft = mapper.fromDetail(detail());

    expect(draft.id).toBe(7);
    expect(draft.version).toBe(4);
    expect(draft.activeBoardId).toBe(10);
    expect(draft.boards[0].filePublicId).toBe('file-a');
    expect(draft.elements[0].assetPublicId).toBe('file-b');
    expect(draft.elements[0].shape).toEqual({ type: 'point', x: 10, y: 20 });
  });

  it('maps a draft back into a save snapshot', () => {
    const snapshot = mapper.toSnapshot(mapper.fromDetail(detail()));

    expect(snapshot.name).toBe('Таверна');
    expect(snapshot.boards[0].filePublicId).toBe('file-a');
    expect(snapshot.elements[0].props.label).toBe('Бочка');
    expect(snapshot.elements[0].props.assetPublicId).toBe('file-b');
    expect(snapshot.enclosures).toEqual([]);
  });

  it('round-trips a detail through draft and back', () => {
    const source = detail();
    const snapshot = mapper.toSnapshot(mapper.fromDetail(source));

    expect(snapshot.boards).toHaveLength(1);
    expect(snapshot.elements).toHaveLength(1);
    expect(snapshot.elements[0].boardId).toBe(10);
  });
});
