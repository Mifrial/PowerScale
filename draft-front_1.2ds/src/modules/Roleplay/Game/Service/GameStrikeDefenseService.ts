import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GamePhpOpenStrike } from '@/modules/Roleplay/Game/Dto/GamePhpOpenStrike';

/** Что делать с окном удара: открыть, переключить, оставить как есть или не трогать. */
export type GameStrikeWindowAction = 'keep' | 'open' | 'switch' | 'wait';

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
   * Что делать с окном при открытом ударе.
   *
   * 'keep' — открытое окно уже про этот удар.
   * 'open' — окна нет, удар надо открыть.
   * 'switch' — окно привязано к другому удару: закрываем его и открываем новый.
   * 'wait' — открыто чужое окно (набор нового удара): не трогаем, ждём
   * явного переключения от пользователя.
   */
  windowAction(
    strike: GamePhpOpenStrike,
    userId: number | null | undefined,
    memberships: GameCharacterMembership[],
    canWriteStrike: boolean,
    window: { open: boolean; defenderKey: CombatEntityKey | null; bound: boolean },
  ): GameStrikeWindowAction {
    const visible = this.shouldAutoOpen(strike, userId, memberships, canWriteStrike);
    if (!window.open) {
      return visible ? 'open' : 'wait';
    }
    // Окно уже про этот удар: ведущий объявил его сам из этого окна.
    if (window.defenderKey === this.defenderKey(strike)) return 'keep';
    if (window.bound) {
      return visible ? 'switch' : 'wait';
    }

    return 'wait';
  }

  /**
   * Кому показывать баннер «Есть открытый удар — ответить».
   *
   * Владелец защитника отвечает за персонажа, ведущий может взять защиту
   * на себя. Остальным баннер не нужен.
   */
  canAnswerPending(
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
