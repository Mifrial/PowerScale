/** Поле в снимке сохранения. */
export interface SceneSaveBoard {
  id: number;
  name: string;
  sortOrder: number;
  filePublicId: string | null;
  boundWidth: number;
  boundHeight: number;
  occupancyCell: number;
  zQuantum: number;
  gridStep: number;
}
