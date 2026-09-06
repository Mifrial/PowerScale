import type { RevisionFileProblem } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileProblem';

/**
 * Ошибка разбора или сборки файла ревизии: тесты цепляются за code/path/stage.
 */
export class RevisionFileProblemError extends Error {
  readonly code: string;
  readonly path: string;
  readonly stage: RevisionFileProblem['stage'];

  constructor(problem: RevisionFileProblem) {
    super(problem.message);
    this.name = 'RevisionFileProblemError';
    this.code = problem.code;
    this.path = problem.path;
    this.stage = problem.stage;
  }

  static is(value: unknown): value is RevisionFileProblemError {
    return value instanceof RevisionFileProblemError;
  }
}
