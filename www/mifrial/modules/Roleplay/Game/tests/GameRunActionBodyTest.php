<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Service\GameRunActionBody;
use PHPUnit\Framework\TestCase;

/**
 * Разбор конверта game.runAction.
 */
final class GameRunActionBodyTest extends TestCase
{
    /**
     * Объявление удара разбирается в строки.
     *
     * @return void
     */
    public function testStrikeDeclareIsNormalized(): void
    {
        $parsed = (new GameRunActionBody())->parse([
            'phase' => 'declare',
            'kind' => 'strike',
            'code' => 'swing',
            'actor' => ['type' => 'character', 'id' => 1],
            'context' => [
                'strikes' => [[
                    'itemInventoryId' => 5,
                    'itemRuleCode' => 'sword',
                    'profileType' => 'strike',
                    'profileIndex' => 0,
                    'target' => ['type' => 'npc', 'id' => 2],
                ]],
                'chosenAmounts' => ['action-points' => 1],
            ],
        ]);

        self::assertSame('declare', $parsed['phase']);
        self::assertSame('strike', $parsed['kind']);
        self::assertSame('swing', $parsed['code']);
        self::assertSame(['type' => 'character', 'id' => 1], $parsed['actor']);
        self::assertCount(1, $parsed['context']['strikes']);
        self::assertSame(0, $parsed['context']['strikes'][0]['instanceIndex']);
        self::assertSame(['type' => 'npc', 'id' => 2], $parsed['context']['strikes'][0]['target']);
        self::assertSame(['action-points' => 1], $parsed['context']['chosenAmounts']);
    }

    /**
     * Ответ не требует кода и актора.
     *
     * @return void
     */
    public function testStrikeRespondHasNoCode(): void
    {
        $parsed = (new GameRunActionBody())->parse([
            'phase' => 'respond',
            'kind' => 'strike',
            'context' => ['defenses' => [[
                'reaction' => 'ignore',
                'expectedSheetVersion' => 3,
            ]]],
        ]);

        self::assertNull($parsed['code']);
        self::assertNull($parsed['actor']);
        self::assertNull($parsed['context']['defenses'][0]['target']);
    }

    /**
     * Лишний ключ конверта отвергается.
     *
     * @return void
     */
    public function testUnknownEnvelopeKeyIsRejected(): void
    {
        $this->expectException(ActionException::class);

        (new GameRunActionBody())->parse([
            'phase' => 'declare',
            'kind' => 'action',
            'code' => 'wait',
            'actor' => ['type' => 'character', 'id' => 1],
            'context' => [
                'expectedSheetVersion' => 1,
                'followUpTargetKey' => null,
            ],
            'battleId' => 1,
        ]);
    }

    /**
     * Лишний ключ строки удара отвергается.
     *
     * @return void
     */
    public function testUnknownStrikeRowKeyIsRejected(): void
    {
        $this->expectException(ActionException::class);

        (new GameRunActionBody())->parse([
            'phase' => 'declare',
            'kind' => 'strike',
            'code' => 'swing',
            'actor' => ['type' => 'character', 'id' => 1],
            'context' => ['strikes' => [[
                'itemInventoryId' => 5,
                'itemRuleCode' => 'sword',
                'profileType' => 'strike',
                'profileIndex' => 0,
                'target' => ['type' => 'npc', 'id' => 2],
                'damage' => 3,
            ]]],
        ]);
    }

    /**
     * Неизвестный вид отвергается.
     *
     * @return void
     */
    public function testUnknownKindIsRejected(): void
    {
        $this->expectException(ActionException::class);

        (new GameRunActionBody())->parse([
            'phase' => 'declare',
            'kind' => 'spell',
            'code' => 'wait',
            'actor' => ['type' => 'character', 'id' => 1],
            'context' => [
                'expectedSheetVersion' => 1,
                'followUpTargetKey' => null,
            ],
        ]);
    }

    /**
     * Рана принимает пару kind/id.
     *
     * @return void
     */
    public function testWoundIsNormalized(): void
    {
        $parsed = (new GameRunActionBody())->parse([
            'phase' => 'declare',
            'kind' => 'wound',
            'code' => 'squeeze',
            'actor' => ['type' => 'character', 'id' => 1],
            'context' => [
                'operation' => 'hold',
                'target' => ['kind' => 'npc', 'id' => 2],
                'stateId' => 7,
                'expectedSheetVersion' => 4,
            ],
        ]);

        self::assertSame('wound', $parsed['kind']);
        self::assertSame(['kind' => 'npc', 'id' => 2], $parsed['context']['target']);
        self::assertSame('hold', $parsed['context']['operation']);
    }
}
