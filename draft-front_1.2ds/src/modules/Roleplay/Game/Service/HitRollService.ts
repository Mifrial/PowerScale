import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import { CHECK_HIT_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { checkRollService } from '@/modules/Roleplay/Game/Service/Instance/checkRollService';

import { resolveHitProcedure } from '@/modules/Roleplay/Game/Utils/resolveStrikeProcedure';
import { rollPoolDefaults } from '@/modules/Roleplay/Game/Utils/initiativeRoll';
import { rollEngine } from '@/modules/Roleplay/Game/Service/Roll/Instance/rollEngine';
import { checkResolutionService } from '@/modules/Roleplay/Rule/Service/Instance/checkResolutionService';
import { itemModifierService } from '@/modules/Roleplay/Rule/Service/Instance/itemModifierService';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Enum/HitDefenseReaction';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import {
  ADVANTAGE_SOURCE_CIRCUMSTANCES,
  ADVANTAGE_SOURCE_STATE,
} from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';
import {
  applyStrikeMastery,
  bestCombatMastery,
  strikeCharacteristicMods,
  STRIKE_STAT_LABEL,
} from '@/modules/Roleplay/Game/Utils/strikeCharacteristicMods';
import { DEFAULT_FALLOFF } from '@/modules/Roleplay/Character/init';
import { weaponAttackRangeService } from '@/modules/Roleplay/Character/init';
import { withRangedHitBreakdown } from '@/modules/Roleplay/Game/Utils/rangedHitDifficultyRows';

import type { HitBlockProfile } from '@/modules/Roleplay/Game/Dto/HitBlockProfile';
import type { HitRollInput } from '@/modules/Roleplay/Game/Dto/HitRollInput';
import type { HitCheckRoll } from '@/modules/Roleplay/Game/Dto/HitCheckRoll';
import { FLANK_DEFENSE_LABEL } from '@/modules/Roleplay/Game/Constant/Combat/FLANK_DEFENSE_LABEL';
export class HitRollService {
  private static readonly FALLBACK_MASTERY: DimensionalNumberValue = { base: 3, size: -1 };

  /** Мастерство оружия для профиля: тайл семьи, иначе общий стат секции. */
  weaponMasteryForAttack(
    overview: CharacterOverview | null,
    attack: Pick<AttackOverview, 'itemName' | 'profileType'>,
  ): DimensionalNumberValue {
    const ranged = attack.profileType === 'throw' || attack.profileType === 'shoot';
    const section = ranged ? overview?.combat?.ranged : overview?.combat?.melee;
    const tile = section?.weapons.find(
      (weapon) => weapon.name === attack.itemName || weapon.shortName === attack.itemName,
    );

    return tile?.value ?? section?.stat.value ?? HitRollService.FALLBACK_MASTERY;
  }

  maxDimensional(a: DimensionalNumberValue, b: DimensionalNumberValue): DimensionalNumberValue {
    return new DimensionalNumber(a).compare(new DimensionalNumber(b)) >= 0 ? a : b;
  }

  /**
   * Грань = база эффективности; размер эффективности складывается с размером мастерства
   * (как размер куба — на успехи).
   */
  poolSpec(
    label: string,
    mastery: DimensionalNumberValue,
    efficiency: DimensionalNumberValue,
    adv: number,
    rules: Rule[],
    extraAdvantages: AdvantageModifier[] = [],
    actorKey?: CombatEntityKey,
    masteryAdjustments: AdvantageModifier[] = [],
  ): DiceRollSpec {
    const defaults = rollPoolDefaults(rules);
    const named = checkRollService.namedCheckSpec(label, mastery, adv, rules, actorKey);

    return {
      ...named,
      dieFaces: defaults.dieFaces,
      efficiency: Math.max(1, efficiency.base),
      efficiencySize: efficiency.size,
      poolSize: mastery.size,
      dieSize: mastery.size + efficiency.size,
      advantages: [...named.advantages, ...extraAdvantages],
      masteryAdjustments: masteryAdjustments.filter((entry) => entry.delta !== 0),
    };
  }

  listBlockProfiles(
    version: CharacterVersion | null,
    rules: Rule[],
    options: { shieldsOnly?: boolean } = {},
  ): HitBlockProfile[] {
    if (!version) return [];
    const profiles: HitBlockProfile[] = [];
    for (const item of version.inventory) {
      if (!item.equipped || !item.ruleId) continue;
      const rule = rules.find((entry) => entry.id === item.ruleId);
      const spec = rule?.spec as ItemSpec | undefined;
      if (!rule || !spec) continue;
      const modifiers = (item.modifierRuleIds ?? [])
        .map((id) => rules.find((entry) => entry.id === id))
        .filter((entry): entry is Rule => entry != null);
      const stacked = itemModifierService.applyStack(spec, modifiers, []).spec;
      const block = options.shieldsOnly
        ? stacked.shield?.block
        : (stacked.shield?.block ?? stacked.weapon?.block_profile);
      if (!block) continue;
      profiles.push({ itemRuleId: item.ruleId, itemName: rule.name, efficiency: block.efficiency });
    }

    return profiles;
  }

  private strikeMasteryAdjustments(delta: number): AdvantageModifier[] {
    if (!delta) return [];

    return [{ source_code: ADVANTAGE_SOURCE_STATE, source_label: STRIKE_STAT_LABEL, delta }];
  }

  rollHit(input: HitRollInput, rng: DiceRng, rules: Rule[], mechanics: Mechanic[]): HitCheckRoll {
    const ranged = input.attack.profileType === 'throw' || input.attack.profileType === 'shoot';
    const procedure = resolveHitProcedure(input.attack.profileType, rules, mechanics);
    const attackMods = strikeCharacteristicMods(input.attackerOverview, rules);
    const attackMastery = applyStrikeMastery(
      this.weaponMasteryForAttack(input.attackerOverview, input.attack),
      attackMods.masteryDelta,
    );
    const attackSpec = this.poolSpec(
      input.attackerLabel,
      attackMastery,
      input.attack.accuracy,
      input.attackerAdv ?? 0,
      rules,
      attackMods.advantages,
      input.attackerKey,
      this.strikeMasteryAdjustments(attackMods.masteryDelta),
    );
    const distance = input.distanceIpari ?? 0;
    const falloff = input.attack.falloff ?? DEFAULT_FALLOFF;
    const cover = input.cover ?? 0;
    if (input.reaction === 'ignore') {
      if (!ranged) {
        return {
          attacker: checkRollService.rollNamedCheck(
            attackSpec,
            CHECK_HIT_CODE,
            procedure.ignoreDefense,
            rng,
            rules,
            mechanics,
          ),
          defender: null,
        };
      }
      const parts = weaponAttackRangeService.rangedHitDifficultyParts(cover, 0, distance, falloff);

      return {
        attacker: withRangedHitBreakdown(
          checkRollService.rollNamedCheck(attackSpec, CHECK_HIT_CODE, parts.difficulty, rng, rules, mechanics),
          parts,
          'ignore',
        ),
        defender: null,
      };
    }
    const defenseMods = strikeCharacteristicMods(input.defenderOverview, rules);
    const defenseMastery = applyStrikeMastery(
      bestCombatMastery(input.defenderOverview, ranged),
      defenseMods.masteryDelta,
    );
    const chosen = input.defenseEfficiency ?? procedure.dodgeEfficiency;
    const efficiency = input.reaction === 'block' ? this.maxDimensional(chosen, procedure.minBlockEfficiency) : chosen;
    const flankAdv =
      input.flank && !input.turn
        ? [{ source_code: ADVANTAGE_SOURCE_CIRCUMSTANCES, source_label: FLANK_DEFENSE_LABEL, delta: -2 }]
        : [];
    const defenseSpec = this.poolSpec(
      input.defenderLabel,
      defenseMastery,
      efficiency,
      input.defenderAdv ?? 0,
      rules,
      [...defenseMods.advantages, ...flankAdv],
      input.defenderKey,
      this.strikeMasteryAdjustments(defenseMods.masteryDelta),
    );
    if (ranged) {
      const attached = checkResolutionService.resolveCheckAttachedRuleCodes(CHECK_HIT_CODE, rules);
      const defenderRolled = rollEngine.roll(defenseSpec, rng, rules, mechanics, attached, []);
      const parts = weaponAttackRangeService.rangedHitDifficultyParts(
        cover,
        Math.max(0, defenderRolled.totalSuccesses),
        distance,
        falloff,
      );

      return {
        attacker: withRangedHitBreakdown(
          checkRollService.rollNamedCheck(attackSpec, CHECK_HIT_CODE, parts.difficulty, rng, rules, mechanics),
          parts,
          input.reaction,
        ),
        defender: defenderRolled,
      };
    }
    const joint = checkRollService.rollJointCheck(attackSpec, defenseSpec, CHECK_HIT_CODE, rng, rules, mechanics);

    return { attacker: joint.left, defender: joint.right };
  }

  /** Совместимость со старыми тестами. */
  rollMeleeHit(input: HitRollInput, rng: DiceRng, rules: Rule[], mechanics: Mechanic[]): HitCheckRoll {
    return this.rollHit(input, rng, rules, mechanics);
  }

  hitHasDefenseRoll(reaction: HitDefenseReaction): boolean {
    return reaction !== 'ignore';
  }
}
