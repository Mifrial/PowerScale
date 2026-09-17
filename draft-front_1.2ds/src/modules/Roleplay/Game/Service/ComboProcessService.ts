import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { CombatActionOption } from '@/modules/Roleplay/Game/Utils/combatActions';
import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';
import type { ProcessSpec } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessSpec';
import type { ProcessStep } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessStep';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { COMBO_CLOSE_CAPS } from '@/modules/Roleplay/Game/Constant/Process/COMBO_CLOSE_CAPS';
import { COMBO_STEP_CODES } from '@/modules/Roleplay/Game/Constant/Process/COMBO_STEP_CODES';
import { processSessionService } from '@/modules/Roleplay/Game/Service/Instance/processSessionService';
import { ADVANTAGE_SOURCE_ACTION } from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';
import { actionRefEquals } from '@/modules/Roleplay/Game/Utils/combatActions';

/** Комбо на сессии процесса: шаги, пакет попадания, закрытие чужой атакой. */
export class ComboProcessService {
  isComboSpec(spec: ProcessSpec | null | undefined): boolean {
    if (!spec) return false;
    const codes = new Set(spec.steps.map((step) => step.code));

    return codes.has(COMBO_STEP_CODES.start) && codes.has(COMBO_STEP_CODES.build) && codes.has(COMBO_STEP_CODES.finish);
  }

  comboCount(session: ProcessSession | null | undefined): number {
    return session?.comboCount ?? 0;
  }

  visibleSteps(spec: ProcessSpec, session: ProcessSession | null): ProcessStep[] {
    const startCode = spec.start_step_code ?? spec.steps[0]?.code;
    if (!session) {
      return spec.steps.filter((step) => step.code === startCode);
    }
    if (session.currentStepStatus === 'pending') {
      return spec.steps.filter((step) => step.code === session.currentStepCode);
    }
    const available = processSessionService.availableSteps(spec, session.currentStepCode);
    const pending =
      session.currentStepStatus === 'pending'
        ? spec.steps.find((step) => step.code === session.currentStepCode)
        : undefined;
    const merged =
      pending && !available.some((step) => step.code === pending.code) ? [pending, ...available] : available;
    const combo = this.comboCount(session);
    if (!this.isComboSpec(spec) || combo >= 2) return merged;

    return merged.filter((step) => step.code !== COMBO_STEP_CODES.finish);
  }

  strikePackage(
    session: ProcessSession,
    spec: ProcessSpec,
    stepCode: string,
    comboClose = false,
  ): { modifiers: AdvantageModifier[]; extraSuccessCount: number } {
    if (!this.isComboSpec(spec)) return { modifiers: [], extraSuccessCount: 0 };
    const combo = this.comboCount(session);
    const closing = comboClose || stepCode === COMBO_STEP_CODES.finish;
    let advantageDelta = 0;
    let extraSuccessCount = 0;
    if (closing) {
      advantageDelta = Math.min(combo, COMBO_CLOSE_CAPS.advantage);
      extraSuccessCount = Math.min(Math.floor(combo / 2), COMBO_CLOSE_CAPS.extraSuccesses);
    } else if (stepCode === COMBO_STEP_CODES.start) {
      advantageDelta = -1;
    } else if (stepCode === COMBO_STEP_CODES.build) {
      advantageDelta = -combo;
    }
    if (!advantageDelta && !extraSuccessCount) return { modifiers: [], extraSuccessCount: 0 };

    return {
      extraSuccessCount,
      modifiers: advantageDelta
        ? [{ source_code: ADVANTAGE_SOURCE_ACTION, source_label: 'Действие', delta: advantageDelta }]
        : [],
    };
  }

  resolveAfterStrike(
    session: ProcessSession,
    spec: ProcessSpec,
    stepCode: string,
    successful: boolean,
    comboClose = false,
  ): ProcessSession | null {
    if (comboClose || (this.isComboSpec(spec) && stepCode === COMBO_STEP_CODES.finish)) {
      return null;
    }
    const next = processSessionService.resolveStep(session, spec, stepCode, successful);
    if (!next || !this.isComboSpec(spec)) return next;
    if (stepCode === COMBO_STEP_CODES.start) {
      return { ...next, comboCount: 1, comboTargetKey: session.comboTargetKey };
    }
    if (stepCode === COMBO_STEP_CODES.build) {
      return { ...next, comboCount: this.comboCount(session) + 1, comboTargetKey: session.comboTargetKey };
    }

    return next;
  }

  hasOwnedCloser(overview: CharacterOverview | null, rules: Rule[], processRuleCode: string): boolean {
    const owned = new Set(overview?.abilities.map((ability) => ability.ruleCode) ?? []);

    return rules.some((rule) => {
      if (!owned.has(rule.code) || rule.type !== 'ability' || !rule.spec || !('parent_ability_code' in rule.spec)) {
        return false;
      }

      return rule.spec.parent_ability_code === processRuleCode;
    });
  }

  canCloseWithOtherAttack(
    session: ProcessSession | null,
    spec: ProcessSpec | null,
    overview: CharacterOverview | null,
    rules: Rule[],
  ): boolean {
    if (!session || !spec || !this.isComboSpec(spec) || this.comboCount(session) < 2) return false;

    return this.hasOwnedCloser(overview, rules, session.processRuleCode);
  }

  listSources(
    available: CombatActionOption[],
    session: ProcessSession | null,
    spec: ProcessSpec | null,
    overview: CharacterOverview | null,
    rules: Rule[],
  ): CombatActionOption[] {
    if (!session) return available;
    const processSources = available.filter((source) => actionRefEquals(source, session.processRuleCode, rules));
    if (!this.canCloseWithOtherAttack(session, spec, overview, rules)) return processSources;

    return [
      ...processSources,
      ...available.filter(
        (source) =>
          !actionRefEquals(source, session.processRuleCode, rules) && source.attackMode !== 'wide',
      ),
    ];
  }

  singleTargetError(session: ProcessSession | null, targetKeys: CombatEntityKey[]): string | null {
    const unique = [...new Set(targetKeys)];
    if (unique.length !== 1 || !unique[0]) {
      return 'Комбо можно вести только по одной цели';
    }
    if (session?.comboTargetKey && session.comboTargetKey !== unique[0]) {
      return 'Комбо уже ведётся по другой цели';
    }

    return null;
  }

  bindTarget(session: ProcessSession, targetKey: CombatEntityKey): ProcessSession {
    const mismatch = this.singleTargetError(session, [targetKey]);
    if (mismatch) throw new Error(mismatch);

    return { ...session, comboTargetKey: targetKey };
  }

  prepareStrike(session: ProcessSession, spec: ProcessSpec, targetKeys: CombatEntityKey[]): ProcessSession {
    if (!this.isComboSpec(spec)) return session;
    const mismatch = this.singleTargetError(session, targetKeys);
    if (mismatch) throw new Error(mismatch);

    return this.bindTarget(session, targetKeys[0]);
  }
}
