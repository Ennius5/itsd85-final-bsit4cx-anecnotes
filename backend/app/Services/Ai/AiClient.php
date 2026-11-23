<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Minimal OpenAI-compatible Chat Completions client with provider fallback (T1, T2).
 *
 * For each provider in config('ai.providers'), in order: send the request, parse the
 * reply as JSON, check it with $validate. The first valid answer wins. A provider that
 * times out, returns an HTTP error or gives invalid JSON is skipped for the next one.
 */
class AiClient
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  callable(array<string, mixed>): (string|null)  $validate  Returns an error message, or null when valid
     *
     * @throws AiUnavailableException
     */
    public function json(array $messages, callable $validate, int $maxTokens = 300): AiResult
    {
        $providers = config('ai.providers');
        $failures = [];

        foreach ($providers as $i => $p) {
            $started = hrtime(true);
            try {
                $response = Http::withToken((string) $p['api_key'])
                    ->acceptJson()
                    ->timeout((int) config('ai.timeout'))
                    ->post(rtrim((string) $p['base_url'], '/').'/chat/completions', [
                        'model' => $p['model'],
                        'messages' => $messages,
                        'temperature' => 0,
                        'max_tokens' => $maxTokens,
                        'response_format' => ['type' => 'json_object'],
                    ]);
                $latency = (int) ((hrtime(true) - $started) / 1_000_000);

                if ($response->failed()) {
                    throw new \RuntimeException('HTTP '.$response->status());
                }

                $raw = (array) $response->json();
                $content = (string) data_get($raw, 'choices.0.message.content', '');
                $data = $this->decode($content);
                if ($data === null) {
                    throw new \RuntimeException('reply was not valid JSON');
                }
                if (($error = $validate($data)) !== null) {
                    throw new \RuntimeException('reply failed validation: '.$error);
                }

                return new AiResult($p['name'], $p['model'], $data, $this->stripContent($raw), $latency, $i + 1, $i > 0);
            } catch (ConnectionException $e) {
                $failures[] = $this->failure($p, 'timeout or connection error', $started);
            } catch (Throwable $e) {
                $failures[] = $this->failure($p, $e->getMessage(), $started);
            }
        }

        throw new AiUnavailableException($failures ?: [['provider' => 'none', 'model' => '-', 'error' => 'no provider configured', 'latency_ms' => 0]]);
    }

    /** @return array<string, mixed>|null */
    private function decode(string $content): ?array
    {
        // Some models wrap JSON in ``` fences despite JSON mode.
        $content = trim((string) preg_replace('/^```(?:json)?|```$/m', '', trim($content)));
        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Keep usage and metadata; drop the generated text (it is stored separately, redacted).
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function stripContent(array $raw): array
    {
        unset($raw['choices']);

        return $raw;
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array{provider: string, model: string, error: string, latency_ms: int}
     */
    private function failure(array $p, string $error, int|float $started): array
    {
        return [
            'provider' => (string) $p['name'],
            'model' => (string) $p['model'],
            'error' => $error,
            'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
        ];
    }
}
