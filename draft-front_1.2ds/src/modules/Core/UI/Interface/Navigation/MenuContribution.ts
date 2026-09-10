export interface MenuContribution {
  id: string;
  apply: (actor: unknown) => void;
}
