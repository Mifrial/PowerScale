import { describe, expect, it } from 'vitest';
import type { SceneActor } from '@/modules/Roleplay/Scene/Dto/SceneActor';
import { SceneActorService } from '@/modules/Roleplay/Scene/Service/SceneActorService';

/** Толщина обводки кольца из инструкций Pixi. */
const strokeWidth = (painted: ReturnType<SceneActorService['paint']>): number => {
  const instruction = painted.ring.context.instructions[0];

  return instruction.action === 'stroke' ? instruction.data.style.width : 0;
};

const actor = (overrides: Partial<SceneActor> = {}): SceneActor => ({
  id: 1,
  x: 10,
  y: 20,
  facing: 0,
  radius: 8,
  tint: 0xffffff,
  label: 'Тест',
  ...overrides,
});

describe('SceneActorService', () => {
  const service = new SceneActorService();

  it('собирает группу: тень, кольцо, стрелка и стендалка', () => {
    const painted = service.paint(actor(), 64, 1);

    expect(painted.node.children.length).toBe(4);
    expect(painted.node.position.x).toBe(10);
    expect(painted.node.position.y).toBe(20);
  });

  it('держит толщину кольца постоянной на экране', () => {
    const painted = service.paint(actor(), 64, 0.1);
    const thin = strokeWidth(painted);

    service.update(painted, actor(), 1);
    const thick = strokeWidth(painted);

    expect(thin).toBeCloseTo(20);
    expect(thick).toBeCloseTo(2);
  });

  it('поворачивает стрелку по facing', () => {
    const painted = service.paint(actor(), 64, 1);
    service.update(painted, actor({ facing: 90 }), 1);

    expect(painted.arrow.rotation).toBeCloseTo(Math.PI / 2);
  });

  it('отражает стендалку, когда актор смотрит влево', () => {
    const painted = service.paint(actor(), 64, 1);
    service.update(painted, actor({ facing: 180 }), 1);

    expect(painted.sprite.scale.x).toBeLessThan(0);
    service.update(painted, actor({ facing: 0 }), 1);
    expect(painted.sprite.scale.x).toBeGreaterThan(0);
  });
});
