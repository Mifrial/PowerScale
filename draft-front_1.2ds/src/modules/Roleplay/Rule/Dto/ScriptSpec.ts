import type { ScriptKind } from '@/modules/Roleplay/Rule/Enum/ScriptKind';

/** Спека письменности: фонетика (один уровень) или иероглифы (три). */
export interface ScriptSpec {
  type: 'script';
  kind: ScriptKind;
}
