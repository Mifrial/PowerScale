import type { NoteHost } from '@/modules/Messages/Notes/Dto/NoteHost';

/**
 * Реестр метаданных сущностей-хостов заметок.
 */
export class NoteHostRegistry {
  private readonly hosts = new Map<string, NoteHost>();

  register(host: NoteHost): void {
    this.hosts.set(host.type, host);
  }

  get(type: string): NoteHost | undefined {
    return this.hosts.get(type);
  }

  list(): NoteHost[] {
    return [...this.hosts.values()];
  }

  reset(): void {
    this.hosts.clear();
  }
}
