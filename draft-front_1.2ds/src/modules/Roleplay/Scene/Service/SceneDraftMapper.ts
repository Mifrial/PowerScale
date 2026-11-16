import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneDetail } from '@/modules/Roleplay/Scene/Dto/SceneDetail';
import type { SceneDraft } from '@/modules/Roleplay/Scene/Dto/SceneDraft';
import type { SceneDraftElement } from '@/modules/Roleplay/Scene/Dto/SceneDraftElement';
import type { SceneDraftBoard } from '@/modules/Roleplay/Scene/Dto/SceneDraftBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneEnclosure } from '@/modules/Roleplay/Scene/Dto/SceneEnclosure';
import type { SceneSaveBoard } from '@/modules/Roleplay/Scene/Dto/SceneSaveBoard';
import type { SceneSaveElement } from '@/modules/Roleplay/Scene/Dto/SceneSaveElement';
import type { SceneSaveEnclosure } from '@/modules/Roleplay/Scene/Dto/SceneSaveEnclosure';
import type { SceneSaveSnapshot } from '@/modules/Roleplay/Scene/Dto/SceneSaveSnapshot';
import { sceneWallService } from '@/modules/Roleplay/Scene/Service/Instance/sceneWallService';

/**
 * Перевод между снимком API и черновиком редактора.
 *
 * Обратное преобразование не теряет файлы и вложения.
 */
export class SceneDraftMapper {
  /** Снимок API → черновик. */
  fromDetail(detail: SceneDetail): SceneDraft {
    const elements = detail.elements.map((element): SceneDraftElement => ({
      id: element.id,
      boardId: element.boardId,
      kind: element.kind,
      shape: element.shape,
      props: element.props,
      sortOrder: element.sortOrder,
      targetBoardId: element.targetBoardId,
      assetPublicId: element.asset?.publicId ?? null,
      assetContentBox: element.asset?.contentBox ?? null,
    }));
    // Привязки проёма к стене в снимке нет: она редакторская. Восстанавливаем
    // её по геометрии, иначе после перезагрузки проём перестал бы ехать за
    // стеной.
    const weldTolerance = Math.max(1, Math.round(detail.resolution / 64));
    for (const boardId of new Set(elements.map((element) => element.boardId))) {
      sceneWallService.weld(elements, boardId, weldTolerance);
    }

    sceneWallService.attachAll(elements);

    return {
      id: detail.id,
      version: detail.version,
      canEdit: detail.canEdit,
      activeBoardId: detail.boards[0]?.id ?? null,
      name: detail.name,
      description: detail.description,
      resolution: detail.resolution,
      schemaVersion: detail.schemaVersion,
      boards: detail.boards.map((board) => ({
        id: board.id,
        name: board.name,
        sortOrder: board.sortOrder,
        filePublicId: board.baseFile?.publicId ?? null,
        boundWidth: board.boundWidth,
        boundHeight: board.boundHeight,
        occupancyCell: board.occupancyCell,
        zQuantum: board.zQuantum,
        gridStep: board.gridStep,
        fileWidth: board.baseFile?.width ?? null,
        fileHeight: board.baseFile?.height ?? null,
      })),
      elements,
      enclosures: detail.enclosures.map((enclosure) => ({
        id: enclosure.id,
        parentBoardId: enclosure.parentBoardId,
        childBoardId: enclosure.childBoardId,
        footprint: enclosure.footprint,
        elevation: enclosure.elevation,
        label: enclosure.label,
        sortOrder: enclosure.sortOrder,
      })),
    };
  }

  /** Черновик → снимок `scene.save`. */
  toSnapshot(draft: SceneDraft): SceneSaveSnapshot {
    return {
      name: draft.name,
      description: draft.description,
      resolution: draft.resolution,
      schemaVersion: draft.schemaVersion,
      boards: draft.boards.map((board): SceneSaveBoard => ({
        id: board.id,
        name: board.name,
        sortOrder: board.sortOrder,
        filePublicId: board.filePublicId,
        boundWidth: board.boundWidth,
        boundHeight: board.boundHeight,
        occupancyCell: board.occupancyCell,
        zQuantum: board.zQuantum,
        gridStep: board.gridStep,
      })),
      elements: draft.elements.map((element): SceneSaveElement => {
        const assetPublicId = element.assetPublicId ?? null;
        // Привязка проёма к стене — редакторская: id временный и после
        // вставки строк сменится. При загрузке она восстанавливается по
        // геометрии, поэтому в снимок не попадает.
        const props = { ...element.props };
        delete props.wallId;
        delete props.offset;
        if (assetPublicId !== null) {
          props.assetPublicId = assetPublicId;
        }

        return {
          id: element.id,
          boardId: element.boardId,
          kind: element.kind,
          shape: element.shape,
          props,
          sortOrder: element.sortOrder,
          targetBoardId: element.targetBoardId,
        };
      }),
      enclosures: draft.enclosures.map((enclosure): SceneSaveEnclosure => ({ ...enclosure })),
    };
  }

  /** Поля черновика → модель рендера. */
  toViewBoards(boards: SceneDraftBoard[]): SceneBoard[] {
    return boards.map((board) => ({
      id: board.id,
      name: board.name,
      sortOrder: board.sortOrder,
      baseFile:
        board.filePublicId === null
          ? null
          : { publicId: board.filePublicId, width: board.fileWidth, height: board.fileHeight },
      boundWidth: board.boundWidth,
      boundHeight: board.boundHeight,
      occupancyCell: board.occupancyCell,
      zQuantum: board.zQuantum,
      gridStep: board.gridStep,
    }));
  }

  /** Элементы черновика → модель рендера. */
  toViewElements(elements: SceneDraftElement[]): SceneElement[] {
    return elements.map((element) => ({
      id: element.id,
      boardId: element.boardId,
      kind: element.kind,
      shape: element.shape,
      props: element.props,
      sortOrder: element.sortOrder,
      targetBoardId: element.targetBoardId,
      asset:
        element.assetPublicId === null
          ? null
          : { publicId: element.assetPublicId, contentBox: element.assetContentBox },
    }));
  }

  /** Вложения черновика → модель рендера. */
  toViewEnclosures(enclosures: SceneSaveEnclosure[]): SceneEnclosure[] {
    return enclosures.map((enclosure) => ({
      id: enclosure.id,
      parentBoardId: enclosure.parentBoardId,
      childBoardId: enclosure.childBoardId,
      footprint: enclosure.footprint,
      elevation: enclosure.elevation,
      label: enclosure.label,
      sortOrder: enclosure.sortOrder,
    }));
  }
}
