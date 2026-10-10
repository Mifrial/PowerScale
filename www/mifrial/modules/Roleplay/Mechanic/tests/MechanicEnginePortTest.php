<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Tests;

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Roleplay\Mechanic\Constant\PurchaseSurchargeEvent;
use Mifrial\Roleplay\Mechanic\Dto\CharacterMechanicContext;
use Mifrial\Roleplay\Mechanic\Dto\MechanicBinding;
use Mifrial\Roleplay\Mechanic\Dto\MechanicRecord;
use Mifrial\Roleplay\Mechanic\Dto\MechanicState;
use Mifrial\Roleplay\Mechanic\Dto\PurchaseSurchargePayload;
use Mifrial\Roleplay\Mechanic\Dto\ResolveActiveOptions;
use Mifrial\Roleplay\Mechanic\Dto\SurchargeItem;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine;
use Mifrial\Roleplay\Mechanic\Service\MechanicPortFactory;
use PHPUnit\Framework\TestCase;

final class MechanicEnginePortTest extends TestCase
{
    /**
     * Порт начисляет доплату purchase_surcharge на character.osSteps без регистрации хендлера снаружи.
     *
     * @return void
     */
    public function testPortChargesPurchaseSurchargeOnOsSteps(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $engine = $application->getLocator()->get(IMechanicContainer::class)->get(IMechanicEngine::class);
        self::assertInstanceOf(IMechanicEngine::class, $engine);

        $context = new CharacterMechanicContext(new MechanicState(
            ['a' => 1, 'b' => 1, 'c' => 1],
            ['a' => ['common'], 'b' => ['common'], 'c' => ['other']],
            [],
        ));
        $active = $engine->resolveActive(
            [new MechanicBinding('code-x', 4, new PurchaseSurchargePayload('common', null, 1, 2))],
            [MechanicRecord::fromNormalized([
                'id' => 4,
                'code' => 'purchase_surcharge',
                'name' => 'Механика',
                'description' => '',
                'handler_version' => '1.0.0',
            ])],
            new ResolveActiveOptions(),
        );
        $engine->runEvent(PurchaseSurchargeEvent::NAME, $context, $active);

        self::assertSame(2, $context->getOsSurchargeTotal());
        self::assertEquals([new SurchargeItem('b', 2)], $context->getSurchargeItems());
    }

    /**
     * Production port разрешает reliability_cut через catalog и registry.
     *
     * @return void
     */
    public function testPortResolvesProductionReliabilityCut(): void
    {
        $engine = (new MechanicPortFactory())->createEngine();
        self::assertInstanceOf(IMechanicEngine::class, $engine);

        self::assertTrue($engine->hasReliabilityCut(
            [new MechanicBinding('damage-type', 7, null)],
            [MechanicRecord::fromNormalized([
                'id' => 7,
                'code' => 'reliability_cut',
                'name' => 'Reliability cut',
                'description' => '',
                'handler_version' => '1.0.0',
            ])],
            new ResolveActiveOptions(),
        ));
    }
}
