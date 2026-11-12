<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Env;
use MadLisp\Lib\Http;

class HttpTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\RequiresPhpExtension('curl')]
    public function testResponseHeadersPreserveColons(): void
    {
        $serverCode = <<<'PHP'
            $server = stream_socket_server('tcp://127.0.0.1:0');
            echo stream_socket_get_name($server, false), "\n";
            flush();

            $client = stream_socket_accept($server, 5);
            if (!$client) {
                exit(1);
            }
            stream_set_timeout($client, 5);
            while (($line = fgets($client)) !== false && $line !== "\r\n") {
            }
            fwrite($client, "HTTP/1.1 200 OK\r\n"
                . "Date: Tue, 10 Nov 2026 12:34:56 GMT\r\n"
                . "Location: https://example.test/next\r\n"
                . "X-Review: a:b\r\n"
                . "X-Plain: value\r\n"
                . "Content-Length: 2\r\n"
                . "Connection: close\r\n\r\nok");
            fclose($client);
            fclose($server);
            PHP;

        $process = proc_open([PHP_BINARY, '-r', $serverCode], [1 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);

        try {
            stream_set_timeout($pipes[1], 5);
            $address = fgets($pipes[1]);
            $this->assertNotFalse($address);

            $env = new Env('test');
            (new Http())->register($env);
            $response = $env->get('http')->call(['GET', 'http://' . trim($address) . '/']);

            $this->assertSame(200, $response->get('status'));
            $this->assertSame('ok', $response->get('body'));
            $headers = $response->get('headers')->getData();
            $this->assertSame('Tue, 10 Nov 2026 12:34:56 GMT', $headers['Date'] ?? null);
            $this->assertSame('https://example.test/next', $headers['Location'] ?? null);
            $this->assertSame('a:b', $headers['X-Review'] ?? null);
            $this->assertSame('value', $headers['X-Plain'] ?? null);
        } finally {
            fclose($pipes[1]);
            proc_terminate($process);
            proc_close($process);
        }
    }
}
