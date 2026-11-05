import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GamePhpOpenStrike } from '@/modules/Roleplay/Game/Dto/GamePhpOpenStrike';
import type { GamePhpRunActionRequest } from '@/modules/Roleplay/Game/Dto/GamePhpRunActionRequest';
import type { ReactionActor } from '@/modules/Roleplay/Game/Dto/ReactionActor';
import type { ReactionCoverEffect, ReactionOffer } from '@/modules/Roleplay/Game/Dto/ReactionOffer';
import type { ReactionWeapon } from '@/modules/Roleplay/Game/Dto/ReactionWeapon';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ReactionPerformer, ReactionSpec } from '@/modules/Roleplay/Rule/Dto/Ability/ReactionSpec';
import type { SupportSpec } from '@/modules/Roleplay/Rule/Dto/Ability/SupportSpec';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { ActionComponent } from '@/modules/Roleplay/Rule/Dto/Ability/ActionComponent';
import type { SupportSide } from '@/modules/Roleplay/Rule/Enum/Ability/SupportSide';
import type { WeaponProfileType } from '@/modules/Roleplay/Rule/Enum/Item/WeaponProfileType';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import { ADVANTAGE_SOURCE_LABELS } from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';
import { actionOdCost } from '@/modules/Roleplay/Game/Utils/combatActions';

/** Куда идёт заявка реакции: Поддержка или Прикрытие. */
export type ReactionSubmit =
  | { kind: 'support'; side: SupportSide; itemInventoryId: number }
  | { kind: 'cover'; reaction: string; itemInventoryId: number; itemRuleCode: string };

/**
 * Реакции на открытый удар: какие карточки доступны и что нужно для заявки.
 *
 * Доступность берётся из узла `reaction`: он говорит, когда реакция
 * применима. Конкретный эффект (`support`, `cover_ally`) выбирается после
 * выбора карточки. Кодов правил и частных карточек здесь нет.
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
    for (const coverer of strike.coverers ?? []) {
      reacted.add(this.entityKey(coverer.actor.type, coverer.actor.id));
    }
    const names: Record<SupportSide, string> = {
      attacker: this.nameOf(strike.attackerKind, strike.attackerId, memberships, npcs),
      defender: this.nameOf(strike.defenderKind, strike.defenderId, memberships, npcs),
    };
    const reactions = rules
      .map((rule) => ({
        rule,
        reaction: this.reactionSpec(rule),
        support: this.supportSpec(rule),
        cover: this.coverSpec(rule),
      }))
      .filter(
        (
          entry,
        ): entry is {
          rule: Rule;
          reaction: ReactionSpec;
          support: SupportSpec | null;
          cover: ReactionCoverEffect | null;
        } =>
          entry.reaction !== null &&
          (entry.support !== null || entry.cover !== null) &&
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
        const cost = actionOdCost(this.componentsOf(entry.rule), 0, 'action-points');

        if (entry.support) {
          const profile = entry.support.profile ?? 'strike';
          if (profile !== strike.profileType) continue;
          const weapons = this.weaponsOf(actor.version, rules, profile);
          if (weapons.length === 0) continue;
          offers.push({
            rule: entry.rule,
            actor,
            reaction: entry.reaction,
            cost,
            effect: {
              kind: 'support',
              sides: entry.support.sides.map((value) => ({ value, name: names[value] })),
              weapons,
              advantage: entry.support.advantage,
              source: entry.support.source ?? 'circumstances',
            },
          });
          continue;
        }

        if (entry.cover) {
          if (role !== 'third_party') continue;
          const blockItems = this.blockItemsOf(actor.version, rules);
          if (blockItems.length === 0) continue;
          offers.push({
            rule: entry.rule,
            actor,
            reaction: entry.reaction,
            cost,
            effect: { ...entry.cover, blockItems },
          });
        }
      }
    }

    return offers;
  }

  /** Тело команды объявления реакции. */
  request(
    gameId: number,
    offer: ReactionOffer,
    submit: ReactionSubmit,
    battleId: number,
    expectedBattleVersion: number,
    expectedSheetVersion: number,
    idempotencyKey: string,
  ): GamePhpRunActionRequest {
    const context: Record<string, unknown> = { expectedSheetVersion };
    if (submit.kind === 'support') {
      const weapon = this.weaponOf(offer, submit.itemInventoryId);
      if (!weapon) throw new Error('Оружие реакции не найдено');
      context.support = {
        side: submit.side,
        itemInventoryId: weapon.itemInventoryId,
        itemRuleCode: weapon.itemRuleCode,
        profileType: weapon.profileType,
      };
    } else {
      const item = this.blockItemOf(offer, submit.itemInventoryId);
      if (!item) throw new Error('Предмет блока не найден');
      context.cover = {
        reaction: submit.reaction,
        blockItemInventoryId: item.itemInventoryId,
        blockItemProfileIndex: 0,
        blockItemRuleCode: item.itemRuleCode,
      };
    }

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
        context,
      },
    };
  }

  private weaponOf(offer: ReactionOffer, itemInventoryId: number): ReactionWeapon | null {
    if (offer.effect.kind !== 'support') return null;

    return (
      offer.effect.weapons.find((weapon) => weapon.itemInventoryId === itemInventoryId) ??
      offer.effect.weapons[0] ??
      null
    );
  }

  private blockItemOf(offer: ReactionOffer, itemInventoryId: number): ReactionWeapon | null {
    if (offer.effect.kind !== 'cover') return null;

    return (
      offer.effect.blockItems.find((item) => item.itemInventoryId === itemInventoryId) ??
      offer.effect.blockItems[0] ??
      null
    );
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

  private coverSpec(rule: Rule): ReactionCoverEffect | null {
    if (rule.type !== 'ability' || !rule.spec || typeof rule.spec !== 'object') return null;
    if (!('type' in rule.spec) || rule.spec.type !== 'action') return null;
    const cover = rule.spec.cover_ally;
    if (!cover || !cover.reactions || cover.reactions.length === 0) return null;

    const defenseAdvantages: AdvantageModifier[] = (cover.defense_advantages ?? []).map((row) => ({
      source_code: row.source_code,
      source_label: ADVANTAGE_SOURCE_LABELS[row.source_code] ?? null,
      delta: row.delta,
    }));

    return {
      kind: 'cover',
      reactions: cover.reactions,
      defenseAdvantages,
      blockItems: [],
    };
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

  private blockItemsOf(version: CharacterVersion, rules: Rule[]): ReactionWeapon[] {
    const items: ReactionWeapon[] = [];
    for (const item of version.inventory) {
      if (!item.equipped || !item.ruleCode) continue;
      const rule = rules.find((candidate) => candidate.type === 'item' && candidate.code === item.ruleCode);
      if (!rule) continue;
      const spec = rule.spec as ItemSpec | undefined;
      if (!spec?.block_profile) continue;
      items.push({
        itemInventoryId: item.id,
        itemRuleCode: item.ruleCode,
        name: rule.name,
        profileType: 'strike',
      });
    }

    return items;
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
