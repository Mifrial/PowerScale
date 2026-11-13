/** Геометрия полосы вдоль контура: внутренняя кромка на контуре, внешняя — с выносом. */
export interface SceneTerrainBand {
  positions: Float32Array;
  uvs: Float32Array;
  indices: Uint32Array;
}
