/** Правка узла в черновике. */
export interface MapNodeEdit {
  name: string;
  shortInfo: string;
  publicInfo: string;
  gmInfo: string;
  linkedMapId: number | null;
  icon: string;
}
