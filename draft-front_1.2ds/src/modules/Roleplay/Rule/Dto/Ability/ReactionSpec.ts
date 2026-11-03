/** Событие, которое открывает реакцию. */
export type ReactionTrigger = 'strike';

/** Момент внутри события, в который реакция ещё доступна. */
export type ReactionWindow = 'before_defense';

/** Кто может объявить реакцию по отношению к участникам удара. */
export type ReactionPerformer = 'third_party' | 'defender' | 'any';

/**
 * Узел реакции: карточка сама говорит, когда её можно применить.
 * Эффект (что именно делает реакция) лежит в отдельном узле спеки.
 */
export interface ReactionSpec {
  trigger: ReactionTrigger;
  window: ReactionWindow;
  performer: ReactionPerformer;
}
