export type ProcessTransition =
  | { mode: 'chain'; max_shift: number; direction?: 'forward' | 'both' }
  | { mode: 'free' }
  | { mode: 'custom'; edges: { from: string; to: string }[] }
