import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { SheetSection } from '@/modules/Roleplay/Character/Enum/SheetSection';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

interface GameRuntimeEntityProjectionBase {
  entityKey: CombatEntityKey;
  kind: 'character' | 'npc';
  id: number;
  actualVersion: number;
  actualSpaceCode: string | null;
  actualRulesRevision: number | null;
  source: 'characterActual' | 'npcActual';
  summary: {
    name: string;
    shortDescription: string | null;
  };
}

interface GameRuntimeEntitySummaryProjection extends GameRuntimeEntityProjectionBase {
  projectionLevel: 'summary';
  version: null;
  visibleSections: SheetSection[];
}

interface GameRuntimeEntityFullProjection extends GameRuntimeEntityProjectionBase {
  projectionLevel: 'full';
  version: CharacterVersion | null;
  visibleSections: SheetSection[];
}

/** Visibility-safe Game projection; summary никогда не содержит полный лист. */
export type GameRuntimeEntityProjection = GameRuntimeEntitySummaryProjection | GameRuntimeEntityFullProjection;
