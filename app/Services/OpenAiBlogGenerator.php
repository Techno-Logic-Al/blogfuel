<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class OpenAiBlogGenerator
{
    /**
     * Generate a structured blog post from the studio form inputs.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function generate(array $attributes): array
    {
        $apiKey = (string) config('services.openai.key');
        $model = $this->resolveModel($attributes);
        $requestTimeout = $this->requestTimeout();

        if ($apiKey === '') {
            throw new RuntimeException('Add your OPENAI_API_KEY to the environment before generating posts.');
        }

        try {
            $response = Http::baseUrl((string) config('services.openai.base_url'))
                ->acceptJson()
                ->asJson()
                ->withToken($apiKey)
                ->withHeaders([
                    'X-Client-Request-Id' => (string) Str::uuid(),
                ])
                ->connectTimeout($this->connectTimeout($requestTimeout))
                ->timeout($requestTimeout)
                ->post('responses', [
                    'model' => $model,
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a senior editorial strategist creating polished, original blog posts for a modern design-forward publication. Never mention AI, never use markdown, and return only content that matches the requested schema.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $this->prompt($attributes),
                        ],
                    ],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'ai_blog_post',
                            'schema' => $this->schema(),
                            'strict' => true,
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'Generation took too long to complete. Try again, choose a faster model, or shorten the brief.',
                previous: $exception
            );
        }

        if ($response->status() === 429) {
            throw new RuntimeException(
                'Generation is temporarily rate-limited. Please wait a moment and try again.'
            );
        }

        if ($response->failed()) {
            $message = $response->json('error.message')
                ?: 'OpenAI returned an error while generating the article.';

            throw new RuntimeException($message);
        }

        $payload = $response->json();
        $rawText = $payload['output_text'] ?? $this->extractOutputText($payload);

        if (! is_string($rawText) || trim($rawText) === '') {
            throw new RuntimeException('The OpenAI response did not contain any article content.');
        }

        $decoded = json_decode($rawText, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('The generated response could not be parsed into a blog post.');
        }

        return [
            'title' => trim((string) ($decoded['title'] ?? 'Untitled article')),
            'excerpt' => trim((string) ($decoded['excerpt'] ?? '')),
            'intro' => trim((string) ($decoded['intro'] ?? '')),
            'sections' => $this->normalizeSections($decoded['sections'] ?? []),
            'takeaway' => trim((string) ($decoded['takeaway'] ?? '')),
            'tags' => $this->normalizeTags($decoded['tags'] ?? []),
            'reading_time' => max(3, min(18, (int) ($decoded['reading_time'] ?? 6))),
            'model' => $model,
        ];
    }

    /**
     * Build the editorial generation prompt.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function prompt(array $attributes): string
    {
        $tone = config("blog.tones.{$attributes['tone']}");
        $audience = config("blog.audiences.{$attributes['audience']}");
        $depth = config("blog.depths.{$attributes['depth']}");
        $keywords = trim((string) ($attributes['keywords'] ?? ''));
        $enhanceSeo = (bool) ($attributes['enhance_seo'] ?? false);

        $lines = array_filter([
            'Create a polished blog article using the brief below.',
            'Topic: '.$attributes['topic'],
            $keywords !== '' ? 'Keywords: '.$keywords : null,
            'Tone direction: '.($tone['label'] ?? $attributes['tone']).'. '.($tone['prompt'] ?? ''),
            'Target audience: '.($audience['label'] ?? $attributes['audience']).'. '.($audience['prompt'] ?? ''),
            'Depth: '.($depth['label'] ?? $attributes['depth']).'. '.($depth['prompt'] ?? ''),
            'Article requirements:',
            '- Make the title specific, modern, and under 70 characters.',
            '- Write an excerpt in 2 sentences max.',
            '- Write an intro paragraph that hooks the reader immediately.',
            '- Create exactly '.((int) ($depth['sections'] ?? 4)).' sections.',
            '- Each section body should be 2 compact paragraphs in plain text.',
            '- Add a concise closing takeaway paragraph.',
            '- Return 3 to 5 short tags.',
        ]);

        if ($enhanceSeo) {
            $lines = array_merge($lines, [
                'SEO enhancement: enabled.',
                '- Optimise for search intent while keeping the writing natural and publication-ready.',
                '- Use the strongest keyword or topic phrase naturally in the title, intro, and at least one section heading.',
                '- If no keywords are provided, infer a sensible primary search phrase from the topic.',
                '- Make section headings descriptive, scannable, and useful for search-first readers.',
                '- Answer the reader\'s likely core question early in the article and avoid keyword stuffing.',
                '- Keep the excerpt concise enough to work as a meta description candidate.',
            ]);
        }

        $lines[] = '- Keep the article original, helpful, and publication-ready.';

        return implode("\n", $lines);
    }

    /**
     * Resolve the selected model, falling back to the configured default.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function resolveModel(array $attributes): string
    {
        $availableModels = config('blog.models', []);
        $selectedModel = (string) ($attributes['model'] ?? '');

        if (array_key_exists($selectedModel, $availableModels)) {
            return $selectedModel;
        }

        $configuredModel = (string) config('services.openai.model');

        if (array_key_exists($configuredModel, $availableModels)) {
            return $configuredModel;
        }

        return array_key_exists('gpt-5-mini', $availableModels)
            ? 'gpt-5-mini'
            : (string) (array_key_first($availableModels) ?? 'gpt-5-mini');
    }

    /**
     * Resolve an HTTP timeout that stays within the host PHP execution limit.
     */
    protected function requestTimeout(): int
    {
        $configuredTimeout = max(5, (int) config('services.openai.timeout', 25));
        $phpExecutionLimit = (int) ini_get('max_execution_time');

        if ($phpExecutionLimit <= 0) {
            return $configuredTimeout;
        }

        return min($configuredTimeout, max(5, $phpExecutionLimit - 5));
    }

    /**
     * Keep the connection timeout lower than the total request timeout.
     */
    protected function connectTimeout(int $requestTimeout): int
    {
        return max(3, min(10, $requestTimeout - 1));
    }

    /**
     * Return the JSON schema sent to the Responses API.
     *
     * @return array<string, mixed>
     */
    protected function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'excerpt' => ['type' => 'string'],
                'intro' => ['type' => 'string'],
                'sections' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'heading' => ['type' => 'string'],
                            'body' => ['type' => 'string'],
                        ],
                        'required' => ['heading', 'body'],
                        'additionalProperties' => false,
                    ],
                ],
                'takeaway' => ['type' => 'string'],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'reading_time' => ['type' => 'integer'],
            ],
            'required' => ['title', 'excerpt', 'intro', 'sections', 'takeaway', 'tags', 'reading_time'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Pull assistant text from the Responses API payload when output_text is absent.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function extractOutputText(array $payload): string
    {
        return collect($payload['output'] ?? [])
            ->flatMap(function (mixed $item): array {
                return is_array($item) ? ($item['content'] ?? []) : [];
            })
            ->filter(function (mixed $content): bool {
                return is_array($content) && ($content['type'] ?? null) === 'output_text';
            })
            ->pluck('text')
            ->filter(fn (mixed $text) => is_string($text) && trim($text) !== '')
            ->implode("\n");
    }

    /**
     * Normalize generated section data.
     *
     * @param  mixed  $sections
     * @return array<int, array<string, string>>
     */
    protected function normalizeSections(mixed $sections): array
    {
        return collect(is_array($sections) ? $sections : [])
            ->filter(fn (mixed $section) => is_array($section))
            ->map(function (array $section): array {
                return [
                    'heading' => trim((string) ($section['heading'] ?? 'Section')),
                    'body' => trim((string) ($section['body'] ?? '')),
                ];
            })
            ->filter(fn (array $section) => $section['body'] !== '')
            ->values()
            ->all();
    }

    /**
     * Normalize generated tags.
     *
     * @param  mixed  $tags
     * @return array<int, string>
     */
    protected function normalizeTags(mixed $tags): array
    {
        return collect(is_array($tags) ? $tags : [])
            ->map(fn (mixed $tag) => trim((string) $tag))
            ->filter()
            ->unique()
            ->take(5)
            ->values()
            ->all();
    }
}
