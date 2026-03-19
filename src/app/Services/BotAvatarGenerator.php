<?php

namespace Backpack\Profile\app\Services;

use Intervention\Image\ImageManager;
use ParabellumKoval\AiContentGenerator\Services\ContentGenerator;
use ParabellumKoval\BackpackImages\Services\ImageUploader;
use ParabellumKoval\BackpackImages\Support\ImageUploadOptions;

class BotAvatarGenerator
{
    private const PROMPT_SETTINGS_PREFIX = 'profile.bot_generation.avatar_prompt';
    private const DEFAULT_DRIVER = 'gemini';
    private const DEFAULT_MODEL = 'gemini-2.5-flash-image';
    private const DEFAULT_FOLDER = 'avatars';
    private const DEFAULT_FACE_RATIO = 0.4;
    private const DEFAULT_WIDTH = 200;
    private const DEFAULT_HEIGHT = 200;
    private const DEFAULT_JPEG_QUALITY = 84;
    protected array $selectionMemory = [];

    public function __construct(
        private readonly ContentGenerator $generator,
        private readonly ImageUploader $imageUploader,
    ) {
    }

    public function generate(array $bot, string $language, string $country, ?array $plan = null): ?array
    {
        $plannedType = strtolower(trim((string) ($plan['avatar_type'] ?? '')));
        $avatarType = in_array($plannedType, ['face', 'non_face'], true) ? $plannedType : $this->pickAvatarType();
        $prompt = $this->buildPrompt($bot, $avatarType, $language, $country, $plan);

        $driver = (string) $this->setting(
            'profile.bot_generation.avatar_driver',
            config('backpack.profile.bot_generation.avatar_driver', self::DEFAULT_DRIVER)
        );
        $model = trim((string) $this->setting(
            'profile.bot_generation.avatar_model',
            config('backpack.profile.bot_generation.avatar_model', self::DEFAULT_MODEL)
        ));
        $folder = trim((string) $this->setting(
            'profile.bot_generation.avatar_folder',
            config('backpack.profile.bot_generation.avatar_folder', self::DEFAULT_FOLDER)
        ));

        if ($folder === '') {
            $folder = self::DEFAULT_FOLDER;
        }

        $payload = [
            'prompt' => $prompt,
            'driver' => $driver !== '' ? $driver : self::DEFAULT_DRIVER,
            'response_format' => 'image',
            'output_type' => 'collection',
            'quantity' => 1,
            'payload' => [
                'response_modalities' => ['IMAGE'],
            ],
        ];

        if ($model !== '') {
            $payload['model'] = $model;
        }

        $response = $this->generator->generate($payload);
        $dataUri = $this->extractDataUri($response->result);

        if (!is_string($dataUri) || trim($dataUri) === '') {
            return null;
        }

        $processedDataUri = $this->forceAvatarSize($dataUri, $this->targetWidth(), $this->targetHeight());
        $stored = $this->imageUploader->uploadFromBase64($processedDataUri, new ImageUploadOptions(folder: $folder));

        return [
            'url' => $stored->url,
            'path' => $stored->path,
            'type' => $avatarType,
            'driver' => $payload['driver'],
            'model' => $payload['model'] ?? null,
        ];
    }

    public function buildSeedPlan(
        string $avatarType,
        string $language,
        string $country,
        ?string $diversityKey = null
    ): array {
        $avatarType = in_array($avatarType, ['face', 'non_face'], true) ? $avatarType : $this->pickAvatarType();
        $scope = $this->buildScope($language, $country, $avatarType);
        $plan = [
            'avatar_type' => $avatarType,
        ];

        $diversityKey = trim((string) $diversityKey);
        if ($diversityKey !== '') {
            $plan['diversity_key'] = $diversityKey;
        }

        if ($avatarType === 'face') {
            return array_merge($plan, $this->resolveFaceValues($scope));
        }

        return array_merge($plan, $this->resolveNonFaceValues($scope));
    }

    private function pickAvatarType(): string
    {
        $ratio = (float) $this->setting(
            'profile.bot_generation.avatar_face_ratio',
            (float) config('backpack.profile.bot_generation.avatar_face_ratio', self::DEFAULT_FACE_RATIO)
        );
        $ratio = max(0.0, min(1.0, $ratio));
        $threshold = (int) round($ratio * 1000);

        return random_int(1, 1000) <= $threshold ? 'face' : 'non_face';
    }

    private function buildPrompt(array $bot, string $avatarType, string $language, string $country, ?array $plan = null): string
    {
        $age = (int) ($bot['age'] ?? 30);
        if ($age < 18 || $age > 80) {
            $age = 30;
        }

        $gender = strtolower(trim((string) ($bot['gender'] ?? '')));
        $genderLabel = match ($gender) {
            'male' => 'male',
            'female' => 'female',
            default => 'adult person',
        };

        $character = trim((string) ($bot['character'] ?? ''));
        $speechStyle = trim((string) ($bot['speech_style'] ?? ''));
        $promptSuffix = trim((string) ($plan['prompt_suffix'] ?? ''));
        $negativeSuffix = trim((string) ($plan['negative_suffix'] ?? ''));
        $diversityKey = trim((string) ($plan['diversity_key'] ?? ''));

        $scope = $this->buildScope($language, $country, $avatarType);
        $templates = $this->resolveTemplates();

        $header = array_filter([
            trim((string) ($templates['common_intro'] ?? 'Generate a realistic social avatar image.')),
            trim((string) ($templates['common_no_text'] ?? 'No logos, no watermark, no text overlays, no brand marks.')),
            sprintf('Persona context: %d years old, %s, locale %s, country %s.', $age, $genderLabel, strtoupper($language), strtoupper($country)),
        ]);

        if ($character !== '') {
            $header[] = 'Character hint: ' . $character . '.';
        }

        if ($speechStyle !== '') {
            $header[] = 'Style hint: ' . $speechStyle . '.';
        }

        if ($avatarType === 'face') {
            $values = $this->resolveFaceValues($scope, $plan);

            $body = $this->renderTemplate(
                (string) ($templates['face_template'] ?? ''),
                $values
            );

            $lines = $header;
            if ($body !== '') {
                $lines[] = $body;
            }

            if ($promptSuffix !== '') {
                $lines[] = 'Unique visual directive: ' . $promptSuffix;
            }
            $lines[] = 'Keep camera angle, quality, filter, and accessory constraints as listed above.';

            if ($negativeSuffix !== '') {
                $lines[] = 'Avoid: ' . $negativeSuffix;
            }

            if ($diversityKey !== '') {
                $lines[] = 'Diversity key: ' . $diversityKey . '.';
            }

            return implode("\n", $lines);
        }

        $values = $this->resolveNonFaceValues($scope, $plan);
        $body = $this->renderTemplate(
            (string) ($templates['non_face_template'] ?? ''),
            $values
        );

        $lines = $header;
        if ($body !== '') {
            $lines[] = $body;
        }

        if ($promptSuffix !== '') {
            $lines[] = 'Unique visual directive: ' . $promptSuffix;
        }
        $lines[] = 'Keep non-face constraint strictly.';

        if ($negativeSuffix !== '') {
            $lines[] = 'Avoid: ' . $negativeSuffix;
        }

        if ($diversityKey !== '') {
            $lines[] = 'Diversity key: ' . $diversityKey . '.';
        }

        return implode("\n", $lines);
    }

    private function resolveTemplates(): array
    {
        $defaults = (array) config('backpack.profile.bot_generation.avatar_prompt.templates', []);

        return [
            'common_intro' => (string) $this->setting(
                self::PROMPT_SETTINGS_PREFIX . '.templates.common_intro',
                $defaults['common_intro'] ?? ''
            ),
            'common_no_text' => (string) $this->setting(
                self::PROMPT_SETTINGS_PREFIX . '.templates.common_no_text',
                $defaults['common_no_text'] ?? ''
            ),
            'face_template' => (string) $this->setting(
                self::PROMPT_SETTINGS_PREFIX . '.templates.face_template',
                $defaults['face_template'] ?? ''
            ),
            'non_face_template' => (string) $this->setting(
                self::PROMPT_SETTINGS_PREFIX . '.templates.non_face_template',
                $defaults['non_face_template'] ?? ''
            ),
        ];
    }

    private function renderTemplate(string $template, array $variables): string
    {
        $template = trim($template);
        if ($template === '') {
            return '';
        }

        $replace = [];
        foreach ($variables as $key => $value) {
            $replace[':' . $key] = trim((string) $value);
        }

        return trim(strtr($template, $replace));
    }

    private function buildScope(string $language, string $country, string $avatarType): string
    {
        return strtoupper($language) . ':' . strtoupper($country) . ':' . $avatarType;
    }

    private function resolveFaceValues(string $scope, ?array $plan = null): array
    {
        $plan = $plan ?? [];

        return [
            'face_camera_angle' => trim((string) ($plan['face_camera_angle'] ?? $plan['camera_angle'] ?? $this->pickVariant('face_camera_angle', $scope))),
            'face_framing' => trim((string) ($plan['face_framing'] ?? $this->pickVariant('face_framing', $scope))),
            'face_quality' => trim((string) ($plan['face_quality'] ?? $plan['quality_profile'] ?? $this->pickVariant('face_quality', $scope))),
            'face_filter' => trim((string) ($plan['face_filter'] ?? $plan['filter_profile'] ?? $this->pickVariant('face_filter', $scope))),
            'face_accessory' => trim((string) ($plan['face_accessory'] ?? $plan['accessory_profile'] ?? $this->pickVariant('face_accessory', $scope))),
            'face_subject' => trim((string) ($plan['face_subject'] ?? $plan['subject_profile'] ?? $this->pickVariant('face_subject', $scope))),
            'face_background' => trim((string) ($plan['face_background'] ?? $this->pickVariant('face_background', $scope))),
        ];
    }

    private function resolveNonFaceValues(string $scope, ?array $plan = null): array
    {
        $plan = $plan ?? [];

        return [
            'non_face_concept' => trim((string) ($plan['non_face_concept'] ?? $this->pickVariant('non_face_concept', $scope))),
            'non_face_style' => trim((string) ($plan['non_face_style'] ?? $this->pickVariant('non_face_style', $scope))),
        ];
    }

    private function pickVariant(string $variantKey, string $scope): string
    {
        $variants = $this->resolveWeightedVariants($variantKey);
        if ($variants === []) {
            return '';
        }

        $preventImmediateRepeat = (bool) $this->setting(
            self::PROMPT_SETTINGS_PREFIX . '.prevent_immediate_repeat',
            (bool) config('backpack.profile.bot_generation.avatar_prompt.prevent_immediate_repeat', true)
        );
        $repeatPenaltyFactor = (float) $this->setting(
            self::PROMPT_SETTINGS_PREFIX . '.repeat_penalty_factor',
            (float) config('backpack.profile.bot_generation.avatar_prompt.repeat_penalty_factor', 0.35)
        );
        $repeatPenaltyFactor = max(0.0, min(1.0, $repeatPenaltyFactor));

        $memoryKey = "{$scope}:{$variantKey}";
        $lastSelected = $this->selectionMemory[$memoryKey] ?? null;
        $effectiveVariants = $variants;

        if (
            $preventImmediateRepeat
            && is_string($lastSelected)
            && $lastSelected !== ''
            && count($variants) > 1
            && $repeatPenaltyFactor < 1.0
        ) {
            foreach ($effectiveVariants as &$variant) {
                if ($variant['value'] === $lastSelected) {
                    $variant['weight'] = max(0.0, $variant['weight'] * $repeatPenaltyFactor);
                }
            }
            unset($variant);
        }

        $selected = $this->weightedRandomPick($effectiveVariants);
        $this->selectionMemory[$memoryKey] = $selected;

        return $selected;
    }

    private function resolveWeightedVariants(string $variantKey): array
    {
        $defaults = (array) config('backpack.profile.bot_generation.avatar_prompt.variants.' . $variantKey, []);
        $configured = $this->setting(self::PROMPT_SETTINGS_PREFIX . '.variants.' . $variantKey, $defaults);
        $variants = $this->normalizeWeightedVariants(is_array($configured) ? $configured : []);

        if ($variants === []) {
            $variants = $this->normalizeWeightedVariants($defaults);
        }

        if ($variants === []) {
            return [];
        }

        $totalWeight = array_sum(array_map(static fn (array $item) => $item['weight'], $variants));
        if ($totalWeight <= 0) {
            foreach ($variants as &$variant) {
                $variant['weight'] = 1.0;
            }
            unset($variant);
        }

        return $variants;
    }

    private function normalizeWeightedVariants(array $rows): array
    {
        $normalizedByValue = [];

        foreach ($rows as $row) {
            $value = '';
            $weight = 1.0;

            if (is_string($row)) {
                $value = trim($row);
            } elseif (is_array($row)) {
                $value = trim((string) ($row['text'] ?? $row['value'] ?? $row['line'] ?? ''));
                if (array_key_exists('weight', $row)) {
                    $weight = (float) $row['weight'];
                }
            }

            if ($value === '') {
                continue;
            }

            $weight = max(0.0, $weight);
            if (!array_key_exists($value, $normalizedByValue)) {
                $normalizedByValue[$value] = 0.0;
            }
            $normalizedByValue[$value] += $weight;
        }

        return collect($normalizedByValue)
            ->map(fn (float $weight, string $value) => ['value' => $value, 'weight' => $weight])
            ->values()
            ->all();
    }

    private function weightedRandomPick(array $variants): string
    {
        if ($variants === []) {
            return '';
        }

        $totalWeight = array_sum(array_map(static fn (array $item) => $item['weight'], $variants));
        if ($totalWeight <= 0) {
            $pick = $variants[array_rand($variants)];
            return (string) ($pick['value'] ?? '');
        }

        $threshold = (random_int(1, 1_000_000) / 1_000_000) * $totalWeight;
        $current = 0.0;
        $fallback = (string) ($variants[array_key_last($variants)]['value'] ?? '');

        foreach ($variants as $variant) {
            $weight = max(0.0, (float) ($variant['weight'] ?? 0.0));
            if ($weight <= 0) {
                continue;
            }

            $current += $weight;
            if ($threshold <= $current) {
                return (string) ($variant['value'] ?? $fallback);
            }
        }

        return $fallback;
    }

    private function setting(string $key, mixed $default): mixed
    {
        try {
            return \Settings::get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    private function extractDataUri(mixed $result): ?string
    {
        if (is_string($result)) {
            $candidate = trim($result);
            if (str_starts_with($candidate, 'data:image/')) {
                return $candidate;
            }

            $decoded = json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->extractDataUri($decoded);
            }

            return null;
        }

        if (!is_array($result)) {
            return null;
        }

        $dataUri = $result['data_uri'] ?? $result['dataUri'] ?? null;
        if (is_string($dataUri) && trim($dataUri) !== '') {
            return trim($dataUri);
        }

        $base64 = $result['base64'] ?? null;
        if (is_string($base64) && trim($base64) !== '') {
            $mime = trim((string) ($result['mime_type'] ?? $result['mimeType'] ?? 'image/jpeg'));
            return sprintf('data:%s;base64,%s', $mime, preg_replace('/\s+/', '', trim($base64)));
        }

        foreach (['images', 'items', 'data', 'results'] as $key) {
            if (isset($result[$key])) {
                $nested = $this->extractDataUri($result[$key]);
                if (is_string($nested) && $nested !== '') {
                    return $nested;
                }
            }
        }

        if (array_is_list($result)) {
            foreach ($result as $item) {
                $nested = $this->extractDataUri($item);
                if (is_string($nested) && $nested !== '') {
                    return $nested;
                }
            }
        }

        return null;
    }

    private function forceAvatarSize(string $dataUri, int $width, int $height): string
    {
        $parsed = $this->parseDataUri($dataUri);
        if ($parsed === null) {
            return $dataUri;
        }

        try {
            $image = ImageManager::gd()->read($parsed['binary']);
            $covered = $image->cover($width, $height);
            $quality = max(60, min(95, (int) $this->setting(
                'profile.bot_generation.avatar_jpeg_quality',
                (int) config('backpack.profile.bot_generation.avatar_jpeg_quality', self::DEFAULT_JPEG_QUALITY)
            )));
            $encoded = $covered->toJpg(quality: $quality);

            return 'data:image/jpeg;base64,' . base64_encode((string) $encoded);
        } catch (\Throwable) {
            return $dataUri;
        }
    }

    private function parseDataUri(string $dataUri): ?array
    {
        if (!preg_match('#^data:(?P<mime>[^;]+);base64,(?P<data>.+)$#si', trim($dataUri), $matches)) {
            return null;
        }

        $binary = base64_decode(preg_replace('/\s+/', '', (string) ($matches['data'] ?? '')), true);
        if ($binary === false) {
            return null;
        }

        return [
            'mime' => trim((string) ($matches['mime'] ?? 'image/jpeg')),
            'binary' => $binary,
        ];
    }

    private function targetWidth(): int
    {
        return max(64, (int) $this->setting(
            'profile.bot_generation.avatar_size.width',
            (int) config('backpack.profile.bot_generation.avatar_size.width', self::DEFAULT_WIDTH)
        ));
    }

    private function targetHeight(): int
    {
        return max(64, (int) $this->setting(
            'profile.bot_generation.avatar_size.height',
            (int) config('backpack.profile.bot_generation.avatar_size.height', self::DEFAULT_HEIGHT)
        ));
    }
}
