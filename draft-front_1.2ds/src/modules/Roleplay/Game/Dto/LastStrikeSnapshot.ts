export interface LastStrikeHit {
  targetKey: string;
  attackSr: number;
  reaction?: string;
  damaged?: boolean;
}

export interface LastStrikeSnapshot {
  kind: 'lethal' | 'other';
  hits: LastStrikeHit[];
}
