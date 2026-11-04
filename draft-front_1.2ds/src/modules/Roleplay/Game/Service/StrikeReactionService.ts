import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GamePhpOpenStrike } from '@/modules/Roleplay/Game/Dto/GamePhpOpenStrike';
import type { GamePhpRunActionRequest } from '@/modules/Roleplay/Game/Dto/GamePhpRunActionRequest';
import type { ReactionActor } from '@/modules/Roleplay/Game/Dto/ReactionActor';
import type { ReactionOffer } from '@/modules/Roleplay/Game/Dto/ReactionOffer';
import type { ReactionWeapon } from '@/modules/Roleplay/Game/Dto/ReactionWeapon';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ReactionPerformer, ReactionSpec } from '@/modules/Roleplay/Rule/Dto/Ability/ReactionSpec';
import type { SupportSpec } from '@/modules/Roleplay/Rule/Dto/Ability/SupportSpec';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { ActionComponent } from '@/modules/Roleplay/Rule/Dto/Ability/ActionComponent';
import type { SupportSide } from '@/modules/Roleplay/Rule/Enum/Ability/SupportSide';
import type { WeaponProfileType } from '@/modules/Roleplay/Rule/Enum/Item/WeaponProfileType';
import { actionOdCost } from '@/modules/Roleplay/Game/Utils/combatActions';

/**
 * Реакции на открытый удар: какие карточки доступны и что нужно для заявки.
 *
 * Доступность берётся из спеки карточки: узел `reaction` говорит, когда
 * реакция применима, узел эффекта (`support`) — что она делает. Кодов правил
 * и частных карточек здесь нет.
 */
export class StrikeReactionService {
  /** Устойчивый ключ открытого удара для подавления повторного окна. */
  strikeKey(strike: GamePhpOpenStrike): string {
    if (strike.strikeId !== undefined && strike.strikeId > 0) return `strike:${strike.strikeId}`;

    return `${strike.attackerKind}:${strike.attackerId}:${strike.itemInventoryId}:${strike.profileIndex}`;
  }

  /**
   * Доступные реакции: свои персонажи и, для ведущего, НПС боя.
   *
   * Мастерство проверяет сервер: клиент не дублирует расчёт листа.
   */
  offers(
    strike: GamePhpOpenStrike,
    currentUserId: number | null,
    memberships: GameCharacterMembership[],
    npcs: GameNpc[],
    rules: Rule[],
    canWrite: boolean,
  ): ReactionOffer[] {
    const attackerKey = this.entityKey(strike.attackerKind, strike.attackerId);
    const defenderKey = this.entityKey(strike.defenderKind, strike.defenderId);
    const reacted = new Set(
      (strike.supporters ?? []).map((supporter) => this.entityKey(supporter.actor.type, supporter.actor.id)),
    );
    const names: Record<SupportSide, string> = {
      attacker: this.nameOf(strike.attackerKind, strike.attackerId, memberships, npcs),
      defender: this.nameOf(strike.defenderKind, strike.defenderId, memberships, npcs),
    };
    const reactions = rules
      .map((rule) => ({ rule, reaction: this.reactionSpec(rule), support: this.supportSpec(rule) }))
      .filter(
        (entry): entry is { rule: Rule; reaction: ReactionSpec; support: SupportSpec } =>
          entry.reaction !== null &&
          entry.support !== null &&
          entry.reaction.trigger === 'strike' &&
          entry.reaction.window === 'before_defense',
      );
    if (reactions.length === 0) return [];

    const actors = this.actorsOf(currentUserId, memberships, npcs, canWrite);
    const offers: ReactionOffer[] = [];
    for (const actor of actors) {
      if (reacted.has(actor.key)) continue;
      const role = actor.key === attackerKey ? 'attacker' : actor.key === defenderKey ? 'defender' : 'third_party';
      for (const entry of reactions) {
        if (!this.allows(entry.reaction.performer, role)) continue;
        if (!this.hasAbility(actor.version, entry.rule.code)) continue;
        const profile = entry.support.profile ?? 'strike';
        if (profile !== strike.profileType) continue;
        const weapons = this.weaponsOf(actor.version, rules, profile);
        if (weapons.length === 0) continue;
        const components = this.componentsOf(entry.rule);
        offers.push({
          rule: entry.rule,
          actor,
          reaction: entry.reaction,
          sides: entry.support.sides.map((value) => ({ value, name: names[value] })),
          weapons,
          advantage: entry.support.advantage,
          source: entry.support.source ?? 'circumstances',
          cost: actionOdCost(components, 0, 'action-points'),
        });
      }
    }

    return offers;
  }

  /** Тело команды объявления реакции. */
  request(
    gameId: number,
    offer: ReactionOffer,
    side: SupportSide,
    weapon: ReactionWeapon,
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
        kind: 'reaction',
        code: offer.rule.code,
        actor: { type: offer.actor.kind, id: offer.actor.id },
        context: {
          expectedSheetVersion,
          support: {
            side,
            itemInventoryId: weapon.itemInventoryId,
            itemRuleCode: weapon.itemRuleCode,
            profileType: weapon.profileType,
          },
        },
      },
    };
  }

  private allows(performer: ReactionPerformer, role: string): boolean {
    return performer === 'any' || performer === role;
  }

  private actorsOf(
    currentUserId: number | null,
    memberships: GameCharacterMembership[],
    npcs: GameNpc[],
    canWrite: boolean,
  ): ReactionActor[] {
    const actors: ReactionActor[] = [];
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

    return actors;
  }

  private reactionSpec(rule: Rule): ReactionSpec | null {
    if (rule.type !== 'ability' || !rule.spec || typeof rule.spec !== 'object') return null;
    if (!('type' in rule.spec) || rule.spec.type !== 'action') return null;

    return rule.spec.reaction ?? null;
  }

  private supportSpec(rule: Rule): SupportSpec | null {
    if (rule.type !== 'ability' || !rule.spec || typeof rule.spec !== 'object') return null;
    if (!('type' in rule.spec) || rule.spec.type !== 'action') return null;
    const support = rule.spec.support;
    if (!support || support.sides.length === 0) return null;

    return support;
  }

  private componentsOf(rule: Rule): ActionComponent[] {
    if (!rule.spec || typeof rule.spec !== 'object' || !('type' in rule.spec) || rule.spec.type !== 'action') return [];

    return rule.spec.action_components ?? [];
  }

  private hasAbility(version: CharacterVersion, ruleCode: string): boolean {
    return version.abilities.some((ability) => ability.ruleCode === ruleCode && ability.level > 0);
  }

  private weaponsOf(version: CharacterVersion, rules: Rule[], profile: WeaponProfileType): ReactionWeapon[] {
    const weapons: ReactionWeapon[] = [];
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

  private nameOf(kind: string, id: number, memberships: GameCharacterMembership[], npcs: GameNpc[]): string {
    if (kind === 'character') {
      return memberships.find((membership) => membership.characterId === id)?.characterName ?? 'Атакующий';
    }
    if (kind === 'npc') return npcs.find((npc) => npc.id === id)?.name ?? 'Защищающийся';

    return 'Участник';
  }

  private entityKey(kind: string, id: number): CombatEntityKey {
    return `${kind}:${id}` as CombatEntityKey;
  }
}
