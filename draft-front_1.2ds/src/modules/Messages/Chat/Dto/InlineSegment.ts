export type InlineSegment = { kind: 'text'; text: string } | { kind: 'token'; type: string; params: string[] };
