/** Слой защиты доспеха в display-форме. */
export interface DefenseLineView {
  defense: string;
  /** «доспеха» / «поддоспешника»; null — источника нет. */
  sourceLabel: string | null;
  durability: number;
}
