import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import {
  ADVANTAGE_SOURCE_CIRCUMSTANCES,
  ADVANTAGE_SOURCE_MULTI_ATTACK,
} from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';
import type { CombatActionOption } from '@/modules/Roleplay/Game/Utils/combatActions';
import {
  asActionAbilitySpec,
  asProcessAbilitySpec,
  actionOdCost,
  findRuleByRef,
} from '@/modules/Roleplay/Game/Utils/combatActions';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';
import { ATTACK_KEYWORD_IDS } from '@/modules/Roleplay/Game/Constant/Combat/ATTACK_KEYWORD_IDS';
import { pushProfileService } from '@/modules/Roleplay/Game/Service/Instance/pushProfileService';

export class AttackActionSourceService {
  list(rules: Rule[], overview: CharacterOverview | null): CombatActionOption[] {
    const ownedRuleIds = new Set(overview?.abilities.map((ability) => ability.ruleCode) ?? []);

    return rules.flatMap((rule) => {
      const actionSpec = asActionAbilitySpec(rule);
      const processSpec = asProcessAbilitySpec(rule);
      const isAttack = (rule.keywordIds ?? []).includes(ATTACK_KEYWORD_IDS.attack);
      if (!isAttack || (!actionSpec && !processSpec)) return [];
      if (!ownedRuleIds.has(rule.code) && !this.isAutomatic(actionSpec?.zones)) return [];

      return [
        {
          ruleCode: rule.code,
          code: rule.code,
          name: rule.name,
          odCost: actionSpec ? actionOdCost(actionSpec.action_components) : 0,
          effects: actionEffectService.effectsOf(rule),
          isAttack: true,
          isProcess: processSpec !== null,
          process: processSpec ?? undefined,
          attackMode: actionSpec?.attack_mode,
        },
      ];
    });
  }

  extraTargetHitAdvantage(targetCount: number): number {
    return targetCount <= 1 ? 0 : 1 - targetCount;
  }

  extraTargetHitModifiers(targetCount: number): AdvantageModifier[] {
    const delta = this.extraTargetHitAdvantage(targetCount);
    if (!delta) return [];

    return [{ source_code: ADVANTAGE_SOURCE_CIRCUMSTANCES, source_label: 'Обстоятельства', delta }];
  }

  compatibleProfiles(rule: Rule | null, attacks: AttackOverview[], rules: Rule[] = []): AttackOverview[] {
    if (!rule) return attacks;
    const keywords = new Set(rule.keywordIds ?? []);
    const melee = keywords.has(ATTACK_KEYWORD_IDS.melee) || keywords.has(ATTACK_KEYWORD_IDS.processMelee);
    const ranged = keywords.has(ATTACK_KEYWORD_IDS.ranged) || keywords.has(ATTACK_KEYWORD_IDS.processRanged);
    const byRange =
      !melee && !ranged
        ? attacks
        : attacks.filter(
            (attack) => (melee && attack.profileType === 'strike') || (ranged && attack.profileType !== 'strike'),
          );
    const typeCodes = actionEffectService.requiredDamageTypeCodes(rule);
    const byType =
      typeCodes.length === 0
        ? byRange
        : byRange.filter((attack) => attack.damageTypeCode !== null && typeCodes.includes(attack.damageTypeCode));

    return pushProfileService.compatibleProfiles(rule, byType, rules);
  }

  isProfileAvailable(profile: AttackOverview | null, availableProfiles: AttackOverview[]): boolean {
    if (!profile) return false;

    return availableProfiles.some(
      (candidate) =>
        candidate.itemRuleCode === profile.itemRuleCode &&
        candidate.profileType === profile.profileType &&
        (candidate.profileIndex ?? 0) === (profile.profileIndex ?? 0) &&
        (profile.inventoryItemId == null ||
          candidate.inventoryItemId == null ||
          candidate.inventoryItemId === profile.inventoryItemId) &&
        (profile.instanceIndex == null ||
          candidate.instanceIndex == null ||
          candidate.instanceIndex === profile.instanceIndex),
    );
  }

  sameItemRef(left: string, right: string, rules: Rule[]): boolean {
    if (left === right) return true;
    const leftRule = findRuleByRef(rules, left);
    const rightRule = findRuleByRef(rules, right);

    return Boolean(leftRule && rightRule && leftRule.code === rightRule.code);
  }

  favoriteAttack(
    attacks: AttackOverview[],
    favorite: { itemRuleCode: string; profileType: AttackOverview['profileType']; profileIndex: number } | null,
    rules: Rule[],
  ): AttackOverview | null {
    if (!favorite) return null;

    return (
      attacks.find(
        (attack) =>
          this.sameItemRef(attack.itemRuleCode, favorite.itemRuleCode, rules) &&
          attack.profileType === favorite.profileType &&
          (attack.profileIndex ?? 0) === favorite.profileIndex,
      ) ?? null
    );
  }

  maxTargets(rule: Rule | null): number {
    const actionSpec = rule ? asActionAbilitySpec(rule) : null;

    return actionSpec?.attack_mode === 'wide' ? (actionSpec.max_targets ?? 1) : 1;
  }

  validateTargetCount(rule: Rule | null, targetKeys: string[]): string | null {
    const maxTargets = this.maxTargets(rule);
    const uniqueCount = new Set(targetKeys).size;
    if (uniqueCount <= maxTargets) return null;

    return `Атака может иметь не более ${maxTargets} целей`;
  }

  strikeCount(rule: Rule | null): number {
    const actionSpec = rule ? asActionAbilitySpec(rule) : null;
    const count = actionSpec?.strike_count ?? 1;

    return count > 1 ? Math.floor(count) : 1;
  }

  isSameWeaponStrikes(rule: Rule | null): boolean {
    return Boolean(rule ? asActionAbilitySpec(rule)?.same_weapon : false);
  }

  minWeapons(rule: Rule | null): number {
    const spec = rule ? asActionAbilitySpec(rule) : null;
    if (!spec?.same_weapon) return this.strikeCount(rule);
    const min = spec.min_weapons ?? 2;

    return min > 1 ? Math.floor(min) : 2;
  }

  liftsMaxWeapons(
    rule: Rule | null,
    actor: { abilities: CharacterAbility[] } | null | undefined,
    rules: Rule[],
  ): boolean {
    if (!rule || !this.isSameWeaponStrikes(rule)) return false;
    const owned = new Set(
      (actor?.abilities ?? []).filter((ability) => ability.level >= 1).map((ability) => ability.ruleCode),
    );
    for (const candidate of rules) {
      if (!owned.has(candidate.code) || candidate.type !== 'ability' || !candidate.spec) continue;
      if (!('parent_ability_code' in candidate.spec) || candidate.spec.parent_ability_code !== rule.code) continue;
      if (candidate.spec.lift_parent_max_weapons) return true;
    }

    return false;
  }

  maxWeapons(rule: Rule | null, actor: { abilities: CharacterAbility[] } | null | undefined, rules: Rule[]): number {
    if (!this.isSameWeaponStrikes(rule)) return this.strikeCount(rule);
    if (this.liftsMaxWeapons(rule, actor, rules)) return Number.POSITIVE_INFINITY;

    const spec = rule ? asActionAbilitySpec(rule) : null;
    const max = spec?.max_weapons ?? this.minWeapons(rule);

    return max > 1 ? Math.floor(max) : 2;
  }

  requiresDistinctWeapons(rule: Rule | null): boolean {
    return Boolean(rule ? asActionAbilitySpec(rule)?.distinct_weapons : false);
  }

  isSequentialStrikes(rule: Rule | null): boolean {
    return this.strikeCount(rule) > 1 && !this.isSameWeaponStrikes(rule);
  }

  sameWeaponCheckModifiers(rule: Rule | null, weaponCount: number): AdvantageModifier[] {
    if (!this.isSameWeaponStrikes(rule) || weaponCount < 1) return [];

    return [
      {
        source_code: ADVANTAGE_SOURCE_MULTI_ATTACK,
        source_label: 'множественная атака',
        delta: -weaponCount,
      },
    ];
  }

  nextSameWeaponProfile(
    profiles: AttackOverview[],
    used: AttackOverview[],
    itemRuleCode: string,
  ): AttackOverview | null {
    const usedKeys = new Set(used.map((profile) => this.weaponKey(profile)));

    return (
      profiles.find((profile) => profile.itemRuleCode === itemRuleCode && !usedKeys.has(this.weaponKey(profile))) ??
      null
    );
  }

  profilesForSameWeapon(
    profiles: AttackOverview[],
    usedInOtherSlots: AttackOverview[],
    current: AttackOverview | null,
    itemRuleCode: string,
  ): AttackOverview[] {
    const usedKeys = new Set(usedInOtherSlots.map((profile) => this.weaponKey(profile)));
    const currentKey = current ? this.weaponKey(current) : null;

    return profiles.filter((profile) => {
      if (profile.itemRuleCode !== itemRuleCode) return false;
      const key = this.weaponKey(profile);

      return key === currentKey || !usedKeys.has(key);
    });
  }

  validateSameWeapons(rule: Rule | null, profiles: AttackOverview[]): string | null {
    if (!this.isSameWeaponStrikes(rule)) return null;
    if (profiles.length < this.minWeapons(rule)) {
      return `Нужно минимум ${this.minWeapons(rule)} экземпляра одинакового оружия`;
    }
    const codes = new Set(profiles.map((profile) => profile.itemRuleCode));
    if (codes.size !== 1) return 'Все экземпляры должны быть одним оружием';
    const keys = profiles.map((profile) => this.weaponKey(profile));
    if (new Set(keys).size !== keys.length) return 'Нужны разные экземпляры одного оружия';

    return null;
  }

  weaponKey(profile: AttackOverview): string {
    return profile.inventoryItemId != null
      ? `id:${profile.inventoryItemId}:${profile.instanceIndex ?? 0}`
      : `code:${profile.itemRuleCode}:${profile.instanceIndex ?? 0}`;
  }

  nextDistinctWeaponProfile(profiles: AttackOverview[], used: AttackOverview[]): AttackOverview | null {
    const usedKeys = new Set(used.map((profile) => this.weaponKey(profile)));

    return profiles.find((profile) => !usedKeys.has(this.weaponKey(profile))) ?? null;
  }

  profilesForOtherWeapons(
    profiles: AttackOverview[],
    usedInOtherSlots: AttackOverview[],
    current: AttackOverview | null,
  ): AttackOverview[] {
    const usedKeys = new Set(usedInOtherSlots.map((profile) => this.weaponKey(profile)));
    const currentKey = current ? this.weaponKey(current) : null;

    return profiles.filter((profile) => {
      const key = this.weaponKey(profile);

      return key === currentKey || !usedKeys.has(key);
    });
  }

  validateDistinctWeapons(rule: Rule | null, profiles: AttackOverview[]): string | null {
    if (!this.requiresDistinctWeapons(rule)) return null;
    const keys = profiles.map((profile) => this.weaponKey(profile));
    if (new Set(keys).size === keys.length) return null;

    return 'Каждый удар должен использовать другое оружие';
  }

  sameWeaponCopyCount(profiles: AttackOverview[], itemRuleCode: string): number {
    const keys = new Set(
      profiles.filter((profile) => profile.itemRuleCode === itemRuleCode).map((profile) => this.weaponKey(profile)),
    );

    return keys.size;
  }

  preferredSameWeaponLead(
    profiles: AttackOverview[],
    minCopies: number,
    favorite: AttackOverview | null,
  ): AttackOverview | null {
    if (favorite && this.sameWeaponCopyCount(profiles, favorite.itemRuleCode) >= minCopies) {
      return favorite;
    }
    for (const profile of profiles) {
      if (this.sameWeaponCopyCount(profiles, profile.itemRuleCode) >= minCopies) return profile;
    }

    return favorite ?? profiles[0] ?? null;
  }

  hasUnusedSameWeaponCopy(
    profiles: AttackOverview[],
    used: AttackOverview[],
    itemRuleCode: string | null | undefined,
  ): boolean {
    if (!itemRuleCode) return false;

    return this.nextSameWeaponProfile(profiles, used, itemRuleCode) !== null;
  }

  private isAutomatic(zones: Record<string, unknown> | undefined): boolean {
    return Object.values(zones ?? {}).some(
      (zone) => typeof zone === 'object' && zone !== null && 'kind' in zone && zone.kind === 'automatic',
    );
  }
}
