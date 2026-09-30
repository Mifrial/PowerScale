import type { CharacterChanged } from '@/modules/Roleplay/Character/Dto/CharacterChanged';

/** Порт post-commit facts Character; consumer не получает права менять actual. */
export interface ICharacterChangePort {
  publish(change: CharacterChanged): void;
  subscribe(listener: (change: CharacterChanged) => void): () => void;
}
