import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
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

const SIZE_STEP = CHARACTERISTIC_BASE_RANGE.max - CHARACTERISTIC_BASE_RANGE.min + 1;

/**
 * Опция после удара: проверка, внутренний урон себе, снятие pending родителя.
 */
export class FuriousRushService {
  constructor(private readonly resolveGameApi: () => IGameApi) {}

  optionsOf(
    parentCode: string | null | undefined,
    abilities: CharacterAbility[],
    rules: Rule[],
  ): ReturnType<typeof actionEffectService.optionalAfterStrikeOptions> {
    return actionEffectService.optionalAfterStrikeOptions(
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

  async apply(input: {
    gameId: number;
    actorKey: CombatEntityKey;
    version: CharacterVersion;
    rules: Rule[];
    mechanics: Mechanic[];
    actionRule: Rule | null | undefined;
    attack: {
      itemRuleCode: string;
      profileType: 'strike' | 'throw' | 'shoot';
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
  }): Promise<{ skipPending: boolean; overlay: GameCombatOverlay | null }> {
    const option = this.optionsOf(input.actionRule?.code, input.version.abilities, input.rules).find((entry) =>
      input.selectedChildCodes.includes(entry.rule.code),
    );
    if (!option) return { skipPending: false, overlay: null };

    const characteristicCode =
      checkResolutionService.resolveCheckCharacteristicCode(option.effect.check_code, input.rules, 'willpower') ??
      'willpower';
    const value = stateRuntimeEffectsService
      .effectiveCharacteristicValues(input.version, input.rules)
      .get(characteristicCode) ?? {
      base: 3,
      size: 0,
    };
    const adv = stateRuntimeEffectsService.checkAdvantageFromStates(input.version, input.rules, {
      kind: 'characteristic',
      code: characteristicCode,
    });
    const roll = checkRollService.rollNamedCheck(
      checkRollService.namedCheckSpec(option.rule.name, value, adv, input.rules, input.actorKey),
      option.effect.check_code,
      { base: option.effect.difficulty, size: 0 },
      input.rng ?? Math.random,
      input.rules,
      input.mechanics,
    );
    const passed = roll.check?.passed === true;
    if (input.chatId !== null) {
      await input.sendMessage(
        '',
        [{ type: ROLL_ATTACHMENT_TYPE, payload: roll }],
        input.chatId,
        input.speaker,
      );
      await input.sendMessage(
        `${input.actorName} проходит проверку на ${option.rule.name} против ${option.effect.difficulty}.${
          passed ? ' Успех: без помехи действия.' : ' Провал: помеха действия остаётся.'
        }`,
        [],
        input.chatId,
        input.speaker,
      );
    }
    if (!passed) return { skipPending: false, overlay: null };

    const strength = this.strikeStrength(input.version, input.rules, input.actionRule, input.attack);
    const weaponDamage = this.selfDamage(strength, option.effect.self_damage.size_delta);
    const overview = characterOverviewService.build(input.version, input.rules);
    const hooks = damageTypeHooksService.resolveDamageTypeHooks(
      option.effect.self_damage.damage_type_code,
      input.rules,
      input.mechanics,
    );
    const result = attackDamageService.applyAttackDamage({
      weaponDamage,
      sr: 1,
      damageTypeCode: option.effect.self_damage.damage_type_code,
      defense: null,
      endurance: attackDamageService.enduranceValueOf(overview, input.rules),
      accumulatedDamage: attackDamageService.accumulatedDamageOf(input.version.states, input.rules),
      hooks,
      defenseIgnored: true,
    });
    const woundStrength = Math.max(result.wound ?? 0, result.hpDamage);
    let overlay: GameCombatOverlay | null = null;
    if (woundStrength > 0) {
      let state = woundInstanceService.addWound(woundStrength);
      if (state && option.effect.self_damage.internal) {
        state = woundInstanceService.setInternal(state, true);
      }
      if (state) {
        overlay = await this.resolveGameApi().addCombatState(input.gameId, input.actorKey, state);
      }
    }

    return { skipPending: option.effect.skip_parent_pending, overlay };
  }
}
