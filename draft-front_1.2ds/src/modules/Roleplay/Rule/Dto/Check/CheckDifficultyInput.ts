export type CheckDifficultyInput = { kind: 'ask' } | { kind: 'from_state'; state_code: string } | { kind: 'none' };
