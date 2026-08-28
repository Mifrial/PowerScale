import type { ActionCostAmount } from '@/modules/Roleplay/Rule/Dto/Ability/ActionCostAmount';

export type ActionCost = { resource_code: string; amount: ActionCostAmount; label?: string };
