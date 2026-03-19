<?php

namespace Backpack\Profile\app\Services;

use ParabellumKoval\AiContentGenerator\Services\ContentGenerator;

class BotAvatarPromptPlanner
{
    private const SETTINGS_PREFIX = 'profile.bot_generation';
    private const PROMPT_SETTINGS_PREFIX = 'profile.bot_generation.avatar_prompt';
    private const DEFAULT_DRIVER = 'openai';
    private const DEFAULT_MODEL = 'gpt-5.1';
    private const DEFAULT_TEMPERATURE = 1.1;

    public function __construct(private readonly ContentGenerator $generator)
    {
    }

    public function planBatch(array $entries, string $language): array
    {
        if (!$this->enabled() || $entries === []) {
            return [];
        }

        $driver = trim((string) $this->setting(self::SETTINGS_PREFIX . '.avatar_prompt_driver', self::DEFAULT_DRIVER));
        $model = trim((string) $this->setting(self::SETTINGS_PREFIX . '.avatar_prompt_model', self::DEFAULT_MODEL));
        $temperature = (float) $this->setting(self::SETTINGS_PREFIX . '.avatar_prompt_temperature', self::DEFAULT_TEMPERATURE);

        $payload = [
            'prompt' => $this->buildPrompt($entries, $language),
            'response_format' => 'array',
            'output_type' => 'single',
            'quantity' => 1,
            'temperature' => max(0.2, min(1.8, $temperature)),
        ];

        if ($driver !== '') {
            $payload['driver'] = $driver;
        }

        if ($model !== '') {
            $payload['model'] = $model;
        }

        try {
            $response = $this->generator->generate($payload);
        } catch (\Throwable) {
            return [];
        }

        $items = $this->normalizeResult($response->result);
        if ($items === []) {
            return [];
        }

        $result = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $slot = isset($item['slot']) ? (int) $item['slot'] : null;
            if ($slot === null || $slot < 0) {
                continue;
            }

            $descriptor = trim((string) ($item['prompt_suffix'] ?? $item['descriptor'] ?? ''));
            if ($descriptor === '') {
                continue;
            }

            $type = strtolower(trim((string) ($item['avatar_type'] ?? '')));
            if (!in_array($type, ['face', 'non_face'], true)) {
                $type = null;
            }

            $result[$slot] = [
                'avatar_type' => $type,
                'prompt_suffix' => $descriptor,
                'negative_suffix' => trim((string) ($item['negative_suffix'] ?? '')),
                'diversity_key' => trim((string) ($item['diversity_key'] ?? '')),
            ];
        }

        return $result;
    }

    private function buildPrompt(array $entries, string $language): string
    {
        $maxSide45 = (float) $this->setting(
            self::PROMPT_SETTINGS_PREFIX . '.distribution.face_side_45_max_percent',
            (float) config('backpack.profile.bot_generation.avatar_prompt.distribution.face_side_45_max_percent', 4)
        );
        $maxHighQuality = (float) $this->setting(
            self::PROMPT_SETTINGS_PREFIX . '.distribution.face_high_quality_max_percent',
            (float) config('backpack.profile.bot_generation.avatar_prompt.distribution.face_high_quality_max_percent', 15)
        );
        $targetFiltered = (float) $this->setting(
            self::PROMPT_SETTINGS_PREFIX . '.distribution.face_filtered_target_percent',
            (float) config('backpack.profile.bot_generation.avatar_prompt.distribution.face_filtered_target_percent', 40)
        );
        $targetAccessories = (float) $this->setting(
            self::PROMPT_SETTINGS_PREFIX . '.distribution.face_accessories_target_percent',
            (float) config('backpack.profile.bot_generation.avatar_prompt.distribution.face_accessories_target_percent', 30)
        );

        $schema = [
            'slot' => 'int, same as input slot',
            'avatar_type' => '"face" or "non_face"',
            'prompt_suffix' => 'string, 35-80 words, highly specific unique visual directive that enriches provided base variants without contradicting them',
            'negative_suffix' => 'string, optional short negatives (<=15 words)',
            'diversity_key' => 'string, short unique token like "A7Q-urban-red-hair"',
        ];

        return implode("\n", [
            'Create a highly diverse avatar prompt plan for user profile images.',
            'Return only valid JSON array (no markdown, no commentary).',
            'Important diversity constraints:',
            '- Every slot must be visually distinct.',
            '- Input slots may contain preselected variant fields coming from admin weighted settings. Treat those fields as mandatory anchors, not optional hints.',
            '- Never contradict provided base variant fields. Use prompt_suffix only to enrich them with extra scene detail.',
            '- Avoid repeating same person archetype, same clothing, same hat color, same glasses style, same pose.',
            '- For face avatars, when face_camera_angle, face_framing, face_quality, face_filter, face_accessory, face_subject, face_background are provided, keep them intact and elaborate around them.',
            '- Faces should look like ordinary people, not fashion models.',
            '- Do not produce studio-looking portraits; keep candid real-life style.',
            '- For non_face avatars, when non_face_concept and non_face_style are provided, keep them intact and elaborate around them.',
            '- Do not replace provided non_face_concept with unrelated desk scenes, notebooks, coffee cups, gadgets, or other off-theme objects unless those objects are explicitly part of the provided concept.',
            '- Respect requested avatar_type from input when provided.',
            sprintf('- Side-angle (~45°) faces should stay around or below %.1f%%.', max(0, $maxSide45)),
            sprintf('- High-quality polished faces should stay around or below %.1f%%.', max(0, $maxHighQuality)),
            sprintf('- Filtered/edited amateur faces target around %.1f%%.', max(0, $targetFiltered)),
            sprintf('- Notable accessories (glasses/hats/scarves) target around %.1f%%.', max(0, $targetAccessories)),
            '- Keep compatibility with locale/language context: ' . strtoupper($language) . '.',
            'Output item schema:',
            json_encode($schema, JSON_UNESCAPED_UNICODE),
            'Input slots:',
            json_encode($entries, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function normalizeResult(mixed $result): array
    {
        if (is_string($result)) {
            $decoded = json_decode($result, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $result = $decoded;
            }
        }

        if (!is_array($result)) {
            return [];
        }

        if (array_is_list($result)) {
            return $result;
        }

        foreach (['items', 'data', 'results', 'plans'] as $key) {
            if (isset($result[$key]) && is_array($result[$key]) && array_is_list($result[$key])) {
                return $result[$key];
            }
        }

        return [];
    }

    private function enabled(): bool
    {
        return (bool) $this->setting(self::SETTINGS_PREFIX . '.avatar_prompt_planner_enabled', true);
    }

    private function setting(string $key, mixed $default): mixed
    {
        try {
            return \Settings::get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}
