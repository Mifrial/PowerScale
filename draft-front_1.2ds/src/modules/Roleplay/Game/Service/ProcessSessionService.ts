import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';
import type { ProcessSpec } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessSpec';
import type { ProcessStep } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessStep';
import type { ActionResolution } from '@/modules/Roleplay/Game/Dto/ActionResolution';

export class ProcessSessionService {
  start(gameId: number, entityKey: CombatEntityKey, processRuleId: string, spec: ProcessSpec): ProcessSession {
    const startStepCode = spec.start_step_code ?? spec.steps[0]?.code;
    if (!startStepCode || !spec.steps.some((step) => step.code === startStepCode)) {
      throw new Error('У процесса нет корректного входного шага');
    }
    const now = new Date().toISOString();

    return {
      gameId,
      entityKey,
      processRuleId,
      currentStepCode: startStepCode,
      currentStepStatus: 'pending',
      status: 'active',
      startedAt: now,
      updatedAt: now,
    };
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
}
