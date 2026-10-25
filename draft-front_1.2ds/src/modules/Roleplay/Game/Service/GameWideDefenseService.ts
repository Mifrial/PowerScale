import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GamePhpOpenWideStrike } from '@/modules/Roleplay/Game/Dto/GamePhpOpenWideStrike';
import type { GamePhpOpenWideStrikeTarget } from '@/modules/Roleplay/Game/Dto/GamePhpOpenWideStrikeTarget';

/**
 * Права и ожидание широкого удара PHP 1 → N.
 *
 * Владелец активного персонажа-цели отвечает за свою цель. Ведущий сразу
 * отвечает за НПС, а защиту персонажа берёт только явным переопределением.
 * Владелец атакующего видит ожидание.
 */
export class GameWideDefenseService {
  targetKey(target: { type: string | null; id: number }): CombatEntityKey {
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
    override = false,
  ): boolean {
    if (this.isTargetOwner(target, userId, memberships)) return true;
    if (!canWriteStrike) return false;
    if (target.type === 'npc') return true;

    return target.type === 'character' && override;
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
    override = false,
  ): GamePhpOpenWideStrikeTarget[] {
    return strike.targets.filter((target) => this.canAnswer(target, userId, memberships, canWriteStrike, override));
  }

  pendingTargets(strike: GamePhpOpenWideStrike): GamePhpOpenWideStrikeTarget[] {
    return strike.targets.filter((target) => !target.answered);
  }

  canAnswerAny(
    strike: GamePhpOpenWideStrike,
    userId: number | null | undefined,
    memberships: GameCharacterMembership[],
    canWriteStrike: boolean,
    override = false,
  ): boolean {
    return this.answerableTargets(strike, userId, memberships, canWriteStrike, override).some(
      (target) => !target.answered,
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
    userNames: Record<number, string> = {},
    npcNames: Record<number, string> = {},
  ): string {
    const pending = this.pendingTargets(strike);
    if (pending.length === 0) return 'Все цели ответили';

    const names = pending.map((target) => this.targetLabel(target, memberships, userNames, npcNames));

    return `Ожидаем ответ: ${names.join(', ')}`;
  }

  private targetLabel(
    target: GamePhpOpenWideStrikeTarget,
    memberships: GameCharacterMembership[],
    userNames: Record<number, string>,
    npcNames: Record<number, string>,
  ): string {
    if (target.type === 'npc') {
      return `ведущего за ${npcNames[target.id] || `НПС #${target.id}`}`;
    }
    const membership = memberships.find((row) => row.characterId === target.id);
    const characterName = membership?.characterName || `персонажа #${target.id}`;
    const ownerId = membership?.characterOwnerId;

    return ownerId ? `${userNames[ownerId] || `#${ownerId}`} за ${characterName}` : `владельца за ${characterName}`;
  }
}
