import type { ITokenSource } from '@/modules/Messages/Inline/Interface/ITokenSource';

export interface NoteHostContext {
  inlineContext: Record<string, unknown>;
  tokenSources: ITokenSource[];
}
