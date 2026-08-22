import type { ChronicleRefKind } from '@/modules/Roleplay/Game/Enum/ChronicleRefKind';

/** Связанная сущность записи летописи (персонаж игры или НПС игры) — отображается чипом. */
export interface ChronicleRef {
  kind: ChronicleRefKind;
  id: number;
}
