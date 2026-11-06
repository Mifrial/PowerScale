/** Дуга маршрута из узла в узел. */
export interface MapRouteLink {
  to: number;
  edgeId: number;
  distance: number;
  cost: number;
}
