export interface IChatTab {
  key: string;
  label: string;
  icon: string;
  types: string[];
  sortOrder?: number;
  /** На вкладке только чаты, где текущий пользователь участник. */
  onlyIfMember?: boolean;
}
