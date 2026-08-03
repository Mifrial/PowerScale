import type { ProcessSpec } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessSpec';
import type { ProcessTransition } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessTransition';
import type { ActionCost } from '@/modules/Roleplay/Rule/Dto/Ability/ActionCost';
import type { ResourceRef } from '@/modules/Roleplay/Rule/Dto/Ability/ResourceRef';

export class ProcessSpecService {
  createEmpty(): ProcessSpec {
    return {
      steps: [],
      start_step_code: undefined,
      transition: { mode: 'chain', max_shift: 1, direction: 'both' },
      failure: null,
    };
  }

  normalize(raw: ProcessSpec): ProcessSpec {
    const transition: ProcessTransition = raw.transition ?? { mode: 'chain', max_shift: 1, direction: 'both' };
    if (transition.mode === 'chain' && transition.max_shift === undefined) {
      return { ...raw, transition: { ...transition, max_shift: 1, direction: transition.direction ?? 'both' } };
    }

    return { ...raw, steps: raw.steps ?? [], failure: raw.failure ?? null };
  }

  addStep(spec: ProcessSpec): ProcessSpec {
    return {
      ...spec,
      steps: [
        ...spec.steps,
        {
          code: `step-${spec.steps.length + 1}`,
          name: '',
          description: '',
          costs: [{ resource_code: 'action-points', amount: 1 }],
        },
      ],
    };
  }

  removeStep(spec: ProcessSpec, index: number): ProcessSpec {
    return { ...spec, steps: spec.steps.filter((_, i) => i !== index) };
  }

  patchStep(spec: ProcessSpec, index: number, key: string, value: unknown): ProcessSpec {
    const steps = spec.steps.map((s, i) => (i === index ? { ...s, [key]: value } : s));

    return { ...spec, steps };
  }

  addStepCost(spec: ProcessSpec, stepIndex: number): ProcessSpec {
    const steps = spec.steps.map((s, i) =>
      i === stepIndex ? { ...s, costs: [...s.costs, { resource_code: 'action-points', amount: 1 }] } : s,
    );

    return { ...spec, steps };
  }

  patchStepCost(spec: ProcessSpec, stepIndex: number, costIndex: number, key: string, value: unknown): ProcessSpec {
    const steps = spec.steps.map((s, i) => {
      if (i !== stepIndex) return s;
      const costs = s.costs.map((c, j) => (j === costIndex ? { ...c, [key]: value } : c));

      return { ...s, costs };
    });

    return { ...spec, steps };
  }

  removeStepCost(spec: ProcessSpec, stepIndex: number, costIndex: number): ProcessSpec {
    if (this.isMandatoryCost(spec.steps[stepIndex]?.costs ?? [], costIndex)) return spec;
    const steps = spec.steps.map((s, i) => {
      if (i !== stepIndex) return s;
      const costs = s.costs.filter((_, j) => j !== costIndex);

      return { ...s, costs };
    });

    return { ...spec, steps };
  }

  updateTransitionMode(spec: ProcessSpec, mode: string): ProcessSpec {
    let transition: ProcessTransition;
    if (mode === 'chain') {
      transition = { mode: 'chain', max_shift: 1, direction: 'both' };
    } else if (mode === 'free') {
      transition = { mode: 'free' };
    } else {
      transition = { mode: 'custom', edges: [] };
    }

    return { ...spec, transition };
  }

  patchTransition(spec: ProcessSpec, key: string, value: unknown): ProcessSpec {
    if (spec.transition.mode !== 'chain') return spec;

    return { ...spec, transition: { ...spec.transition, [key]: value } };
  }

  patchEdge(spec: ProcessSpec, edgeIndex: number, key: string, value: unknown): ProcessSpec {
    const t = spec.transition;
    if (t.mode !== 'custom') return spec;
    const edges = (t.edges ?? []).map((e, i) => (i === edgeIndex ? { ...e, [key]: value } : e));

    return { ...spec, transition: { mode: 'custom', edges } };
  }

  addEdge(spec: ProcessSpec): ProcessSpec {
    const t = spec.transition;
    if (t.mode !== 'custom') return spec;
    const edges = [...(t.edges ?? []), { from: '', to: '' }];

    return { ...spec, transition: { mode: 'custom', edges } };
  }

  removeEdge(spec: ProcessSpec, edgeIndex: number): ProcessSpec {
    const t = spec.transition;
    if (t.mode !== 'custom') return spec;
    const edges = (t.edges ?? []).filter((_, i) => i !== edgeIndex);

    return { ...spec, transition: { mode: 'custom', edges } };
  }

  patchSpec(spec: ProcessSpec, key: string, value: unknown): ProcessSpec {
    return { ...spec, [key]: value };
  }

  isDimensionalCost(cost: ActionCost, resources: ResourceRef[]): boolean {
    if (!cost.resource_code) return false;

    return resources.find((r) => r.code === cost.resource_code)?.isDimensional ?? false;
  }

  isMandatoryCost(costs: ActionCost[], costIndex: number): boolean {
    const odIndex = costs.findIndex((c) => c.resource_code === 'action-points');

    return odIndex === costIndex;
  }
}

export const processSpecService = new ProcessSpecService();
