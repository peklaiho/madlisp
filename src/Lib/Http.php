<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

namespace MadLisp\Lib;

use MadLisp\CoreFunc;
use MadLisp\Env;
use MadLisp\Hash;
use MadLisp\MadLispException;

class Http implements ILib
{
    public function register(Env $env): void
    {
        $env->set('http', new CoreFunc('http', 'Perform a HTTP request.', 2, 4,
            function (string $method, string $url, ?string $requestBody = null, ?Hash $requestHeaders = null) {
                $ch = curl_init($url);

                $method = strtoupper($method);
                if ($method == 'HEAD') {
                    curl_setopt($ch, CURLOPT_NOBODY, true);
                } elseif ($method != 'GET') {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                }

                if ($requestBody !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
                    curl_setopt($ch, CURLOPT_POSTREDIR, 3);
                }

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

                if ($requestHeaders !== null) {
                    $h = [];
                    foreach ($requestHeaders->getData() as $key => $val) {
                        $h[] = "$key: $val";
                    }
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
                }

                // for debugging
                // curl_setopt($ch, CURLOPT_VERBOSE, true);

                $responseHeaders = [];
                curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$responseHeaders) {
                    $parts = explode(':', $header);
                    if (count($parts) == 2) {
                        $key = trim($parts[0]);
                        $val = trim($parts[1]);
                        $responseHeaders[$key] = $val;
                    }
                    return strlen($header);
                });

                $responseBody = @curl_exec($ch);

                if (curl_errno($ch) !== 0) {
                    throw new MadLispException('HTTP error: ' . curl_error($ch));
                }

                $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                curl_close($ch);

                return new Hash([
                    'status' => $status,
                    'body' => $responseBody,
                    'headers' => new Hash($responseHeaders)
                ]);
            }
        ));
    }
}
