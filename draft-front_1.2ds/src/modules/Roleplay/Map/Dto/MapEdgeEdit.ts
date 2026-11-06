/** Правка ребра в черновике. */
export interface MapEdgeEdit {
  fromNodeId: number;
  toNodeId: number;
  terrainCode: string;
  distanceMeters: number | null;
  isDirected: boolean;
  label: string;
}
