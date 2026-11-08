import type { NoteHostContext } from '@/modules/Messages/Notes/Dto/NoteHostContext';

export interface NoteHost {
  type: string;
  label: string;
  icon: string;
  route?: (hostId: number) => string | null;
  resolveEditorContext?: (hostId: number) => Promise<NoteHostContext>;
}
