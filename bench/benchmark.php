#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use MadLisp\Lisp;
use MadLisp\LispFactory;

$workloadDir = __DIR__ . '/lisp';
$workloads = [
    'arithmetic' => ['file' => 'arithmetic.lisp', 'expected' => 12502500],
    'tail-recursion' => ['file' => 'tail-recursion.lisp', 'expected' => 0],
    'collections' => ['file' => 'collections.lisp', 'expected' => 399980000],
    'environment' => ['file' => 'environment.lisp', 'expected' => 30000],
    'fibonacci' => ['file' => 'fibonacci.lisp', 'expected' => 6765],
];

$options = getopt('', ['workload:', 'iterations:', 'warmup:', 'compile', 'source', 'json', 'profile', 'list', 'help']);

if (isset($options['help'])) {
    printUsage();
}

if (isset($options['list'])) {
    foreach (array_keys($workloads) as $name) {
        echo $name . PHP_EOL;
    }
    exit(0);
}

$name = $options['workload'] ?? 'all';
$iterations = isset($options['iterations']) ? max(1, (int) $options['iterations']) : 10;
$warmup = isset($options['warmup']) ? max(0, (int) $options['warmup']) : 2;
$compile = isset($options['compile']);

if ($name !== 'all' && !isset($workloads[$name])) {
    fwrite(STDERR, "Unknown workload: $name" . PHP_EOL);
    printUsage(1);
}

if (isset($options['source'])) {
    $sourceWorkloads = $name === 'all' ? array_keys($workloads) : [$name];

    foreach ($sourceWorkloads as $workloadName) {
        $source = file_get_contents($workloadDir . '/' . $workloads[$workloadName]['file']);
        if ($source === false) {
            throw new RuntimeException('Unable to read workload ' . $workloads[$workloadName]['file']);
        }

        $lisp = (new LispFactory())->make(true);
        $program = $lisp->compile($lisp->read($source));

        if (count($sourceWorkloads) > 1) {
            echo '// ' . $workloadName . PHP_EOL;
        }
        echo $program->getSource() . PHP_EOL;
    }

    exit(0);
}

$results = [];

if ($name === 'all') {
    // PHP cannot reset memory_get_peak_usage() on all supported versions.
    // Run each workload in a child process so every peak is independent.
    foreach (array_keys($workloads) as $workloadName) {
        $command = sprintf(
            '%s %s %s --workload=%s --iterations=%d --warmup=%d --json %s',
            (isset($options['profile']) ? 'XDEBUG_TRIGGER=1' : ''),
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__FILE__),
            escapeshellarg($workloadName),
            $iterations,
            $warmup,
            $compile ? '--compile' : ''
        );
        exec($command, $output, $status);
        if ($status !== 0) {
            throw new RuntimeException('Workload process failed: ' . $workloadName);
        }

        $result = json_decode(implode('', $output), true);
        if (!is_array($result) || count($result) !== 1) {
            throw new RuntimeException('Invalid benchmark output: ' . $workloadName);
        }
        $results[] = $result[0];
        $output = [];
    }
} else {
    $results[] = runWorkload($name, $workloads[$name], $workloadDir, $iterations, $warmup, $compile);
}

if (isset($options['json'])) {
    echo json_encode($results, JSON_PRETTY_PRINT) . PHP_EOL;
} else {
    foreach ($results as $result) {
        if ($result['compile_ms'] >= 0) {
            printf(
                "%-20s: %3.2f ms compilation, %4.0f ms total, %3.0f ms/op, %3.0f ops/sec, peak memory %3d MB\n",
                $result['workload'],
                $result['compile_ms'],
                $result['total_ms'],
                $result['average_ms'],
                $result['ops_per_second'],
                intdiv($result['peak_memory_bytes'], 1024 * 1024),
            );
        } else {
            printf(
                "%-20s: %4.0f ms total, %3.0f ms/op, %3.0f ops/sec, peak memory %3d MB\n",
                $result['workload'],
                $result['total_ms'],
                $result['average_ms'],
                $result['ops_per_second'],
                intdiv($result['peak_memory_bytes'], 1024 * 1024),
            );
        }
    }
}

function runWorkload(string $workloadName, array $workload, string $workloadDir, int $iterations, int $warmup, bool $compile): array
{
    $source = file_get_contents($workloadDir . '/' . $workload['file']);
    if ($source === false) {
        throw new RuntimeException('Unable to read workload ' . $workload['file']);
    }

    $lisp = (new LispFactory())->make(true);

    if ($compile) {
        // Record compilation time separately
        $compileStart = hrtime(true);
        $compiledProgram = $lisp->compile($lisp->read($source));
        $compileElapsed = hrtime(true) - $compileStart;
    }

    for ($i = 0; $i < $warmup; $i++) {
        if ($compile) {
            $lisp->execute($compiledProgram);
        } else {
            $lisp->readEval($source);
        }
    }

    $start = hrtime(true);
    $lastResult = null;
    for ($i = 0; $i < $iterations; $i++) {
        if ($compile) {
            $lastResult = $lisp->execute($compiledProgram);
        } else {
            $lastResult = $lisp->readEval($source);
        }
    }
    $elapsedNs = hrtime(true) - $start;

    if ($lastResult !== $workload['expected']) {
        throw new RuntimeException(sprintf(
            '%s returned %s, expected %s',
            $workloadName,
            valueToString($lastResult),
            valueToString($workload['expected'])
        ));
    }

    return [
        'workload' => $workloadName,
        'iterations' => $iterations,
        'warmup' => $warmup,
        'compile_ms' => $compile ? round($compileElapsed / 1000000, 3) : -1,
        'total_ms' => round($elapsedNs / 1000000, 3),
        'average_ms' => round($elapsedNs / $iterations / 1000000, 3),
        'ops_per_second' => round($iterations / ($elapsedNs / 1000000000), 2),
        'peak_memory_bytes' => memory_get_peak_usage(true),
    ];
}

function valueToString($value): string
{
    if (is_scalar($value) || $value === null) {
        return var_export($value, true);
    }

    return get_class($value);
}

function printUsage(int $status = 0): void
{
    $usage = <<<'TEXT'
Usage: php bench/benchmark.php [options]

Options:
  --workload=name       Run one workload, or all (default: all)
  --compile             Use the compiler and executor
  --source              Print compiled PHP source without executing
  --iterations=n        Timed iterations (default: 10)
  --warmup=n            Untimed warmup iterations (default: 2)
  --json                Emit machine-readable JSON
  --profile             Use xdebug profiler
  --list                List available workloads
  --help                Show this help
TEXT;

    fwrite($status === 0 ? STDOUT : STDERR, $usage . PHP_EOL);
    exit($status);
}
