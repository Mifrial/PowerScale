/** Найденный путь без геометрии. */
export interface MapRoutePath {
  nodeIds: number[];
  edgeIds: number[];
  distance: number;
  cost: number;
}
