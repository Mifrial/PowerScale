/** Диапазон лет «годы → ступень возраста» у вида/расы. Полуинтервал [ageStart, ageEnd). */
export interface AgeRange {
  /** Имя ступени (из правила 'age', напр. «Молодой»). */
  age: string;
  ageStart: number;
  ageEnd: number;
}
