export type Session = { kind: 'anon' } | { kind: 'guest' } | { kind: 'user'; userId: number };
