import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { PushSpec } from '@/modules/Roleplay/Rule/Dto/Ability/PushSpec';
import { asActionAbilitySpec } from '@/modules/Roleplay/Game/Utils/combatActions';
import { HANDS_ITEM_CODE } from '@/modules/Roleplay/Game/Constant/Combat/HANDS_ITEM_CODE';
import { SLASHING_DAMAGE_TYPE_CODE } from '@/modules/Roleplay/Rule/Constant/DamageType/SLASHING_DAMAGE_TYPE_CODE';
import { BLUNT_DAMAGE_TYPE_CODE } from '@/modules/Roleplay/Rule/Constant/DamageType/BLUNT_DAMAGE_TYPE_CODE';

/** Какие профили можно выбрать для толчка. */
export class PushProfileService {
  isPushAction(rule: Rule | null | undefined): boolean {
    return this.pushOf(rule) !== null;
  }

  pushOf(rule: Rule | null | undefined): PushSpec | null {
    if (!rule) return null;

    return asActionAbilitySpec(rule)?.push ?? null;
  }

  compatibleProfiles(rule: Rule | null, attacks: AttackOverview[], rules: Rule[]): AttackOverview[] {
    const push = this.pushOf(rule);
    if (!push) return attacks;
    if (push.profiles === 'slashing_or_blunt_strike') {
      return attacks.filter(
        (attack) =>
          attack.profileType === 'strike' &&
          (attack.damageTypeCode === SLASHING_DAMAGE_TYPE_CODE || attack.damageTypeCode === BLUNT_DAMAGE_TYPE_CODE),
      );
    }

    return attacks.filter(
      (attack) => attack.profileType === 'strike' && this.canPushWithHandsOrShield(attack.itemRuleCode, rules),
    );
  }

  preferredProfile(profiles: AttackOverview[]): AttackOverview | null {
    return profiles.find((profile) => profile.itemRuleCode === HANDS_ITEM_CODE) ?? profiles[0] ?? null;
  }

  canPushWithHandsOrShield(itemRuleCode: string, rules: Rule[]): boolean {
    if (itemRuleCode === HANDS_ITEM_CODE) return true;
    const item = rules.find((candidate) => candidate.code === itemRuleCode && candidate.type === 'item');
    if (!item?.spec || typeof item.spec !== 'object') return false;

    return 'shield' in (item.spec as ItemSpec);
  }
}
