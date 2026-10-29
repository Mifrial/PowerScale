import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ActionEffect } from '@/modules/Roleplay/Rule/Dto/Ability/ActionEffect';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import {
  CHARACTERISTIC_BASE_RANGE,
  characterHandsService,
  characterOverviewService,
  stateRuntimeEffectsService,
} from '@/modules/Roleplay/Character/init';
import { CHARACTERISTIC_STRENGTH_CODE } from '@/modules/Roleplay/Rule/Constant/Characteristic/CHARACTERISTIC_STRENGTH_CODE';
import { CharacteristicNumber } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';
import { checkResolutionService } from '@/modules/Roleplay/Rule/init';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';
import { checkRollService } from '@/modules/Roleplay/Game/Service/Instance/checkRollService';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import { damageTypeHooksService } from '@/modules/Roleplay/Game/Service/Instance/damageTypeHooksService';
import { woundInstanceService } from '@/modules/Roleplay/Game/Service/Instance/woundInstanceService';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';

type ActionModifierEffect = Extract<ActionEffect, { type: 'action_modifier' }>;
type SelfDamageOutcome = Extract<ActionModifierEffect['effects'][number], { type: 'self_damage' }>;

const SIZE_STEP = CHARACTERISTIC_BASE_RANGE.max - CHARACTERISTIC_BASE_RANGE.min + 1;

interface ModifierApplyInput {
  gameId: number;
  actorKey: CombatEntityKey;
  version: CharacterVersion;
  rules: Rule[];
  mechanics: Mechanic[];
  actionRule: Rule | null | undefined;
  attack: {
    itemRuleCode: string;
    profileType: 'strike' | 'throw' | 'shoot';
    profileIndex?: number;
    damageTypeCode: string | null;
  };
  selectedChildCodes: readonly string[];
  actorName: string;
  rng?: DiceRng;
  chatId: number | null;
  speaker: ChatSpeaker;
  sendMessage: (
    content: string,
    attachments: ChatAttachment[],
    chatId: number,
    speaker: ChatSpeaker,
  ) => Promise<unknown>;
}

/**
 * Модификатор действия в мок-режиме: проверка из спеки и последствия по их типам.
 *
 * Контракт поведения — PHP `GameActionModifierRunner`.
 */
export class ActionModifierService {
  constructor(private readonly resolveGameApi: () => IGameApi) {}

  optionsOf(
    parentCode: string | null | undefined,
    abilities: CharacterAbility[],
    rules: Rule[],
  ): ReturnType<typeof actionEffectService.actionModifierOptions> {
    return actionEffectService.actionModifierOptions(
      parentCode,
      abilities.filter((ability) => ability.level >= 1).map((ability) => ability.ruleCode),
      rules,
    );
  }

  strikeStrength(
    version: CharacterVersion,
    rules: Rule[],
    actionRule: Rule | null | undefined,
    attack: {
      itemRuleCode: string;
      profileType: 'strike' | 'throw' | 'shoot';
      profileIndex?: number;
      damageTypeCode: string | null;
    },
  ): DimensionalNumberValue {
    const base = stateRuntimeEffectsService
      .effectiveCharacteristicValues(version, rules)
      .get(CHARACTERISTIC_STRENGTH_CODE) ?? {
      base: 3,
      size: 0,
    };
    const occupy = characterHandsService.occupyHandsForAttack(
      version.inventory,
      attack.itemRuleCode,
      attack.profileType,
      attack.profileIndex ?? 0,
      rules,
    );
    const grip = characterHandsService.gripBonus(occupy);
    const action = actionEffectService.currentAttackActionCharacteristicModifierForActor(
      actionRule,
      attack.profileType,
      version,
      attack,
      rules,
    );

    return CharacteristicNumber.from(base).modifyWith(grip + action).value;
  }

  selfDamage(strikeStrength: DimensionalNumberValue, sizeDelta: number): DimensionalNumberValue {
    return CharacteristicNumber.from(strikeStrength).modifyWith(sizeDelta * SIZE_STEP).value;
  }

  async apply(input: ModifierApplyInput): Promise<{ skipPending: boolean; overlay: GameCombatOverlay | null }> {
    const selected = this.optionsOf(input.actionRule?.code, input.version.abilities, input.rules).filter((entry) =>
      input.selectedChildCodes.includes(entry.rule.code),
    );
    let skipPending = false;

    for (const option of selected) {
      if (!this.applies(option.effect, input.attack.profileType)) continue;
      if (!(await this.check(option.rule, option.effect, input))) continue;

      for (const outcome of option.effect.effects) {
        if (outcome.type === 'skip_after_action') {
          skipPending = true;
          continue;
        }

        await this.selfDamageOutcome(input, outcome);
      }
    }

    return { skipPending, overlay: null };
  }

  private applies(effect: ActionModifierEffect, profileType: 'strike' | 'throw' | 'shoot'): boolean {
    if (!effect.scope.components.includes(profileType)) return false;

    return effect.scope.hit_count === 'all' || effect.scope.hit_count >= 1;
  }

  private async check(rule: Rule, effect: ActionModifierEffect, input: ModifierApplyInput): Promise<boolean> {
    if (!effect.check) return true;

    const willpowerCode = attackDamageService.willpowerRule(input.rules)?.code ?? null;
    const characteristicCode = checkResolutionService.resolveCheckCharacteristicCode(
      effect.check.code,
      input.rules,
      willpowerCode,
    );
    const value = characteristicCode
      ? (stateRuntimeEffectsService
          .effectiveCharacteristicValues(input.version, input.rules)
          .get(characteristicCode) ?? {
          base: 3,
          size: 0,
        })
      : { base: 3, size: 0 };
    const adv = characteristicCode
      ? stateRuntimeEffectsService.checkAdvantageFromStates(input.version, input.rules, {
          kind: 'characteristic',
          code: characteristicCode,
        })
      : 0;
    const roll = checkRollService.rollNamedCheck(
      checkRollService.namedCheckSpec(rule.name, value, adv, input.rules, input.actorKey),
      effect.check.code,
      { base: effect.check.difficulty, size: 0 },
      input.rng ?? Math.random,
      input.rules,
      input.mechanics,
    );
    const passed = roll.check?.passed === true;
    if (input.chatId !== null) {
      await input.sendMessage('', [{ type: ROLL_ATTACHMENT_TYPE, payload: roll }], input.chatId, input.speaker);
      await input.sendMessage(
        `${input.actorName}: ${rule.name} — ${passed ? 'успех' : 'провал'}.`,
        [],
        input.chatId,
        input.speaker,
      );
    }

    return passed;
  }

  private async selfDamageOutcome(input: ModifierApplyInput, outcome: SelfDamageOutcome): Promise<void> {
    const strength = this.strikeStrength(input.version, input.rules, input.actionRule, input.attack);
    const weaponDamage = this.selfDamage(strength, outcome.size_delta);
    const overview = characterOverviewService.build(input.version, input.rules);
    const hooks = damageTypeHooksService.resolveDamageTypeHooks(outcome.damage_type_code, input.rules, input.mechanics);
    const result = attackDamageService.applyAttackDamage({
      weaponDamage,
      sr: 1,
      damageTypeCode: outcome.damage_type_code,
      defense: null,
      endurance: attackDamageService.enduranceValueOf(overview, input.rules),
      accumulatedDamage: attackDamageService.accumulatedDamageOf(input.version.states, input.rules),
      hooks,
      defenseIgnored: outcome.ignore_defense,
    });
    const woundStrength = Math.max(result.wound ?? 0, result.hpDamage);
    if (woundStrength <= 0) return;

    let state = woundInstanceService.addWound(woundStrength);
    if (state && outcome.ignore_defense) {
      state = woundInstanceService.setInternal(state, true);
    }
    if (state) {
      await this.resolveGameApi().addCombatState(input.gameId, input.actorKey, state);
    }
  }
}
