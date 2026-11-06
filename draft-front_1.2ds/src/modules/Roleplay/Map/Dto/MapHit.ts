/** Попадание указателя в объект карты. */
export interface MapHit {
  kind: 'node' | 'area' | 'line' | 'edge';
  id: number;
}
