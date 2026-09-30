import type { CharacterChanged } from '@/modules/Roleplay/Character/Dto/CharacterChanged';
import type { ICharacterChangePort } from '@/modules/Roleplay/Character/Interface/ICharacterChangePort';

/** Process-local порт фактов Character для mock-интеграции и invalidation store. */
export class CharacterChangePort implements ICharacterChangePort {
  private readonly listeners = new Set<(change: CharacterChanged) => void>();

  publish(change: CharacterChanged): void {
    for (const listener of this.listeners) {
      listener(change);
    }
  }

  subscribe(listener: (change: CharacterChanged) => void): () => void {
    this.listeners.add(listener);

    return () => {
      this.listeners.delete(listener);
    };
  }
}
