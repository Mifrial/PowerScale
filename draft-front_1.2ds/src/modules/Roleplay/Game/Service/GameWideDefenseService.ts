import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GamePhpOpenWideStrike } from '@/modules/Roleplay/Game/Dto/GamePhpOpenWideStrike';
import type { GamePhpOpenWideStrikeTarget } from '@/modules/Roleplay/Game/Dto/GamePhpOpenWideStrikeTarget';

/**
 * Права и ожидание широкого удара PHP 1 → N.
 *
 * Владелец активного персонажа-цели отвечает за свою цель. Ведущий отвечает
 * за любую цель, включая НПС. Владелец атакующего видит ожидание.
 */
export class GameWideDefenseService {
  targetKey(target: GamePhpOpenWideStrikeTarget): CombatEntityKey {
    return `${target.type}:${target.id}` as CombatEntityKey;
  }

  attackerKey(strike: GamePhpOpenWideStrike): CombatEntityKey {
    return `${strike.attacker.type}:${strike.attacker.id}` as CombatEntityKey;
  }

  isAttackerOwner(
    strike: GamePhpOpenWideStrike,
    userId: number | null | undefined,
    memberships: GameCharacterMembership[],
  ): boolean {
    if (strike.attacker.type !== 'character' || userId === null || userId === undefined) return false;
    const membership = memberships.find((row) => row.characterId === strike.attacker.id);

    return Boolean(membership && membership.membershipStatus === 'active' && membership.characterOwnerId === userId);
  }

  isTargetOwner(
    target: GamePhpOpenWideStrikeTarget,
    userId: number | null | undefined,
    memberships: GameCharacterMembership[],
  ): boolean {
    if (target.type !== 'character' || userId === null || userId === undefined) return false;
    const membership = memberships.find((row) => row.characterId === target.id);

    return Boolean(membership && membership.membershipStatus === 'active' && membership.characterOwnerId === userId);
  }

  canAnswer(
    target: GamePhpOpenWideStrikeTarget,
    userId: number | null | undefined,
    memberships: GameCharacterMembership[],
    canWriteStrike: boolean,
  ): boolean {
    if (this.isTargetOwner(target, userId, memberships)) return true;
    if (!canWriteStrike) return false;

    return target.type === 'npc' || target.type === 'character';
  }

  ownTargets(
    strike: GamePhpOpenWideStrike,
    userId: number | null | undefined,
    memberships: GameCharacterMembership[],
  ): GamePhpOpenWideStrikeTarget[] {
    return strike.targets.filter((target) => this.isTargetOwner(target, userId, memberships));
  }

  answerableTargets(
    strike: GamePhpOpenWideStrike,
    userId: number | null | undefined,
    memberships: GameCharacterMembership[],
    canWriteStrike: boolean,
  ): GamePhpOpenWideStrikeTarget[] {
    if (canWriteStrike) return [...strike.targets];

    return this.ownTargets(strike, userId, memberships);
  }

  pendingTargets(strike: GamePhpOpenWideStrike): GamePhpOpenWideStrikeTarget[] {
    return strike.targets.filter((target) => !target.answered);
  }

  canAnswerAny(
    strike: GamePhpOpenWideStrike,
    userId: number | null | undefined,
    memberships: GameCharacterMembership[],
    canWriteStrike: boolean,
  ): boolean {
    return (
      this.answerableTargets(strike, userId, memberships, canWriteStrike).some((target) => !target.answered) ||
      (canWriteStrike && strike.targets.length > 0)
    );
  }

  shouldAutoOpen(
    strike: GamePhpOpenWideStrike,
    userId: number | null | undefined,
    memberships: GameCharacterMembership[],
    canWriteStrike: boolean,
  ): boolean {
    if (this.pendingTargets(strike).length === 0) return false;
    if (canWriteStrike || this.isAttackerOwner(strike, userId, memberships)) return true;

    return this.ownTargets(strike, userId, memberships).some((target) => !target.answered);
  }

  waitingText(
    strike: GamePhpOpenWideStrike,
    memberships: GameCharacterMembership[],
    npcNames: Record<number, string> = {},
  ): string {
    const pending = this.pendingTargets(strike);
    if (pending.length === 0) return 'Все цели ответили';

    const names = pending.map((target) =>
      target.type === 'npc'
        ? npcNames[target.id] || `НПС #${target.id}`
        : memberships.find((row) => row.characterId === target.id)?.characterName || `персонаж #${target.id}`,
    );

    return `Ожидаем ответ: ${names.join(', ')}`;
  }
}
