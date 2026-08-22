import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterStatus } from '@/modules/Roleplay/Character/Enum/CharacterStatus';
import type { GameMemberRole } from '@/modules/Roleplay/Game/Enum/GameMemberRole';
import type { GameMembershipStatus } from '@/modules/Roleplay/Game/Enum/GameMembershipStatus';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';

/**
 * Членство персонажа в игре (ТР §3 `game_characters`; модель версий — Баг 1, 2026-08-20).
 * `activeVersion` — одобренная, ЗАМОРОЖЕНА (снимок на момент approve), меняется только модерацией.
 * `latestVersion` — самая свежая версия персонажа (`versions[id]`, источник истины); приёмник всех
 * изменений вне сессии, изменение → автоподача на модерацию.
 * `pendingVersion` — производная `snapshot(latest + оверлей сессии)`, только просмотр для модератора;
 * в `versions[id]` никогда не пишется.
 * `overlay` — сессионный слой (боевые правки и/или копия листа из редактора); во время сессии игра
 * читает `activeVersion + overlay`, эффективная версия — `overlay.sheet ?? activeVersion`.
 * Модерация = diff(active, latest + overlay) → approve/reject.
 * `visibility` — видимость листа для игроков (дефолт «Полностью»); настраивает владелец или ГМ.
 */
export interface GameCharacterMembership {
  gameId: number;
  characterId: number;
  characterName: string;
  characterOwnerId: number;
  characterOwnerName: string;
  characterStatus: CharacterStatus;
  role: GameMemberRole;
  membershipStatus: GameMembershipStatus;
  activeVersion: CharacterVersion | null;
  latestVersion: CharacterVersion | null;
  pendingVersion: CharacterVersion | null;
  overlay: GameCombatOverlay | null;
  visibility: SheetVisibility;
  /** Бонусные очки от ГМ сверх лимитов игры (реальные лимиты персонажа = лимит игры + бонус). */
  osBonus: number;
  orBonus: number;
  olBonus: number;
  updatedAt: string;
}
