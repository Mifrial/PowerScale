<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Tests;

use Mifrial\Roleplay\Mechanic\Constant\PurchaseSurchargeEvent;
use Mifrial\Roleplay\Mechanic\Dto\CharacterMechanicContext;
use Mifrial\Roleplay\Mechanic\Dto\MechanicBinding;
use Mifrial\Roleplay\Mechanic\Dto\MechanicRecord;
use Mifrial\Roleplay\Mechanic\Dto\MechanicState;
use Mifrial\Roleplay\Mechanic\Dto\PurchaseSurchargePayload;
use Mifrial\Roleplay\Mechanic\Dto\ResolveActiveOptions;
use Mifrial\Roleplay\Mechanic\Dto\SurchargeItem;
use Mifrial\Roleplay\Mechanic\Exception\MechanicInvalidException;
use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;
use Mifrial\Roleplay\Mechanic\Interface\IReliabilityCut;
use Mifrial\Roleplay\Mechanic\Interface\MechanicPayload;
use Mifrial\Roleplay\Mechanic\Service\Handler\PurchaseSurchargeHandler;
use Mifrial\Roleplay\Mechanic\Service\MechanicEngine;
use Mifrial\Roleplay\Mechanic\Service\MechanicHandlerRegistry;
use PHPUnit\Framework\TestCase;

final class MechanicEngineTest extends TestCase
{
    /**
     * resolveActive резолвит механики через каталог (mechanicId → code@version).
     *
     * @return void
     */
    public function testResolveActiveMapsMechanicIdToCodeVersion(): void
    {
        $registry = new MechanicHandlerRegistry();
        $registry->register($this->handler('m', '1.0.0', ['ev' => 0]));
        $engine = new MechanicEngine($registry);

        $resolved = $engine->resolveActive(
            [
                new MechanicBinding('roll', 5, null),
                new MechanicBinding('plain', null, null),
            ],
            [$this->mechanic(5, 'm', '1.0.0')],
            new ResolveActiveOptions(),
        );

        self::assertCount(1, $resolved);
        self::assertSame('m', $resolved[0]->getHandler()->getCode());
    }

    /**
     * Строка каталога без хендлера в реестре — отказ резолва.
     *
     * @return void
     */
    public function testResolveActiveRejectsBindingWithoutHandler(): void
    {
        $engine = new MechanicEngine(new MechanicHandlerRegistry());

        $this->expectException(MechanicInvalidException::class);
        $engine->resolveActive(
            [new MechanicBinding('roll', 5, null)],
            [$this->mechanic(5, 'm', '1.0.0')],
            new ResolveActiveOptions(),
        );
    }

    /**
     * Пустой список и хендлер без маркера не включают срез. Маркер включает.
     *
     * @return void
     */
    public function testHasReliabilityCutReadsMarker(): void
    {
        $registry = new MechanicHandlerRegistry();
        $registry->register($this->handler('plain', '1.0.0', []));
        $registry->register($this->cuttingHandler());
        $engine = new MechanicEngine($registry);
        $catalog = [
            $this->mechanic(1, 'plain', '1.0.0'),
            $this->mechanic(2, 'cut', '1.0.0'),
        ];
        $options = new ResolveActiveOptions();

        self::assertFalse($engine->hasReliabilityCut([], [], $options));
        self::assertFalse($engine->hasReliabilityCut([new MechanicBinding('roll', 1, null)], $catalog, $options));
        self::assertTrue($engine->hasReliabilityCut(
            [new MechanicBinding('roll', 1, null), new MechanicBinding('roll', 2, null)],
            $catalog,
            $options,
        ));
    }

    /**
     * includeCodes фильтрует семейство, extraRuleCodes добавляет код правила вне фильтра.
     *
     * @return void
     */
    public function testResolveActiveFiltersIncludeCodesAndAddsExtraRuleCodes(): void
    {
        $registry = new MechanicHandlerRegistry();
        $registry->register($this->handler('a', '1.0.0', ['ev' => 0]));
        $registry->register($this->handler('b', '1.0.0', ['ev' => 0]));
        $engine = new MechanicEngine($registry);

        $resolved = $engine->resolveActive(
            [
                new MechanicBinding('one', 1, null),
                new MechanicBinding('two', 2, null),
            ],
            [
                $this->mechanic(1, 'a', '1.0.0'),
                $this->mechanic(2, 'b', '1.0.0'),
            ],
            new ResolveActiveOptions(['a'], ['two']),
        );

        $codes = array_map(
            static fn ($resolvedMechanic): string => $resolvedMechanic->getHandler()->getCode(),
            $resolved,
        );
        sort($codes);
        self::assertSame(['a', 'b'], $codes);
    }

    /**
     * runEvent выполняет подписанных в порядке приоритета и мутирует контекст.
     *
     * @return void
     */
    public function testRunEventOrdersSubscribersByPriority(): void
    {
        $registry = new MechanicHandlerRegistry();
        $registry->register($this->tracingHandler('late', 20));
        $registry->register($this->tracingHandler('early', 5));
        $registry->register($this->tracingHandler('other', 0, 'other'));
        $engine = new MechanicEngine($registry);
        $active = $engine->resolveActive(
            [
                new MechanicBinding('code-x', 1, null),
                new MechanicBinding('code-x', 2, null),
                new MechanicBinding('code-x', 3, null),
            ],
            [
                $this->mechanic(1, 'late', '1.0.0'),
                $this->mechanic(2, 'early', '1.0.0'),
                $this->mechanic(3, 'other', '1.0.0'),
            ],
            new ResolveActiveOptions(),
        );
        $context = new MechanicEngineTraceContext();

        $engine->runEvent('ev', $context, $active);

        self::assertSame(['early', 'late'], $context->trace);
    }

    /**
     * Доплачивает ОС за каждую способность сверх freeCount по признаку.
     *
     * @return void
     */
    public function testPurchaseSurchargeChargesKeywordMatchesBeyondFreeCount(): void
    {
        $context = $this->runSurcharge(
            [
                'a' => 1,
                'b' => 1,
                'c' => 1,
            ],
            [
                'a' => ['common'],
                'b' => ['common'],
                'c' => ['other'],
            ],
            [],
            new PurchaseSurchargePayload('common', null, 1, 2),
        );

        self::assertSame(2, $context->getOsSurchargeTotal());
        self::assertEquals([new SurchargeItem('b', 2)], $context->getSurchargeItems());
    }

    /**
     * Фильтр по расе учитывает расовые способности и не сравнивает код расы.
     *
     * @return void
     */
    public function testPurchaseSurchargeChargesRacialAbilities(): void
    {
        $context = $this->runSurcharge(
            ['a' => 1, 'b' => 1],
            ['a' => [], 'b' => []],
            ['a', 'b', 'c'],
            new PurchaseSurchargePayload(null, 'beast', 0, 1),
        );

        self::assertSame(2, $context->getOsSurchargeTotal());
    }

    /**
     * Без совпадений ничего не начисляет.
     *
     * @return void
     */
    public function testPurchaseSurchargeChargesNothingWithoutMatches(): void
    {
        $context = $this->runSurcharge(
            ['a' => 1],
            ['a' => ['other']],
            [],
            new PurchaseSurchargePayload('common', null, 0, 2),
        );

        self::assertSame(0, $context->getOsSurchargeTotal());
        self::assertSame([], $context->getSurchargeItems());
    }

    /**
     * Прогон purchase_surcharge на character.osSteps.
     *
     * @param array<string, int> $abilityLevels Уровни.
     * @param array<string, list<string>> $abilityKeywords Признаки.
     * @param list<string> $racialAbilityCodes Расовые коды.
     * @param PurchaseSurchargePayload $payload Параметры доплаты.
     *
     * @return CharacterMechanicContext Контекст после события.
     */
    private function runSurcharge(
        array $abilityLevels,
        array $abilityKeywords,
        array $racialAbilityCodes,
        PurchaseSurchargePayload $payload,
    ): CharacterMechanicContext {
        $registry = new MechanicHandlerRegistry();
        $registry->register(new PurchaseSurchargeHandler());
        $engine = new MechanicEngine($registry);
        $context = new CharacterMechanicContext(new MechanicState($abilityLevels, $abilityKeywords, $racialAbilityCodes));
        $engine->runEvent(
            PurchaseSurchargeEvent::NAME,
            $context,
            $engine->resolveActive(
                [new MechanicBinding('code-x', 4, $payload)],
                [$this->mechanic(4, 'purchase_surcharge', '1.0.0')],
                new ResolveActiveOptions(),
            ),
        );

        return $context;
    }

    /**
     * Строка каталога.
     *
     * @param int $id Id.
     * @param string $code Код семейства.
     * @param string $version Поставка.
     *
     * @return MechanicRecord Запись.
     */
    private function mechanic(int $id, string $code, string $version): MechanicRecord
    {
        return MechanicRecord::fromNormalized([
            'id' => $id,
            'code' => $code,
            'name' => 'Механика',
            'description' => '',
            'handler_version' => $version,
        ]);
    }

    /**
     * Хендлер без побочного эффекта.
     *
     * @param string $code Код.
     * @param string $version Поставка.
     * @param array<string, int> $subscriptions Подписки.
     *
     * @return IMechanicHandler Хендлер.
     */
    private function handler(string $code, string $version, array $subscriptions): IMechanicHandler
    {
        return new class ($code, $version, $subscriptions) implements IMechanicHandler {
            /**
             * @param array<string, int> $subscriptions Подписки.
             */
            public function __construct(
                private readonly string $code,
                private readonly string $version,
                private readonly array $subscriptions,
            ) {
            }

            public function getCode(): string
            {
                return $this->code;
            }

            public function getVersion(): string
            {
                return $this->version;
            }

            public function getSubscriptions(): array
            {
                return $this->subscriptions;
            }

            public function run(?MechanicPayload $payload, object $context, string $event): void
            {
            }
        };
    }

    /**
     * Хендлер с маркером среза. Код в проверке не читается.
     *
     * @return IMechanicHandler Хендлер.
     */
    private function cuttingHandler(): IMechanicHandler
    {
        return new class implements IMechanicHandler, IReliabilityCut {
            public function getCode(): string
            {
                return 'cut';
            }

            public function getVersion(): string
            {
                return '1.0.0';
            }

            public function getSubscriptions(): array
            {
                return [];
            }

            public function run(?MechanicPayload $payload, object $context, string $event): void
            {
            }
        };
    }

    /**
     * Хендлер, пишущий свой код в trace контекста.
     *
     * @param string $code Код.
     * @param int $priority Приоритет события ev.
     * @param string $event Событие подписки.
     *
     * @return IMechanicHandler Хендлер.
     */
    private function tracingHandler(string $code, int $priority, string $event = 'ev'): IMechanicHandler
    {
        return new class ($code, $priority, $event) implements IMechanicHandler {
            public function __construct(
                private readonly string $code,
                private readonly int $priority,
                private readonly string $event,
            ) {
            }

            public function getCode(): string
            {
                return $this->code;
            }

            public function getVersion(): string
            {
                return '1.0.0';
            }

            public function getSubscriptions(): array
            {
                return [$this->event => $this->priority];
            }

            public function run(?MechanicPayload $payload, object $context, string $event): void
            {
                if ($context instanceof MechanicEngineTraceContext) {
                    $context->trace[] = $this->code;
                }
            }
        };
    }
}

/**
 * Контекст теста приоритета: список кодов вызвавшихся хендлеров.
 */
final class MechanicEngineTraceContext
{
    /**
     * Коды в порядке вызова.
     *
     * @var list<string>
     */
    public array $trace = [];
}
