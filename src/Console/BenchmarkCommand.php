<?php

namespace SmMehdiSharifi\LaravelMsgpack\Console;

use Illuminate\Console\Command;
use SmMehdiSharifi\LaravelMsgpack\MsgpackManager;

class BenchmarkCommand extends Command
{
    private const MAX_ITERATIONS = 1000000;

    private const GZIP_LEVEL = 6;

    protected $signature = 'msgpack:benchmark
        {--iterations=1000 : Number of times each operation is measured}
        {--json : Output machine-readable JSON instead of a table}';

    protected $description = 'Compare JSON and MessagePack size and serialization performance';

    public function handle(MsgpackManager $manager): int
    {
        $iterations = $this->iterations();

        if ($iterations === null) {
            $message = sprintf(
                'The --iterations option must be an integer between 1 and %d.',
                self::MAX_ITERATIONS,
            );

            if ($this->option('json')) {
                $this->line(json_encode(['error' => $message], JSON_THROW_ON_ERROR));
            } else {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $payload = $this->payload();
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $messagePack = $manager->encode($payload);

        $raw = [
            'json' => [
                'bytes' => strlen($json),
                'encode_ms_per_iteration' => $this->measure(
                    static function () use ($payload): void {
                        json_encode($payload, JSON_THROW_ON_ERROR);
                    },
                    $iterations,
                ),
                'decode_ms_per_iteration' => $this->measure(
                    static function () use ($json): void {
                        json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                    },
                    $iterations,
                ),
            ],
            'messagepack' => [
                'bytes' => strlen($messagePack),
                'encode_ms_per_iteration' => $this->measure(
                    static function () use ($manager, $payload): void {
                        $manager->encode($payload);
                    },
                    $iterations,
                ),
                'decode_ms_per_iteration' => $this->measure(
                    static function () use ($manager, $messagePack): void {
                        $manager->decode($messagePack);
                    },
                    $iterations,
                ),
            ],
        ];
        $raw['size_reduction_percent'] = $this->reductionPercent(
            $raw['json']['bytes'],
            $raw['messagepack']['bytes'],
        );

        $metrics = [
            'fixture' => 'deterministic_api_payload',
            'php_version' => PHP_VERSION,
            'iterations' => $iterations,
            'raw' => $raw,
            'gzip' => $this->gzipMetrics($json, $messagePack, $iterations),
        ];

        if ($this->option('json')) {
            $this->line(json_encode(
                $metrics,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ));
        } else {
            $this->renderTable($metrics);
        }

        return self::SUCCESS;
    }

    private function iterations(): ?int
    {
        $iterations = filter_var($this->option('iterations'), FILTER_VALIDATE_INT);

        if ($iterations === false || $iterations < 1 || $iterations > self::MAX_ITERATIONS) {
            return null;
        }

        return $iterations;
    }

    /**
     * Build a stable fixture so results can be compared across runs.
     *
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'id' => 1042,
            'name' => 'Laravel MessagePack',
            'locale' => 'fa-IR',
            'active' => true,
            'description' => 'Optional binary transport for Laravel APIs.',
            'tags' => ['laravel', 'api', 'serialization', 'messagepack'],
            'owner' => [
                'id' => 7,
                'name' => 'Mehdi Sharifi',
                'roles' => ['maintainer', 'developer'],
            ],
            'items' => [
                [
                    'sku' => 'LM-100',
                    'quantity' => 2,
                    'price' => 19.99,
                ],
                [
                    'sku' => 'LM-200',
                    'quantity' => 1,
                    'price' => 7.5,
                ],
            ],
            'metadata' => [
                'created_at' => '2026-09-11T05:00:00+00:00',
                'unicode' => 'سلام دنیا / こんにちは / مرحبا',
                'features' => [
                    'negotiation' => true,
                    'fallback' => 'json',
                ],
            ],
        ];
    }

    private function measure(callable $operation, int $iterations): float
    {
        $operation();
        $startedAt = hrtime(true);

        for ($iteration = 0; $iteration < $iterations; $iteration++) {
            $operation();
        }

        return round((hrtime(true) - $startedAt) / 1000000 / $iterations, 3);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function gzipMetrics(string $json, string $messagePack, int $iterations): ?array
    {
        if (! function_exists('gzencode') || ! function_exists('gzdecode')) {
            return null;
        }

        $compressedJson = gzencode($json, self::GZIP_LEVEL);
        $compressedMessagePack = gzencode($messagePack, self::GZIP_LEVEL);

        if ($compressedJson === false || $compressedMessagePack === false) {
            return null;
        }

        $metrics = [
            'json' => [
                'bytes' => strlen($compressedJson),
                'compress_ms_per_iteration' => $this->measure(
                    static function () use ($json): void {
                        gzencode($json, self::GZIP_LEVEL);
                    },
                    $iterations,
                ),
                'decompress_ms_per_iteration' => $this->measure(
                    static function () use ($compressedJson): void {
                        gzdecode($compressedJson);
                    },
                    $iterations,
                ),
            ],
            'messagepack' => [
                'bytes' => strlen($compressedMessagePack),
                'compress_ms_per_iteration' => $this->measure(
                    static function () use ($messagePack): void {
                        gzencode($messagePack, self::GZIP_LEVEL);
                    },
                    $iterations,
                ),
                'decompress_ms_per_iteration' => $this->measure(
                    static function () use ($compressedMessagePack): void {
                        gzdecode($compressedMessagePack);
                    },
                    $iterations,
                ),
            ],
        ];
        $metrics['size_reduction_percent'] = $this->reductionPercent(
            $metrics['json']['bytes'],
            $metrics['messagepack']['bytes'],
        );

        return $metrics;
    }

    private function reductionPercent(int $baseline, int $value): float
    {
        if ($baseline === 0) {
            return 0.0;
        }

        return round((1 - ($value / $baseline)) * 100, 2);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function renderTable(array $metrics): void
    {
        $this->info('MessagePack benchmark');
        $this->line('Fixture: '.$metrics['fixture']);
        $this->line('Iterations: '.$metrics['iterations']);

        $raw = $metrics['raw'];
        $this->table(
            ['Metric', 'JSON', 'MessagePack', 'Reduction'],
            [
                [
                    'Payload size',
                    $this->formatBytes($raw['json']['bytes']),
                    $this->formatBytes($raw['messagepack']['bytes']),
                    $this->formatPercent($raw['size_reduction_percent']),
                ],
                [
                    'Encode time / iteration',
                    $this->formatMilliseconds($raw['json']['encode_ms_per_iteration']),
                    $this->formatMilliseconds($raw['messagepack']['encode_ms_per_iteration']),
                    '',
                ],
                [
                    'Decode time / iteration',
                    $this->formatMilliseconds($raw['json']['decode_ms_per_iteration']),
                    $this->formatMilliseconds($raw['messagepack']['decode_ms_per_iteration']),
                    '',
                ],
            ],
        );

        if ($metrics['gzip'] === null) {
            $this->warn('Gzip metrics unavailable (ext-zlib is unavailable or compression failed).');

            return;
        }

        $gzip = $metrics['gzip'];
        $this->line('Gzip compression (level '.self::GZIP_LEVEL.')');
        $this->table(
            ['Metric', 'JSON', 'MessagePack', 'Reduction'],
            [
                [
                    'Compressed size',
                    $this->formatBytes($gzip['json']['bytes']),
                    $this->formatBytes($gzip['messagepack']['bytes']),
                    $this->formatPercent($gzip['size_reduction_percent']),
                ],
                [
                    'Compress time / iteration',
                    $this->formatMilliseconds($gzip['json']['compress_ms_per_iteration']),
                    $this->formatMilliseconds($gzip['messagepack']['compress_ms_per_iteration']),
                    '',
                ],
                [
                    'Decompress time / iteration',
                    $this->formatMilliseconds($gzip['json']['decompress_ms_per_iteration']),
                    $this->formatMilliseconds($gzip['messagepack']['decompress_ms_per_iteration']),
                    '',
                ],
            ],
        );
    }

    private function formatBytes(int $bytes): string
    {
        return $bytes.' B';
    }

    private function formatMilliseconds(float $milliseconds): string
    {
        return number_format($milliseconds, 3).' ms';
    }

    private function formatPercent(float $percent): string
    {
        return number_format($percent, 2).' %';
    }
}
