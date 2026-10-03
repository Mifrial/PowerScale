<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\CharacterOsSteps;
use Mifrial\Roleplay\Mechanic\Dto\MechanicRecord;
use Mifrial\Roleplay\Mechanic\Dto\SurchargeItem;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\TestCase;

final class CharacterOsStepsTest extends TestCase
{
    /**
     * Доплата purchase_surcharge попадает в аккумуляторы. Spec правила не читается.
     *
     * @return void
     */
    public function testOsStepsChargeSurchargeWithoutReadingSpec(): void
    {
        $mechanics = $this->createMock(IMechanics::class);
        $mechanics->expects(self::once())->method('get')->with(4)->willReturn(MechanicRecord::fromNormalized([
            'id' => 4,
            'code' => 'purchase_surcharge',
            'name' => 'Механика',
            'description' => '',
            'handler_version' => '1.0.0',
        ]));
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $engine = $application->getLocator()->get(IMechanicContainer::class)->get(IMechanicEngine::class);
        self::assertInstanceOf(IMechanicEngine::class, $engine);

        $grant = $this->resolved('grant', ['grants' => ['money' => 10], 'budgets' => ['os' => 1]], [], []);
        self::assertTrue($grant->isSpecBroken());
        self::assertSame([], $grant->getMechanics());

        $context = (new CharacterOsSteps($engine, $mechanics))->runOsSteps(
            new CharacterRuleSlice(5, 2, 'world', [
                $this->resolved('a', [], ['common'], [[
                    'mechanic_id' => 4,
                    'mechanic_payload' => [
                        'type' => 'purchase_surcharge',
                        'filter' => ['keyword_code' => 'common'],
                        'free_count' => 1,
                        'surcharge' => 2,
                    ],
                ]]),
                $this->resolved('b', [], ['common'], []),
                $this->resolved('c', [], ['other'], []),
                $grant,
            ], []),
            ['a' => 1, 'b' => 1, 'c' => 1],
            [],
        );

        self::assertSame(2, $context->getOsSurchargeTotal());
        self::assertEquals([new SurchargeItem('b', 2)], $context->getSurchargeItems());
        self::assertSame(['common'], $context->getState()->getAbilityKeywords()['a']);
        self::assertArrayNotHasKey('grant', $context->getState()->getAbilityLevels());
    }

    /**
     * Живое правило с уже разрешёнными кодами признаков.
     *
     * @param string $code Ключ.
     * @param array<string|int, mixed> $spec Spec, шаг его не читает.
     * @param array<int, string> $keywordCodes Признаки.
     * @param array<int, array<string, mixed>> $mechanics Строки механик.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function resolved(string $code, array $spec, array $keywordCodes, array $mechanics): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(
                10,
                1,
                $code,
                true,
                'ability',
                'Name',
                '',
                $spec,
                [],
                $mechanics,
                'needs_work',
                '',
                DateTime::now(),
            ),
            $keywordCodes,
        );
    }
}
