import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import { CONCENTRATION_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/CONCENTRATION_ABILITY_CODE';
import { CONCENTRATION_ADVANTAGE_LABEL } from '@/modules/Roleplay/Rule/Constant/Ability/CONCENTRATION_ADVANTAGE_LABEL';
import { PREDELNAYA_CONCENTRATION_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/PREDELNAYA_CONCENTRATION_ABILITY_CODE';
import { SOSREDOTOCHENIE_VOLI_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/SOSREDOTOCHENIE_VOLI_ABILITY_CODE';
import { DLITELNOE_NAPRYAZHENIE_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/DLITELNOE_NAPRYAZHENIE_ABILITY_CODE';
import { CONCENTRATION_DEFAULT_ACTION_TURNS } from '@/modules/Roleplay/Rule/Constant/Ability/CONCENTRATION_DEFAULT_ACTION_TURNS';
import { DLITELNOE_NAPRYAZHENIE_ACTION_TURNS } from '@/modules/Roleplay/Rule/Constant/Ability/DLITELNOE_NAPRYAZHENIE_ACTION_TURNS';
import { CONCENTRATION_RESOURCE_CODE } from '@/modules/Roleplay/Rule/Constant/Resource/CONCENTRATION_RESOURCE_CODE';
import { CHECK_EXHAUSTION_CODE, CHECK_WILLPOWER_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { checkResolutionService } from '@/modules/Roleplay/Rule/init';
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
    const values = stateRuntimeEffectsService.effectiveCharacteristicValues(version, rules);
    const minimum = new DimensionalNumber({ base: 5, size: 0 });
    for (const code of ['intellect', 'perception'] as const) {
      const value = values.get(code);
      if (value && CharacteristicNumber.from(value).compare(minimum) >= 0) return true;
    }

    return false;
  }

  tokenCurrent(version: CharacterVersion, overlay: GameCombatOverlay | null): number {
    const resource = effectiveResources(version, overlay).find((item) => item.ruleCode === CONCENTRATION_RESOURCE_CODE);

    return resource?.current.base ?? 0;
  }

  tokenLimit(version: CharacterVersion): number {
    const resource = version.resources.find((item) => item.ruleCode === CONCENTRATION_RESOURCE_CODE);

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
    if (this.tokenCurrent(version, overlay) < 1) return false;
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

    return Math.min(this.tokenCurrent(version, overlay), 1 + this.livePeakLevel(version, rules));
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
    amount = 1,
  ): Promise<GameCombatOverlay> {
    const resource = effectiveResources(version, overlay).find((item) => item.ruleCode === CONCENTRATION_RESOURCE_CODE);
    if (!resource) throw new Error('Нет жетонов концентрации');
    const spent = Math.max(0, Math.min(Math.floor(amount), resource.current.base));
    if (spent < 1) throw new Error('Нет жетонов концентрации');
    await api.setCombatResource(gameId, entityKey, CONCENTRATION_RESOURCE_CODE, {
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
  ): Promise<GameCombatOverlay | null> {
    if (!this.isAbilityOwned(version)) return overlay;
    const used = overlay?.concentrationUsedInCycle === true;
    const resource = version.resources.find((item) => item.ruleCode === CONCENTRATION_RESOURCE_CODE);
    if (!resource) return overlay;
    if (!used) {
      const limit = Math.max(0, resourceLimitBase(resource));
      await api.setCombatResource(gameId, entityKey, CONCENTRATION_RESOURCE_CODE, {
        base: limit,
        size: resource.current.size,
      });
    }

    return api.setCombatConcentrationUsedInCycle(gameId, entityKey, false);
  }

  private livePeakLevel(version: CharacterVersion, rules: Rule[]): number {
    const purchased =
      version.abilities.find((ability) => ability.ruleCode === PREDELNAYA_CONCENTRATION_ABILITY_CODE)?.level ?? 0;
    if (purchased >= 2 && this.hasIntellectOrPerception(version, rules, { base: 5, size: 2 })) return 2;
    if (purchased >= 1 && this.hasIntellectOrPerception(version, rules, { base: 5, size: 1 })) return 1;

    return 0;
  }

  private isWillFocusLive(version: CharacterVersion, rules: Rule[]): boolean {
    const purchased =
      version.abilities.find((ability) => ability.ruleCode === SOSREDOTOCHENIE_VOLI_ABILITY_CODE)?.level ?? 0;
    if (purchased < 1) return false;

    return this.meetsMinimum(version, rules, ['willpower'], { base: 5, size: 0 });
  }

  private isLongTensionLive(version: CharacterVersion, rules: Rule[]): boolean {
    const purchased =
      version.abilities.find((ability) => ability.ruleCode === DLITELNOE_NAPRYAZHENIE_ABILITY_CODE)?.level ?? 0;
    if (purchased < 1) return false;

    return this.hasIntellectOrPerception(version, rules, { base: 4, size: 1 });
  }

  private isWillpowerCheck(checkCode: string, rules: Rule[], characteristicOverride?: string | null): boolean {
    if (checkCode === CHECK_EXHAUSTION_CODE) return true;
    if (checkResolutionService.checkAncestorCodes(checkCode, rules).includes(CHECK_WILLPOWER_CODE)) return true;

    return (
      checkResolutionService.resolveCheckCharacteristicCode(checkCode, rules, characteristicOverride) === 'willpower'
    );
  }

  private hasIntellectOrPerception(
    version: CharacterVersion,
    rules: Rule[],
    minimum: { base: number; size: number },
  ): boolean {
    return this.meetsMinimum(version, rules, ['intellect', 'perception'], minimum);
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
