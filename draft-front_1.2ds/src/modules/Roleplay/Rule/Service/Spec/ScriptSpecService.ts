import type { ScriptSpec } from '@/modules/Roleplay/Rule/Dto/ScriptSpec';
import type { ScriptKind } from '@/modules/Roleplay/Rule/Enum/ScriptKind';

/** Пустая и нормализованная спека письменности. */
export class ScriptSpecService {
  createEmpty(): ScriptSpec {
    return { type: 'script', kind: 'alphabetic' };
  }

  resolve(spec: unknown): ScriptSpec {
    if (!spec || typeof spec !== 'object' || !('type' in spec) || spec.type !== 'script') {
      return this.createEmpty();
    }
    const kind: ScriptKind = (spec as ScriptSpec).kind === 'logographic' ? 'logographic' : 'alphabetic';

    return { type: 'script', kind };
  }
}
