import type { SceneSaveSnapshot } from '@/modules/Roleplay/Scene/Dto/SceneSaveSnapshot';

/** Сборка пустого снимка новой локации. */
export class SceneSnapshotFactory {
  /** Пустая локация с одним полем; временные id отрицательные. */
  createDefaultSnapshot(name = 'Новая локация'): SceneSaveSnapshot {
    return {
      name,
      description: '',
      resolution: 64,
      schemaVersion: 1,
      boards: [
        {
          id: -1,
          name: 'Поле',
          sortOrder: 0,
          filePublicId: null,
          boundWidth: 2560,
          boundHeight: 2560,
          occupancyCell: 16,
          zQuantum: 16,
          gridStep: 32,
        },
      ],
      elements: [],
      enclosures: [],
    };
  }
}
