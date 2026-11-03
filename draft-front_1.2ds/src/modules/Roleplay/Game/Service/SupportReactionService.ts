import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GamePhpOpenStrike } from '@/modules/Roleplay/Game/Dto/GamePhpOpenStrike';
import type { GamePhpRunActionRequest } from '@/modules/Roleplay/Game/Dto/GamePhpRunActionRequest';
import type { SupportReactionActor } from '@/modules/Roleplay/Game/Dto/SupportReactionActor';
import type { SupportReactionOption } from '@/modules/Roleplay/Game/Dto/SupportReactionOption';
import type { SupportReactionWeapon } from '@/modules/Roleplay/Game/Dto/SupportReactionWeapon';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { SupportSpec } from '@/modules/Roleplay/Rule/Dto/Ability/SupportSpec';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { SupportSide } from '@/modules/Roleplay/Rule/Enum/Ability/SupportSide';
import type { WeaponProfileType } from '@/modules/Roleplay/Rule/Enum/Item/WeaponProfileType';

/**
 * Реакция третьей стороны: кто может поддержать открытый удар и каким оружием.
 */
export class SupportReactionService {
  /** Устойчивый ключ открытого удара для подавления повторного окна. */
  strikeKey(strike: GamePhpOpenStrike): string {
    if (strike.strikeId !== undefined && strike.strikeId > 0) return `strike:${strike.strikeId}`;

    return `${strike.attackerKind}:${strike.attackerId}:${strike.itemInventoryId}:${strike.profileIndex}`;
  }

  /**
   * Кандидаты в поддержку: свои персонажи и, для ведущего, НПС боя.
   *
   * Мастерство проверяет сервер: клиент не дублирует расчёт листа.
   */
  options(
    strike: GamePhpOpenStrike,
    currentUserId: number | null,
    memberships: GameCharacterMembership[],
    npcs: GameNpc[],
    rules: Rule[],
    canWrite: boolean,
  ): SupportReactionOption[] {
    const attackerKey = this.entityKey(strike.attackerKind, strike.attackerId);
    const defenderKey = this.entityKey(strike.defenderKind, strike.defenderId);
    const attackProfile = strike.profileType;
    const supportRules = rules
      .map((rule) => ({ rule, spec: this.supportSpec(rule) }))
      .filter(
        (entry): entry is { rule: Rule; spec: SupportSpec } =>
          entry.spec !== null && (entry.spec.profile ?? 'strike') === attackProfile,
      );
    if (supportRules.length === 0) return [];

    const actors: SupportReactionActor[] = [];
    for (const membership of memberships) {
      if (currentUserId === null && !canWrite) continue;
      if (!canWrite && membership.characterOwnerId !== currentUserId) continue;
      if (membership.membershipStatus !== 'active') continue;
      if (membership.isActiveSessionParticipant === false) continue;
      if (!membership.approvedCharacterVersion) continue;
      actors.push({
        key: this.entityKey('character', membership.characterId),
        kind: 'character',
        id: membership.characterId,
        name: membership.characterName,
        version: membership.approvedCharacterVersion,
      });
    }

    if (canWrite) {
      for (const npc of npcs) {
        if (!npc.version || npc.status !== 'active') continue;
        actors.push({
          key: this.entityKey('npc', npc.id),
          kind: 'npc',
          id: npc.id,
          name: npc.name,
          version: npc.version,
        });
      }
    }

    const options: SupportReactionOption[] = [];
    for (const actor of actors) {
      if (actor.key === attackerKey || actor.key === defenderKey) continue;
      const known = supportRules.find((entry) => this.hasAbility(actor.version, entry.rule.code));
      if (!known) continue;
      const profile = known.spec.profile ?? 'strike';
      const weapons = this.weaponsOf(actor.version, rules, profile);
      if (weapons.length === 0) continue;

      options.push({ actor, rule: known.rule, sides: known.spec.sides, weapons });
    }

    return options;
  }

  /** Тело команды объявления поддержки. */
  request(
    gameId: number,
    option: SupportReactionOption,
    side: SupportSide,
    weapon: SupportReactionWeapon,
    battleId: number,
    expectedBattleVersion: number,
    expectedSheetVersion: number,
    idempotencyKey: string,
  ): GamePhpRunActionRequest {
    return {
      gameId,
      battleId,
      idempotencyKey,
      expectedVersion: expectedBattleVersion,
      action: {
        phase: 'declare',
        kind: 'support',
        code: option.rule.code,
        actor: { type: option.actor.kind, id: option.actor.id },
        context: {
          expectedSheetVersion,
          side,
          itemInventoryId: weapon.itemInventoryId,
          itemRuleCode: weapon.itemRuleCode,
          profileType: weapon.profileType,
        },
      },
    };
  }

  private supportSpec(rule: Rule): SupportSpec | null {
    if (rule.type !== 'ability' || !rule.spec || typeof rule.spec !== 'object') return null;
    if (!('type' in rule.spec) || rule.spec.type !== 'action') return null;
    const support = rule.spec.support;
    if (!support || support.sides.length === 0) return null;

    return support;
  }

  private hasAbility(version: CharacterVersion, ruleCode: string): boolean {
    return version.abilities.some((ability) => ability.ruleCode === ruleCode && ability.level > 0);
  }

  private weaponsOf(version: CharacterVersion, rules: Rule[], profile: WeaponProfileType): SupportReactionWeapon[] {
    const weapons: SupportReactionWeapon[] = [];
    for (const item of version.inventory) {
      if (!item.equipped || !item.ruleCode) continue;
      const rule = rules.find((candidate) => candidate.type === 'item' && candidate.code === item.ruleCode);
      if (!rule) continue;
      const spec = rule.spec as ItemSpec | undefined;
      if (!spec?.weapon?.weapon_profiles.some((row) => row.type === profile)) continue;
      weapons.push({
        itemInventoryId: item.id,
        itemRuleCode: item.ruleCode,
        name: rule.name,
        profileType: profile,
      });
    }

    return weapons;
  }

  private entityKey(kind: string, id: number): CombatEntityKey {
    return `${kind}:${id}` as CombatEntityKey;
  }
}
