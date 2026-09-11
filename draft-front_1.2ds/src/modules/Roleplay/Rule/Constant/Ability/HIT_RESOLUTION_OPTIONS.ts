/** Варианты доставки попадания для заклинания-действия. */
export const HIT_RESOLUTION_OPTIONS: { title: string; value: 'none' | 'attack' | 'auto' }[] = [
  { title: 'Без атаки', value: 'none' },
  { title: 'Атака (касание / луч)', value: 'attack' },
  { title: 'Автопопадание', value: 'auto' },
];
