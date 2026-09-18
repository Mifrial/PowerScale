export interface LastStrikeHit {
  targetKey: string;
  attackSr: number;
  reaction?: string;
}

export interface LastStrikeSnapshot {
  kind: 'lethal' | 'other';
  hits: LastStrikeHit[];
}
