import type { NoteState } from '@/modules/Messages/Notes/Enum/NoteState';

export interface NoteHostFilter {
  q?: string;
  hostType?: string;
  authorId?: number;
  state?: NoteState;
  limit?: number;
}
