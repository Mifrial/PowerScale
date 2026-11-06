/** Попадание указателя в объект карты. */
export interface MapHit {
  kind: 'node' | 'area' | 'line';
  id: number;
}
