<?php

declare(strict_types=1);

use Mifrial\Tools\Duplicates\Cluster;
use Mifrial\Tools\Duplicates\Scanner;
use PHPUnit\Framework\TestCase;

final class ScannerTest extends TestCase
{
    private const FIXTURE_SCAN = __DIR__ . '/fixtures/modules';

    /** @var array{clusters: list<Cluster>, stats: array<string, mixed>}|null */
    private ?array $scanResult = null;

    public function testRenamedVariablesAreStillDetected(): void
    {
        $cluster = $this->clusterWith('Service/Second.php');

        self::assertSame(Cluster::KIND_GATE, $cluster->kind);
        self::assertSame(2, count($cluster->occurrences));
    }

    public function testBoilerplateFoldersStayOutOfTheGate(): void
    {
        $cluster = $this->clusterWith('Dto/SecondDto.php');

        self::assertSame(Cluster::KIND_BOILERPLATE, $cluster->kind);
    }

    public function testCrossModuleCloneIsReportedButNotGated(): void
    {
        $cluster = $this->clusterWith('Cross/Service/Gamma.php');

        self::assertSame(Cluster::KIND_CROSS_MODULE, $cluster->kind);
    }

    public function testValueDuplicationIsGatedEvenAcrossModules(): void
    {
        $cluster = $this->clusterWith('Value/Money.php');

        self::assertSame(Cluster::KIND_GATE, $cluster->kind);
        self::assertSame(2, count($cluster->occurrences));
    }

    public function testAllowRuleMovesClusterOutOfTheGate(): void
    {
        $cluster = $this->clusterWith('Service/AllowedCopy.php');

        self::assertSame(Cluster::KIND_ALLOWED, $cluster->kind);
        self::assertIsString($cluster->reason);
        self::assertStringContainsString('fixture allow rule', $cluster->reason);
        self::assertStringContainsString('2026-12-01', $cluster->reason);
    }

    public function testUnrelatedCodeProducesNoCluster(): void
    {
        foreach ($this->scanResult()['clusters'] as $cluster) {
            foreach ($cluster->occurrences as $occurrence) {
                self::assertStringNotContainsString('Service/Unique.php', $occurrence->path);
            }
        }
    }

    public function testStatsMatchClusters(): void
    {
        $result = $this->scanResult();
        $stats = $result['stats'];

        $gateClusters = array_filter(
            $result['clusters'],
            static fn(Cluster $cluster): bool => $cluster->kind === Cluster::KIND_GATE,
        );

        self::assertSame(count($gateClusters), $stats['gateClusters']);
        self::assertSame(count($result['clusters']), $stats['clusters']);
        self::assertGreaterThan(0, $stats['gateLines']);
        self::assertArrayHasKey('Core/Alpha', $stats['byModule']);
    }

    public function testCheckExitCodeFollowsCeiling(): void
    {
        $configFile = $this->writeConfig(0);

        try {
            self::assertSame(1, $this->runCli($configFile, '--check'));
            self::assertSame(0, $this->runCli($this->writeConfig(100000), '--check'));
        } finally {
            @unlink($configFile);
        }
    }

    public function testRatchetLowersCeilingAndRefusesToRaise(): void
    {
        $configFile = $this->writeConfig(100000);

        try {
            self::assertSame(0, $this->runCli($configFile, '--ratchet'));

            $config = json_decode((string) file_get_contents($configFile), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($config);
            self::assertLessThan(100000, $config['gate']['maxDuplicatedLines']);

            $lowered = (int) $config['gate']['maxDuplicatedLines'];
            $config['gate']['maxDuplicatedLines'] = 1;
            file_put_contents($configFile, json_encode($config, JSON_THROW_ON_ERROR));

            self::assertSame(1, $this->runCli($configFile, '--ratchet'));

            $unchanged = json_decode((string) file_get_contents($configFile), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($unchanged);
            self::assertSame(1, $unchanged['gate']['maxDuplicatedLines']);
            self::assertGreaterThan(1, $lowered);
        } finally {
            @unlink($configFile);
        }
    }

    /**
     * @return array{clusters: list<Cluster>, stats: array<string, mixed>}
     */
    private function scanResult(): array
    {
        if ($this->scanResult !== null) {
            return $this->scanResult;
        }

        $scanner = Scanner::fromConfig(self::FIXTURE_SCAN, [
            'minTokens' => 50,
            'minLines' => 8,
            'excludePaths' => [],
            'gate' => [
                'excludeFolders' => ['Dto', 'Table', 'Spec'],
                'alwaysIncludeFolders' => ['Value'],
            ],
            'allow' => [
                [
                    'paths' => ['modules/*/Service/Allowed*.php'],
                    'reason' => 'fixture allow rule',
                    'review' => '2026-12-01',
                ],
            ],
        ]);

        $this->scanResult = $scanner->scan();

        return $this->scanResult;
    }

    private function clusterWith(string $suffix): Cluster
    {
        foreach ($this->scanResult()['clusters'] as $cluster) {
            foreach ($cluster->occurrences as $occurrence) {
                if (str_ends_with($occurrence->path, $suffix)) {
                    return $cluster;
                }
            }
        }

        self::fail(sprintf('no cluster contains %s', $suffix));
    }

    private function writeConfig(int $ceiling): string
    {
        $file = tempnam(sys_get_temp_dir(), 'duplicates-config-');
        self::assertIsString($file);

        $config = [
            'minTokens' => 50,
            'minLines' => 8,
            'excludePaths' => [],
            'gate' => [
                'excludeFolders' => ['Dto', 'Table', 'Spec'],
                'alwaysIncludeFolders' => ['Value'],
                'maxDuplicatedLines' => $ceiling,
            ],
            'allow' => [],
        ];

        file_put_contents($file, json_encode($config, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return $file;
    }

    private function runCli(string $configFile, string $mode): int
    {
        $command = sprintf(
            '%s %s --root=%s --config=%s %s --top=1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/../scan.php'),
            escapeshellarg(self::FIXTURE_SCAN),
            escapeshellarg($configFile),
            $mode,
        );

        exec($command . ' 2>/dev/null', $output, $exitCode);

        return $exitCode;
    }
}
