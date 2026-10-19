import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GamePhpOpenStrike } from '@/modules/Roleplay/Game/Dto/GamePhpOpenStrike';

/**
 * Права и подписи шага защиты PHP-удара 1 → 1.
 *
 * Игрок действует за своего активного персонажа. Ведущий закрывает НПС-защиту
 * сразу, а защиту персонажа берёт на себя только явным переопределением.
 */
export class GameStrikeDefenseService {
  defenderKey(strike: GamePhpOpenStrike): CombatEntityKey {
    return `${strike.defenderKind}:${strike.defenderId}` as CombatEntityKey;
  }

  attackerKey(strike: GamePhpOpenStrike): CombatEntityKey {
    return `${strike.attackerKind}:${strike.attackerId}` as CombatEntityKey;
  }

  defenderMembership(
    strike: GamePhpOpenStrike,
    memberships: GameCharacterMembership[],
  ): GameCharacterMembership | null {
    if (strike.defenderKind !== 'character') return null;

    return memberships.find((membership) => membership.characterId === strike.defenderId) ?? null;
  }

  isDefenderOwner(
    strike: GamePhpOpenStrike,
    userId: number | null | undefined,
    memberships: GameCharacterMembership[],
  ): boolean {
    if (userId === null || userId === undefined) return false;
    const membership = this.defenderMembership(strike, memberships);
    if (!membership || membership.membershipStatus !== 'active') return false;

    return membership.characterOwnerId === userId;
  }

  /**
   * Может ли пользователь отправить защиту.
   *
   * Владелец защитника — всегда. Ведущий — для НПС сразу, для персонажа только
   * после явного «Взять на себя».
   */
  canResolve(
    strike: GamePhpOpenStrike,
    userId: number | null | undefined,
    memberships: GameCharacterMembership[],
    canWriteStrike: boolean,
    override: boolean,
  ): boolean {
    if (this.isDefenderOwner(strike, userId, memberships)) return true;
    if (!canWriteStrike) return false;
    if (strike.defenderKind === 'npc') return true;

    return override;
  }

  /** Авто-открытие окна: только участник защиты, а не любой игрок. */
  shouldAutoOpen(
    strike: GamePhpOpenStrike,
    userId: number | null | undefined,
    memberships: GameCharacterMembership[],
    canWriteStrike: boolean,
  ): boolean {
    return this.isDefenderOwner(strike, userId, memberships) || canWriteStrike;
  }

  /**
   * Подпись ожидания. Для персонажа — игрок и персонаж, для НПС — ведущий и НПС.
   * Если имя игрока ещё не прогрето, остаётся `#id`.
   */
  waitingText(
    strike: GamePhpOpenStrike,
    memberships: GameCharacterMembership[],
    userNames: Record<number, string>,
    npcNames: Record<number, string> = {},
  ): string {
    if (strike.defenderKind === 'npc') {
      const npcName = npcNames[strike.defenderId] || `НПС #${strike.defenderId}`;

      return `Ожидаем реакцию ведущего за ${npcName}`;
    }
    const membership = this.defenderMembership(strike, memberships);
    const characterName = membership?.characterName || `персонажа #${strike.defenderId}`;
    const ownerId = membership?.characterOwnerId;
    if (!ownerId) return `Ожидаем реакцию владельца за ${characterName}`;
    const ownerName = userNames[ownerId] || `#${ownerId}`;

    return `Ожидаем реакцию ${ownerName} за ${characterName}`;
  }
}
