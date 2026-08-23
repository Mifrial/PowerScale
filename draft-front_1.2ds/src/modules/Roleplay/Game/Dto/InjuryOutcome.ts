export type InjuryHealUnit = 'days' | 'decades' | 'months' | 'years';

export interface InjuryHealRoll {
  diceCount: number;
  dieFaces: number;
  unit: InjuryHealUnit;
  rolls: number[];
  total: number;
}

export interface InjuryOutcome {
  strength: number;
  permanent: boolean;
  temporary: boolean;
  lethal: boolean;
  disfiguring: boolean;
  heal?: InjuryHealRoll;
}
