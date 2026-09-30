/** Машиночитаемая причина, по которой choices/patch не прошли validation. */
export interface CharacterValidationProblem {
  code: string;
  message: string;
  path?: string;
  stage?: string;
}
