import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import { CONCENTRATION_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/CONCENTRATION_ABILITY_CODE';
import { CONCENTRATION_ADVANTAGE_LABEL } from '@/modules/Roleplay/Rule/Constant/Ability/CONCENTRATION_ADVANTAGE_LABEL';
import { CONCENTRATION_DEFAULT_ACTION_TURNS } from '@/modules/Roleplay/Rule/Constant/Ability/CONCENTRATION_DEFAULT_ACTION_TURNS';
import { DLITELNOE_NAPRYAZHENIE_ACTION_TURNS } from '@/modules/Roleplay/Rule/Constant/Ability/DLITELNOE_NAPRYAZHENIE_ACTION_TURNS';
import type { ResourceSpec } from '@/modules/Roleplay/Rule/Dto/ResourceSpec';
import { checkResolutionService } from '@/modules/Roleplay/Rule/init';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import { stateRuntimeEffectsService } from '@/modules/Roleplay/Character/init';
import { CharacteristicNumber } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { effectiveResources, resourceLimitBase } from '@/modules/Roleplay/Game/Utils/combatEffectiveState';

/** Жетоны концентрации в бою: live-требование, трата на проверку, условный refill за свой ход. */
export class ConcentrationTokenService {
  isAbilityOwned(version: CharacterVersion | null | undefined): boolean {
    return (version?.abilities ?? []).some(
      (ability) => ability.ruleCode === CONCENTRATION_ABILITY_CODE && ability.level >= 1,
    );
  }

  isAbilityLive(version: CharacterVersion | null | undefined, rules: Rule[]): boolean {
    if (!version || !this.isAbilityOwned(version)) return false;
    return this.meetsMinimum(version, rules, attackDamageService.concentrationThresholdCodes(rules), {
      base: 5,
      size: 0,
    });
  }

  private tokenRule(rules: Rule[]): Rule | null {
    return (
      rules.find((item) => {
        if (item.type !== 'resource') return false;
        const spec = item.spec as ResourceSpec | undefined;

        return spec?.check_token === true;
      }) ?? null
    );
  }

  tokenCurrent(version: CharacterVersion, overlay: GameCombatOverlay | null, rules: Rule[]): number {
    const rule = this.tokenRule(rules);
    const resource = rule
      ? effectiveResources(version, overlay).find((item) => item.ruleCode === rule.code)
      : undefined;

    return resource?.current.base ?? 0;
  }

  tokenLimit(version: CharacterVersion, rules: Rule[]): number {
    const rule = this.tokenRule(rules);
    const resource = rule ? version.resources.find((item) => item.ruleCode === rule.code) : undefined;

    return resource ? Math.max(0, resourceLimitBase(resource)) : 0;
  }

  canSpend(
    version: CharacterVersion | null | undefined,
    overlay: GameCombatOverlay | null,
    rules: Rule[],
    checkCode: string,
    characteristicOverride?: string | null,
    actionTurns = CONCENTRATION_DEFAULT_ACTION_TURNS,
  ): boolean {
    if (!version || !this.isAbilityLive(version, rules)) return false;
    if (this.tokenCurrent(version, overlay, rules) < 1) return false;
    if (actionTurns > this.maxActionTurns(version, rules)) return false;
    if (checkResolutionService.isConcentrationTokenCheck(checkCode, rules, characteristicOverride)) return true;

    return this.isWillFocusLive(version, rules) && this.isWillpowerCheck(checkCode, rules, characteristicOverride);
  }

  maxActionTurns(version: CharacterVersion | null | undefined, rules: Rule[]): number {
    if (!version || !this.isLongTensionLive(version, rules)) return CONCENTRATION_DEFAULT_ACTION_TURNS;

    return DLITELNOE_NAPRYAZHENIE_ACTION_TURNS;
  }

  /** Без улучшения — 1; Предельная 1 — 2; Предельная 2 — 3. Не выше текущего запаса. */
  maxSpend(
    version: CharacterVersion | null | undefined,
    overlay: GameCombatOverlay | null,
    rules: Rule[],
    checkCode: string,
    characteristicOverride?: string | null,
    actionTurns = CONCENTRATION_DEFAULT_ACTION_TURNS,
  ): number {
    if (!this.canSpend(version, overlay, rules, checkCode, characteristicOverride, actionTurns) || !version) {
      return 0;
    }

    return Math.min(this.tokenCurrent(version, overlay, rules), 1 + this.livePeakLevel(version, rules));
  }

  tokenAdvantage(amount = 1): AdvantageModifier {
    return {
      source_code: CONCENTRATION_ABILITY_CODE,
      source_label: CONCENTRATION_ADVANTAGE_LABEL,
      delta: amount,
    };
  }

  /** Оферта могла хранить boolean; true → 1. */
  parseSpendAmount(value: unknown): number {
    if (value === true) return 1;
    const n = Math.floor(Number(value));

    return Number.isFinite(n) && n > 0 ? n : 0;
  }

  async spendToken(
    api: IGameApi,
    gameId: number,
    entityKey: CombatEntityKey,
    version: CharacterVersion,
    overlay: GameCombatOverlay | null,
    rules: Rule[],
    amount = 1,
  ): Promise<GameCombatOverlay> {
    const rule = this.tokenRule(rules);
    const resource = rule
      ? effectiveResources(version, overlay).find((item) => item.ruleCode === rule.code)
      : undefined;
    if (!rule || !resource) throw new Error('Нет жетонов концентрации');
    const spent = Math.max(0, Math.min(Math.floor(amount), resource.current.base));
    if (spent < 1) throw new Error('Нет жетонов концентрации');
    await api.setCombatResource(gameId, entityKey, rule.code, {
      base: resource.current.base - spent,
      size: resource.current.size,
    });

    return api.setCombatConcentrationUsedInCycle(gameId, entityKey, true);
  }

  async refillIfUnused(
    api: IGameApi,
    gameId: number,
    entityKey: CombatEntityKey,
    version: CharacterVersion,
    overlay: GameCombatOverlay | null,
    rules: Rule[],
  ): Promise<GameCombatOverlay | null> {
    if (!this.isAbilityOwned(version)) return overlay;
    const used = overlay?.concentrationUsedInCycle === true;
    const rule = this.tokenRule(rules);
    const resource = rule ? version.resources.find((item) => item.ruleCode === rule.code) : undefined;
    if (!rule || !resource) return overlay;
    if (!used) {
      const limit = Math.max(0, resourceLimitBase(resource));
      await api.setCombatResource(gameId, entityKey, rule.code, {
        base: limit,
        size: resource.current.size,
      });
    }

    return api.setCombatConcentrationUsedInCycle(gameId, entityKey, false);
  }

  private livePeakLevel(version: CharacterVersion, rules: Rule[]): number {
    const purchased = this.purchasedLevel(version, rules, 'peak_concentration');
    if (purchased >= 2 && this.hasIntellectOrPerception(version, rules, { base: 5, size: 2 })) return 2;
    if (purchased >= 1 && this.hasIntellectOrPerception(version, rules, { base: 5, size: 1 })) return 1;

    return 0;
  }

  private isWillFocusLive(version: CharacterVersion, rules: Rule[]): boolean {
    const purchased = this.purchasedLevel(version, rules, 'will_focus');
    if (purchased < 1) return false;

    const code = attackDamageService.willpowerRule(rules)?.code;

    return this.meetsMinimum(version, rules, code ? [code] : [], { base: 5, size: 0 });
  }

  private isLongTensionLive(version: CharacterVersion, rules: Rule[]): boolean {
    const purchased = this.purchasedLevel(version, rules, 'long_tension');
    if (purchased < 1) return false;

    return this.hasIntellectOrPerception(version, rules, { base: 4, size: 1 });
  }

  private isWillpowerCheck(checkCode: string, rules: Rule[], characteristicOverride?: string | null): boolean {
    if (checkResolutionService.ancestorHasFlag(checkCode, rules, 'willpower')) return true;

    const code = attackDamageService.willpowerRule(rules)?.code;
    if (!code) return false;

    return checkResolutionService.resolveCheckCharacteristicCode(checkCode, rules, characteristicOverride) === code;
  }

  private purchasedLevel(
    version: CharacterVersion,
    rules: Rule[],
    flag: 'peak_concentration' | 'will_focus' | 'long_tension',
  ): number {
    const rule = rules.find((item) => {
      if (item.type !== 'ability') return false;
      const spec = item.spec as AbilitySpec | undefined;

      return spec?.[flag] === true;
    });
    if (!rule) return 0;

    return version.abilities.find((ability) => ability.ruleCode === rule.code)?.level ?? 0;
  }

  private hasIntellectOrPerception(
    version: CharacterVersion,
    rules: Rule[],
    minimum: { base: number; size: number },
  ): boolean {
    return this.meetsMinimum(version, rules, attackDamageService.concentrationThresholdCodes(rules), minimum);
  }

  private meetsMinimum(
    version: CharacterVersion,
    rules: Rule[],
    codes: readonly string[],
    minimum: { base: number; size: number },
  ): boolean {
    const values = stateRuntimeEffectsService.effectiveCharacteristicValues(version, rules);
    const floor = new DimensionalNumber(minimum);
    for (const code of codes) {
      const value = values.get(code);
      if (value && CharacteristicNumber.from(value).compare(floor) >= 0) return true;
    }

    return false;
  }
}
