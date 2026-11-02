import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { ProcessSessionStep } from '@/modules/Roleplay/Game/Dto/ProcessSessionStep';
import type { ProcessSessionClosing } from '@/modules/Roleplay/Game/Dto/ProcessSessionClosing';
import type { ActionResolution } from '@/modules/Roleplay/Game/Dto/ActionResolution';

export interface ProcessSession {
  gameId: number;
  entityKey: CombatEntityKey;
  processRuleCode: string;
  currentStepCode: string;
  currentStepStatus: 'pending' | 'completed';
  status: 'active';
  startedAt: string;
  updatedAt: string;
  lastResolution?: ActionResolution;
  /** Счётчики сессии с сервера; в лист персонажа не попадают. */
  counters?: Record<string, number>;
  /** Доступные шаги, уже отфильтрованные сервером по счётчикам. */
  availableSteps?: ProcessSessionStep[];
  /** Открытое закрытие обычной атакой, если сервер его разрешил. */
  closing?: ProcessSessionClosing | null;
  /** Сколько раз экземпляр оружия уже завершил удар в этой сессии. */
  weaponUseCounts?: Record<string, number>;
  /** Последняя цель удара процесса; подсказка клиенту, проверка — на сервере. */
  lastStrikeTargetKey?: CombatEntityKey;
}
