<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Character\Service\Read\CharacterSectionMask;
use Mifrial\Roleplay\Game\Service\ConflictSheetProjection;
use Mifrial\Roleplay\Game\Service\GameCharacterProjectionMask;
use Mifrial\Roleplay\Game\Service\GameNpcVisibility;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет, что resources подчиняется существующим visibility masks.
 */
final class ResourceVisibilityTest extends TestCase
{
    /**
     * Keeps resources only when the Character section is open.
     *
     * @return void
     */
    public function testCharacterSectionMaskControlsResources(): void
    {
        $mask = new CharacterSectionMask();
        $choices = ['money' => 1];
        $sheet = ['money' => 1, 'resources' => [['ruleCode' => 'ap', 'current' => 1]]];

        self::assertArrayNotHasKey('resources', $mask->apply($choices, $sheet, [])['sheet']);
        self::assertArrayHasKey('resources', $mask->apply($choices, $sheet, ['resources'])['sheet']);
    }

    /**
     * Keeps resources only in an open game section.
     *
     * @return void
     */
    public function testGameProjectionMasksControlResources(): void
    {
        $mask = new GameCharacterProjectionMask();
        $sheet = ['resources' => [['ruleCode' => 'ap', 'current' => 1]]];

        self::assertArrayNotHasKey(
            'resources',
            $mask->apply([], $sheet, [], false)['sheet'],
        );
        self::assertArrayHasKey(
            'resources',
            $mask->apply([], $sheet, ['resources'], false)['sheet'],
        );
    }

    /**
     * Keeps resources only in an open NPC section.
     *
     * @return void
     */
    public function testNpcVisibilityMasksControlResources(): void
    {
        $mask = new GameNpcVisibility();
        $version = ['choices' => [], 'sheet' => ['resources' => [['ruleCode' => 'ap', 'current' => 1]]]];
        $visibility = ['scope' => 'users', 'userIds' => [1], 'sections' => []];

        self::assertArrayNotHasKey('resources', $mask->mask($version, $visibility, false)['sheet']);
        self::assertArrayHasKey(
            'resources',
            $mask->mask($version, ['sections' => ['resources']], false)['sheet'],
        );
    }

    /**
     * Uses the same masks for conflict snapshots.
     *
     * @return void
     */
    public function testConflictProjectionDoesNotExposeHiddenResources(): void
    {
        $projection = new ConflictSheetProjection(
            new CharacterSectionMask(),
            new GameCharacterProjectionMask(),
            new GameNpcVisibility(),
        );
        $masked = $projection->projectGameCharacter(
            ['money' => 1],
            ['money' => 1, 'resources' => [['ruleCode' => 'ap', 'current' => 1]]],
            [],
            false,
        );

        self::assertArrayNotHasKey('money', $masked['choices']);
        self::assertArrayNotHasKey('money', $masked['sheet']);
        self::assertArrayNotHasKey('resources', $masked['sheet']);
    }
}
