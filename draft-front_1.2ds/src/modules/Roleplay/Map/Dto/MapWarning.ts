/** Предупреждение после публикации карты. */
export interface MapWarning {
  code: string;
  edgeId: number;
  blockerKind: 'area' | 'line';
  blockerId: number;
}
