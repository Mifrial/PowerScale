/** Геометрия ковра травы: квады в мировых координатах с UV в атлас. */
export interface SceneGrassMesh {
  positions: Float32Array;
  uvs: Float32Array;
  /** RGBA на вершину: тинт и прозрачность пучка. */
  tints: Float32Array;
  /** Точка основания пучка на вершину: задел под наклон травы. */
  origins: Float32Array;
  indices: Uint32Array;
}
