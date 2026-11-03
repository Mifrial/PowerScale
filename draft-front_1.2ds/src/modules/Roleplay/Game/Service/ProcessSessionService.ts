import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CombatActionOption } from '@/modules/Roleplay/Game/Utils/combatActions';
import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';
import type { ProcessStrikePackage } from '@/modules/Roleplay/Game/Dto/ProcessStrikePackage';
import type { ProcessSpec } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessSpec';
import type { ProcessStep } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessStep';
import type { ProcessFormula } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessFormula';
import type { ActionResolution } from '@/modules/Roleplay/Game/Dto/ActionResolution';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import { ADVANTAGE_SOURCE_CIRCUMSTANCES } from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';
import { actionRefEquals } from '@/modules/Roleplay/Game/Utils/combatActions';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';

export class ProcessSessionService {
  start(gameId: number, entityKey: CombatEntityKey, processRuleCode: string, spec: ProcessSpec): ProcessSession {
    const startStepCode = spec.start_step_code ?? spec.steps[0]?.code;
    if (!startStepCode || !spec.steps.some((step) => step.code === startStepCode)) {
      throw new Error('У процесса нет корректного входного шага');
    }
    const now = new Date().toISOString();

    return {
      gameId,
      entityKey,
      processRuleCode,
      currentStepCode: startStepCode,
      currentStepStatus: 'pending',
      status: 'active',
      startedAt: now,
      updatedAt: now,
      counters: this.initialCounters(spec),
    };
  }

  /**
   * Шаги, которые можно объявить прямо сейчас.
   *
   * Для открытой сессии берутся готовые шаги сервера (счётчики и переходы уже
   * учтены); без сессии — только стартовый шаг карточки.
   *
   * @param spec Спека процесса.
   * @param session Открытая сессия или null.
   *
   * @returns Шаги для диалога объявления.
   */
  /**
   * Источники атаки при открытом процессе.
   *
   * Без закрытия доступна только карточка процесса; с закрытием — ещё обычные
   * не-широкие атаки: сервер сам решит, закрывает ли атака процесс.
   *
   * @param available Все источники атаки.
   * @param session Открытая сессия или null.
   * @param rules Правила ревизии.
   *
   * @returns Отфильтрованные источники.
   */
  launchSources(
    available: CombatActionOption[],
    session: ProcessSession | null | undefined,
    rules: Rule[],
  ): CombatActionOption[] {
    if (!session) return available;
    const processSources = available.filter((source) => actionRefEquals(source, session.processRuleCode, rules));
    if (!session.closing) return processSources;

    return [
      ...processSources,
      ...available.filter(
        (source) => !actionRefEquals(source, session.processRuleCode, rules) && source.attackMode !== 'wide',
      ),
    ];
  }

  visibleSteps(spec: ProcessSpec, session: ProcessSession | null | undefined): ProcessStep[] {
    if (session?.availableSteps) {
      return session.availableSteps.map((step) => ({
        code: step.code,
        name: step.name,
        description: step.description,
        costs: [{ resource_code: 'action-points', amount: step.actionPoints }],
        interruption: { mode: 'normal' },
        execution: { type: step.execution, action_code: step.actionCode ?? null },
      }));
    }

    const startStepCode = spec.start_step_code ?? spec.steps[0]?.code;

    return spec.steps.filter((step) => step.code === startStepCode);
  }

  /**
   * Пакет удара шага с готовыми числами.
   *
   * Для открытой сессии числа приходят с сервера; для стартового шага до
   * первого удара формулы считаются по объявленным счётчикам. Это подсказка
   * для диалога — окончательные числа всё равно считает сервер.
   *
   * @param spec Спека процесса.
   * @param session Открытая сессия или null.
   * @param stepCode Код шага.
   *
   * @returns Пакет удара; нули без пакета.
   */
  strikePreview(spec: ProcessSpec, session: ProcessSession | null | undefined, stepCode: string): ProcessStrikePackage {
    const empty = { advantage: 0, extraSuccesses: 0 };
    const serverStep = session?.availableSteps?.find((step) => step.code === stepCode);
    if (serverStep) {
      return serverStep.strike ?? empty;
    }

    const step = spec.steps.find((entry) => entry.code === stepCode);
    const strike = step?.strike;
    if (!strike) return empty;
    const counters = session?.counters ?? this.initialCounters(spec);

    return {
      advantage: this.formulaValue(strike.advantage, counters),
      extraSuccesses: this.formulaValue(strike.extra_successes, counters),
    };
  }

  /**
   * Стартовые счётчики карточки процесса.
   *
   * @param spec Спека процесса.
   *
   * @returns Карта код → значение.
   */
  initialCounters(spec: ProcessSpec): Record<string, number> {
    const counters: Record<string, number> = {};
    for (const counter of spec.counters ?? []) {
      counters[counter.counter_code] = counter.initial ?? 0;
    }

    return counters;
  }

  /**
   * Значение формулы процесса по счётчикам; неизвестный счётчик — 0.
   *
   * @param formula Формула.
   * @param counters Счётчики сессии.
   *
   * @returns Число в границах формулы.
   */
  private formulaValue(formula: ProcessFormula | undefined, counters: Record<string, number>): number {
    if (!formula) return 0;
    if (formula.type === 'fixed') return this.clampFormula(formula.value, formula);

    const counter = counters[formula.counter_code] ?? 0;
    const divisor = Math.max(1, formula.divisor ?? 1);
    const value = Math.floor((counter * (formula.factor ?? 1)) / divisor);

    return this.clampFormula(value, formula);
  }

  /**
   * Сначала нижняя граница, затем верхняя.
   *
   * @param value Значение.
   * @param formula Формула с границами.
   *
   * @returns Значение в границах.
   */
  private clampFormula(value: number, formula: ProcessFormula): number {
    let result = value;
    if (formula.type === 'counter' && formula.min_value !== undefined) {
      result = Math.max(formula.min_value, result);
    }
    if (formula.type === 'counter' && formula.max_value !== undefined) {
      result = Math.min(formula.max_value, result);
    }

    return result;
  }

  availableSteps(spec: ProcessSpec, currentStepCode: string): ProcessStep[] {
    const stepsByCode = new Map(spec.steps.map((step) => [step.code, step]));
    if (!stepsByCode.has(currentStepCode)) return [];
    const transition = spec.transition;
    let codes: string[];
    if (transition.mode === 'free') {
      codes = spec.steps.map((step) => step.code);
    } else if (transition.mode === 'custom') {
      codes = transition.edges.filter((edge) => edge.from === currentStepCode).map((edge) => edge.to);
    } else {
      const index = spec.steps.findIndex((step) => step.code === currentStepCode);
      const radius = Math.max(0, transition.max_shift);
      const direction = transition.direction ?? 'both';
      codes = spec.steps
        .map((step, stepIndex) => ({ code: step.code, shift: stepIndex - index }))
        .filter((entry) => Math.abs(entry.shift) <= radius && (direction === 'both' || entry.shift >= 0))
        .map((entry) => entry.code);
    }

    return [...new Set(codes)].flatMap((code) => {
      const step = stepsByCode.get(code);

      return step ? [step] : [];
    });
  }

  canExit(spec: ProcessSpec, currentStepCode: string): boolean {
    return spec.exit_step_codes === undefined || spec.exit_step_codes.includes(currentStepCode);
  }

  canInterruptNormally(spec: ProcessSpec, currentStepCode: string): boolean {
    return this.interruptionFor(spec, currentStepCode)?.mode === 'normal';
  }

  /** Обычное прерывание: отказ или эффекты завершения. Текст чата задаёт вызывающий. */
  planNormalInterrupt(
    spec: ProcessSpec | null,
    currentStepCode: string,
    rule: Rule | null,
  ): { allowed: false } | { allowed: true; completionEffects: PendingActionEffect[] } {
    if (!spec || !rule || !this.canInterruptNormally(spec, currentStepCode)) return { allowed: false };

    return { allowed: true, completionEffects: actionEffectService.effectsAfterProcess(rule) };
  }

  canInterruptEmergency(spec: ProcessSpec, currentStepCode: string): boolean {
    const interruption = this.interruptionFor(spec, currentStepCode);

    return interruption?.mode === 'emergency' && Boolean(interruption.effects?.length);
  }

  private interruptionFor(
    spec: ProcessSpec,
    currentStepCode: string,
  ): ProcessSpec['steps'][number]['interruption'] | null {
    return spec.steps.find((step) => step.code === currentStepCode)?.interruption ?? null;
  }

  stepCost(step: ProcessStep, resourceCode: string): number {
    return step.costs
      .filter((cost) => cost.resource_code === resourceCode)
      .reduce((total, cost) => total + (typeof cost.amount === 'number' ? cost.amount : cost.amount.base), 0);
  }

  /** Режим шага с умолчанием старых ревизий: нет execution — operations значит none, иначе strike. */
  executionOf(step: ProcessStep): 'strike' | 'action' | 'none' {
    if (step.execution) return step.execution.type;

    return 'operations' in step ? 'none' : 'strike';
  }

  recordResolution(session: ProcessSession, resolution: ActionResolution): ProcessSession {
    return { ...session, lastResolution: structuredClone(resolution), updatedAt: new Date().toISOString() };
  }

  resolveStep(
    session: ProcessSession,
    spec: ProcessSpec,
    stepCode: string,
    successful: boolean,
  ): ProcessSession | null {
    const startStepCode = spec.start_step_code ?? spec.steps[0]?.code;
    const isInitialStep = session.currentStepStatus === 'pending' && stepCode === startStepCode;
    if (!isInitialStep && !this.availableSteps(spec, session.currentStepCode).some((step) => step.code === stepCode)) {
      throw new Error('Выбранный шаг недоступен из текущего шага');
    }
    if (!successful) {
      if (spec.failure === 'restart_from_first') {
        const start = spec.start_step_code ?? spec.steps[0]?.code;
        if (!start) return null;

        return {
          ...session,
          currentStepCode: start,
          currentStepStatus: 'completed',
          updatedAt: new Date().toISOString(),
        };
      }

      return null;
    }

    return {
      ...session,
      currentStepCode: stepCode,
      currentStepStatus: 'completed',
      updatedAt: new Date().toISOString(),
    };
  }

  recordStrikeUses(
    session: ProcessSession,
    spec: ProcessSpec | null | undefined,
    weaponKeys: string[],
    targetKey: CombatEntityKey | null,
  ): ProcessSession {
    const counts = { ...(session.weaponUseCounts ?? {}) };
    if (spec?.repeat_weapon_circumstance) {
      for (const weaponKey of weaponKeys) {
        counts[weaponKey] = (counts[weaponKey] ?? 0) + 1;
      }
    }

    return {
      ...session,
      weaponUseCounts: spec?.repeat_weapon_circumstance ? counts : session.weaponUseCounts,
      lastStrikeTargetKey: targetKey ?? session.lastStrikeTargetKey,
      updatedAt: new Date().toISOString(),
    };
  }

  priorWeaponUses(session: ProcessSession | null | undefined, weaponKey: string): number {
    return session?.weaponUseCounts?.[weaponKey] ?? 0;
  }

  repeatWeaponModifiers(
    session: ProcessSession | null | undefined,
    spec: ProcessSpec | null | undefined,
    weaponKey: string,
  ): AdvantageModifier[] {
    if (!spec?.repeat_weapon_circumstance) return [];
    const prior = this.priorWeaponUses(session, weaponKey);
    if (prior <= 0) return [];

    return [{ source_code: ADVANTAGE_SOURCE_CIRCUMSTANCES, source_label: 'Обстоятельства', delta: -prior }];
  }
}
