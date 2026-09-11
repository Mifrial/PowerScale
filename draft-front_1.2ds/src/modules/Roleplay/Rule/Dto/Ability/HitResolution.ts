/** Как действие доставляет попадание. РУ урона — только РУ атаки (для auto — заданный rating). */
export type HitResolution = { type: 'none' } | { type: 'attack' } | { type: 'auto'; rating: number };
