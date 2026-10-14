import type { CreateGameData } from '@/modules/Roleplay/Game/Dto/CreateGameData';
import type { Game } from '@/modules/Roleplay/Game/Dto/Game';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import type { GameJoinPolicy } from '@/modules/Roleplay/Game/Enum/GameJoinPolicy';
import type { GameStatus } from '@/modules/Roleplay/Game/Enum/GameStatus';
import type { GameMember } from '@/modules/Roleplay/Game/Dto/GameMember';
import type { GameMemberRole } from '@/modules/Roleplay/Game/Enum/GameMemberRole';
import type { GameVisibility } from '@/modules/Roleplay/Game/Enum/GameVisibility';

/**
 * Тело и карточка PHP Game: ключи CreateGameInput, плоский JSON → GameDetail.
 * Теги PHP не принимает; ОЛ на игре не задаётся (только возраст) — в payload всегда null.
 */
export class GamePhpCardService {
  createBody(data: CreateGameData): {
    name: string;
    status: GameStatus;
    visibility: GameVisibility;
    joinPolicy: GameJoinPolicy;
    spaceId: number;
    rulesRevision: number;
    osPointsLimit: number | null;
    olPointsLimit: null;
    orPointsLimit: number | null;
    moneyLimit: number | null;
    shortDescription: string | null;
    description: string | null;
    spaceCode: string | null;
  } {
    return {
      name: data.name,
      status: data.status,
      visibility: data.visibility,
      joinPolicy: data.joinPolicy,
      spaceId: data.spaceId,
      rulesRevision: data.rulesRevision,
      osPointsLimit: data.osPointsLimit,
      olPointsLimit: null,
      orPointsLimit: data.orPointsLimit,
      moneyLimit: data.moneyLimit,
      shortDescription: data.shortDescription,
      description: data.description,
      spaceCode: data.spaceCode,
    };
  }

  updateBody(
    id: number,
    data: CreateGameData,
  ): {
    id: number;
    name: string;
    status: GameStatus;
    visibility: GameVisibility;
    joinPolicy: GameJoinPolicy;
    spaceId: number;
    rulesRevision: number;
    osPointsLimit: number | null;
    olPointsLimit: null;
    orPointsLimit: number | null;
    moneyLimit: number | null;
    shortDescription: string | null;
    description: string | null;
    spaceCode: string | null;
  } {
    return { id, ...this.createBody(data) };
  }

  detailOf(row: unknown): GameDetail {
    const record = this.recordOf(row);
    const game = this.gameOf(record);

    return {
      game,
      description: this.nullableStringOf(record.description),
      osPointsLimit: this.nullableIntOf(record.osPointsLimit),
      olPointsLimit: this.nullableIntOf(record.olPointsLimit),
      orPointsLimit: this.nullableIntOf(record.orPointsLimit),
      moneyLimit: this.nullableIntOf(record.moneyLimit),
      forbiddenTags: [],
      members: [],
      discussionChatId: game.discussionChatId,
      gameChatId: game.gameChatId,
    };
  }

  membersOf(rows: unknown): GameMember[] {
    if (!Array.isArray(rows)) return [];

    return rows.map((row) => this.memberOf(row));
  }

  memberOf(row: unknown): GameMember {
    const record = this.recordOf(row);
    const userId = this.intOf(record.userId);

    return {
      userId,
      userName: `#${userId}`,
      role: this.roleOf(record.role),
      permissions: [],
    };
  }

  listOf(rows: unknown): Game[] {
    if (!Array.isArray(rows)) return [];

    return rows.map((row) => this.gameOf(this.recordOf(row)));
  }

  private gameOf(record: Record<string, unknown>): Game {
    return {
      id: this.intOf(record.id),
      name: this.stringOf(record.name),
      shortDescription: this.nullableStringOf(record.shortDescription),
      status: this.statusOf(record.status),
      sessionRunning: record.sessionRunning === true,
      visibility: this.visibilityOf(record.visibility),
      joinPolicy: this.joinPolicyOf(record.joinPolicy),
      ownerId: this.intOf(record.ownerId),
      ownerName: typeof record.ownerName === 'string' ? record.ownerName : '',
      spaceId: this.intOf(record.spaceId),
      spaceCode: this.stringOf(record.spaceCode),
      rulesRevision: this.intOf(record.rulesRevision),
      memberCount: typeof record.memberCount === 'number' ? record.memberCount : 0,
      tags: [],
      gameChatId: this.nullableIntOf(record.gameChatId),
      discussionChatId: this.nullableIntOf(record.discussionChatId),
    };
  }

  private recordOf(value: unknown): Record<string, unknown> {
    if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
      return value as Record<string, unknown>;
    }

    return {};
  }

  private stringOf(value: unknown): string {
    return typeof value === 'string' ? value : '';
  }

  private nullableStringOf(value: unknown): string | null {
    if (value === null || value === undefined) return null;

    return typeof value === 'string' ? value : null;
  }

  private intOf(value: unknown): number {
    return typeof value === 'number' && Number.isFinite(value) ? value : 0;
  }

  private nullableIntOf(value: unknown): number | null {
    if (value === null || value === undefined) return null;

    return typeof value === 'number' && Number.isFinite(value) ? value : null;
  }

  private statusOf(value: unknown): GameStatus {
    if (
      value === 'draft' ||
      value === 'recruiting' ||
      value === 'in_process' ||
      value === 'paused' ||
      value === 'completed'
    ) {
      return value;
    }

    return 'draft';
  }

  private visibilityOf(value: unknown): GameVisibility {
    if (value === 'all' || value === 'friends' || value === 'players' || value === 'invited' || value === 'whitelist') {
      return value;
    }

    return 'all';
  }

  private roleOf(value: unknown): GameMemberRole {
    if (value === 'owner' || value === 'gm' || value === 'player') return value;

    return 'player';
  }

  private joinPolicyOf(value: unknown): GameJoinPolicy {
    if (value === 'anyone' || value === 'friends' || value === 'invite_only' || value === 'whitelist') {
      return value;
    }

    return 'anyone';
  }
}
