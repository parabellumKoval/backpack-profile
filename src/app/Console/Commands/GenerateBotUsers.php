<?php

namespace Backpack\Profile\app\Console\Commands;

use App\Support\GenerationRunReporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ParabellumKoval\AiContentGenerator\Services\ContentGenerator;

class GenerateBotUsers extends Command
{
    protected $signature = 'profile:generate-bots
        {count=50 : Total number of bots to generate}
        {--batch=50 : Batch size per AI request}
        {--language=* : Language codes (uk, cs, ru, en, de, es, ...)}
        {--country=* : Country codes (UA, CZ, DE, ES, ...)}
        {--driver= : AI driver name}
        {--model= : AI model name}
        {--temperature= : Model temperature}
        {--max-tokens= : Max tokens for the response}
        {--force : Force provider even if marked unavailable}
        {--email-domain=bot.local : Email domain for generated users}
        {--password= : Password for generated users}
        {--verified : Mark emails as verified}
        {--unverified : Leave emails unverified}
        {--run-id= : Internal generation run ID for progress tracking}
        {--prompt-path= : Path to prompt template file}
        {--dry-run : Do not write to the database}';

    protected $description = 'Generate bot users using the AI content generator.';

    private const LANGUAGE_ALIASES = [
        'ua' => 'uk',
    ];
    private const DEFAULT_LANGUAGE_LABELS = [
        'uk' => 'Ukrainian',
        'cs' => 'Czech',
        'ru' => 'Russian',
        'en' => 'English',
        'de' => 'German',
        'es' => 'Spanish',
    ];
    private const DEFAULT_COUNTRIES = ['CZ', 'UA'];
    private const DEFAULT_PROMPT_PATH = __DIR__ . '/../../../resources/prompts/bot-user-generator.txt';
    private const DEFAULT_AGE_MIN = 18;
    private const DEFAULT_AGE_MAX = 60;
    private const DEFAULT_PASSWORD = 'bot228vivadzen';
    private const MAX_EMPTY_BATCH_ATTEMPTS = 3;

    public function handle(ContentGenerator $generator): int
    {
        $total = (int) $this->argument('count');
        if ($total < 1) {
            $this->error('Count must be greater than 0.');
            return self::FAILURE;
        }

        $batchSize = (int) $this->option('batch');
        if ($batchSize < 1) {
            $batchSize = $total;
        }
        $batchSize = min($batchSize, $total);

        try {
            $languages = $this->normalizeLanguages($this->option('language'));
            $countries = $this->normalizeCountries($this->option('country'));
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        if ($languages === []) {
            $languages = $this->defaultLanguages();
        }
        if ($countries === []) {
            $countries = self::DEFAULT_COUNTRIES;
        }

        $promptPath = (string) ($this->option('prompt-path') ?: self::DEFAULT_PROMPT_PATH);
        if (!is_file($promptPath)) {
            $this->error("Prompt file not found: {$promptPath}");
            return self::FAILURE;
        }

        $promptTemplate = trim((string) file_get_contents($promptPath));
        if ($promptTemplate === '') {
            $this->error('Prompt file is empty.');
            return self::FAILURE;
        }

        $emailDomain = trim((string) $this->option('email-domain')) ?: 'bot.local';
        $defaultPassword = (string) config('backpack.profile.bot_generation.default_password', self::DEFAULT_PASSWORD);
        $password = trim((string) ($this->option('password') ?: $defaultPassword));
        $verifyEmail = $this->resolveVerifyEmailOption();
        $dryRun = (bool) $this->option('dry-run');
        $reporter = GenerationRunReporter::fromOption($this->option('run-id'));

        $languageCounts = $this->allocateCounts($total, $languages);

        $this->info(sprintf(
            'Generating %d bots (batch=%d, languages=%s, countries=%s).',
            $total,
            $batchSize,
            implode(', ', $languages),
            implode(', ', $countries),
        ));

        if ($dryRun) {
            $this->info('Dry run mode: no users will be saved.');
        }

        $progress = $this->output->createProgressBar($total);
        $progress->start();
        $reporter->setTotal($total, [
            'requested_count' => $total,
            'languages' => $languages,
            'countries' => $countries,
            'created_count' => 0,
            'dry_run' => $dryRun,
        ]);

        $created = 0;
        $countryIndex = 0;

        foreach ($languageCounts as $language => $languageTotal) {
            if ($languageTotal < 1) {
                continue;
            }

            $remaining = $languageTotal;
            $emptyBatches = 0;

            while ($remaining > 0 && $emptyBatches < self::MAX_EMPTY_BATCH_ATTEMPTS) {
                $requestedCount = min($batchSize, $remaining);
                $prompt = $this->buildPrompt($promptTemplate, $language);

                $payload = [
                    'prompt' => $prompt,
                    'response_format' => 'array',
                    'output_type' => 'collection',
                    'quantity' => $requestedCount,
                ];

                $driver = $this->option('driver');
                if ($driver) {
                    $payload['driver'] = $driver;
                }

                $model = $this->option('model');
                if ($model) {
                    $payload['model'] = $model;
                }

                $temperature = $this->option('temperature');
                if ($temperature !== null && $temperature !== '') {
                    $payload['temperature'] = (float) $temperature;
                }

                $maxTokens = $this->option('max-tokens');
                if ($maxTokens !== null && $maxTokens !== '') {
                    $payload['max_tokens'] = (int) $maxTokens;
                }

                if ($this->option('force')) {
                    $payload['force'] = true;
                }

                try {
                    $response = $generator->generate($payload);
                } catch (\Throwable $exception) {
                    $this->error('AI generation failed: ' . $exception->getMessage());
                    return self::FAILURE;
                }

                $items = $this->normalizeResult($response->result);
                if ($items === []) {
                    $emptyBatches++;
                    $this->warn(sprintf(
                        'AI response contained no valid items for language=%s (attempt %d/%d).',
                        $language,
                        $emptyBatches,
                        self::MAX_EMPTY_BATCH_ATTEMPTS,
                    ));
                    continue;
                }

                if (count($items) !== $requestedCount) {
                    $this->warn(sprintf(
                        'AI returned %d items for batch %d (language=%s).',
                        count($items),
                        $requestedCount,
                        $language,
                    ));
                }

                $processedInBatch = 0;

                foreach ($items as $item) {
                    if ($processedInBatch >= $requestedCount || $remaining < 1) {
                        break;
                    }

                    $bot = $this->normalizeBotItem($item);
                    if ($bot === null) {
                        $this->warn('Skipping invalid AI item.');
                        continue;
                    }

                    $country = $this->pickCountry($countries, $countryIndex);
                    $countryIndex++;

                    if ($dryRun) {
                        $this->line($this->formatPreview($bot, $language, $country));
                        $created++;
                        $processedInBatch++;
                        $remaining--;
                        $progress->advance();
                        $reporter->setProgress($created, null, [
                            'created_count' => $created,
                            'current_language' => $language,
                            'last_country' => $country,
                        ]);
                        continue;
                    }

                    $user = $this->createBotUser($bot, $language, $country, $verifyEmail, $emailDomain, $password);
                    if ($user) {
                        $created++;
                        $processedInBatch++;
                        $remaining--;
                        $progress->advance();
                        $reporter->setProgress($created, null, [
                            'created_count' => $created,
                            'current_language' => $language,
                            'last_country' => $country,
                        ]);
                    }
                }

                if ($processedInBatch === 0) {
                    $emptyBatches++;
                } else {
                    $emptyBatches = 0;
                }
            }

            if ($remaining > 0) {
                $this->warn(sprintf(
                    'Stopped before reaching the requested amount for language=%s. Missing: %d.',
                    $language,
                    $remaining,
                ));
            }
        }

        $progress->finish();
        $this->newLine(2);
        $label = $dryRun ? 'Previewed' : 'Created';
        $this->info("{$label} {$created} bot users.");
        $reporter->merge([
            'created_count' => $created,
        ], [
            'created_count' => $created,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }

    private function defaultLanguages(): array
    {
        $supported = (array) config('app.supported_locales', []);
        $supported = array_values(array_unique(array_map(fn ($value) => $this->normalizeLanguageCode((string) $value), $supported)));
        $supported = array_values(array_filter($supported));

        return $supported !== [] ? $supported : array_keys(self::DEFAULT_LANGUAGE_LABELS);
    }

    private function normalizeLanguages(mixed $value): array
    {
        $items = $this->splitOptionValues($value, false);
        if ($items === []) {
            return [];
        }

        $supported = $this->defaultLanguages();
        $normalized = [];

        foreach ($items as $item) {
            $code = $this->normalizeLanguageCode($item);
            if ($code === null) {
                throw new \InvalidArgumentException(sprintf('Unsupported languages: %s.', $item));
            }

            if (!in_array($code, $supported, true)) {
                throw new \InvalidArgumentException(sprintf('Unsupported languages: %s.', $item));
            }

            $normalized[] = $code;
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeCountries(mixed $value): array
    {
        $items = $this->splitOptionValues($value, true);
        if ($items === []) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (!preg_match('/^[A-Z]{2}$/', $item)) {
                throw new \InvalidArgumentException(sprintf('Unsupported countries: %s.', $item));
            }

            $normalized[] = $item;
        }

        return array_values(array_unique($normalized));
    }

    private function splitOptionValues(mixed $value, bool $upper = false): array
    {
        $values = is_array($value) ? $value : (is_string($value) ? [$value] : []);
        $items = [];

        foreach ($values as $entry) {
            foreach (explode(',', (string) $entry) as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                $items[] = $upper ? strtoupper($part) : strtolower($part);
            }
        }

        return array_values(array_unique($items));
    }

    private function allocateCounts(int $total, array $languages): array
    {
        $count = count($languages);
        if ($count === 0) {
            return [];
        }

        $base = intdiv($total, $count);
        $remainder = $total % $count;
        $result = [];

        foreach ($languages as $index => $language) {
            $result[$language] = $base + ($index < $remainder ? 1 : 0);
        }

        return $result;
    }

    private function buildPrompt(string $template, string $language): string
    {
        $languageName = $this->languageLabels()[$language] ?? strtoupper($language);

        return strtr($template, [
            '{{language}}' => $language,
            '{{language_name}}' => $languageName,
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

        foreach (['profiles', 'items', 'data', 'results'] as $key) {
            if (array_key_exists($key, $result) && is_array($result[$key]) && array_is_list($result[$key])) {
                return $result[$key];
            }
        }

        if (count($result) === 1) {
            $value = reset($result);
            if (is_array($value) && array_is_list($value)) {
                return $value;
            }
        }

        return [$result];
    }

    private function normalizeBotItem(array $item): ?array
    {
        $firstName = $this->firstString($item, ['first_name', 'first', 'firstname']);
        $lastName = $this->firstString($item, ['last_name', 'last', 'lastname']);
        $displayName = $this->firstString($item, ['display_name', 'name']);
        $gender = $this->normalizeGender($this->firstString($item, ['gender', 'sex']));
        $age = (int) ($item['age'] ?? 0);
        $character = $this->firstString($item, ['character', 'personality']);
        $literacyLevel = $this->firstInt($item, ['literacy_level', 'literacy', 'grammar_level', 'grammar_score']);
        $speechStyle = $this->firstString($item, ['speech_style', 'speaking_style', 'style']);
        $emojiUsage = $this->firstString($item, ['emoji_usage', 'emojis', 'smileys_usage', 'smileys']);
        $punctuationUsage = $this->firstString($item, ['punctuation_usage', 'punctuation', 'punctuation_style']);
        $messageLength = $this->firstString($item, ['message_length', 'message_length_style', 'length']);

        if ($displayName === '') {
            $displayName = trim($firstName . ' ' . $lastName);
        }

        if ($displayName === '') {
            return null;
        }

        if ($firstName === '') {
            $firstName = $displayName;
        }

        if ($age < self::DEFAULT_AGE_MIN || $age > self::DEFAULT_AGE_MAX) {
            $age = random_int(self::DEFAULT_AGE_MIN, self::DEFAULT_AGE_MAX);
        }

        if ($gender === '') {
            $gender = random_int(0, 1) === 0 ? 'male' : 'female';
        }

        $literacyLevel = $this->normalizeLiteracyLevel($literacyLevel);

        return [
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'display_name' => $displayName,
            'gender' => $gender,
            'age' => $age,
            'character' => $character !== '' ? $character : null,
            'literacy_level' => $literacyLevel,
            'speech_style' => $speechStyle !== '' ? $speechStyle : null,
            'emoji_usage' => $emojiUsage !== '' ? $emojiUsage : null,
            'punctuation_usage' => $punctuationUsage !== '' ? $punctuationUsage : null,
            'message_length' => $messageLength !== '' ? $messageLength : null,
        ];
    }

    private function firstString(array $item, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $item)) {
                continue;
            }

            $value = $item[$key];
            if ($value === null) {
                continue;
            }

            $string = trim((string) $value);
            if ($string !== '') {
                return $string;
            }
        }

        return '';
    }

    private function firstInt(array $item, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $item)) {
                continue;
            }

            $value = $this->normalizeInt($item[$key]);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function normalizeInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value);
        }

        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }

        if (preg_match('/-?\d+/', $string, $matches) !== 1) {
            return null;
        }

        return (int) $matches[0];
    }

    private function normalizeGender(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace(['.', ',', ';'], '', $normalized);

        return match ($normalized) {
            'm', 'male', 'man', 'masculine' => 'male',
            'f', 'female', 'woman', 'feminine' => 'female',
            default => '',
        };
    }

    private function normalizeLiteracyLevel(?int $level): int
    {
        if ($level === null) {
            return random_int(3, 10);
        }

        return max(0, min(10, $level));
    }

    private function pickCountry(array $countries, int $index): string
    {
        if ($countries === []) {
            return self::DEFAULT_COUNTRIES[0];
        }

        $position = $index % count($countries);
        return $countries[$position];
    }

    private function resolveVerifyEmailOption(): bool
    {
        if ($this->option('unverified')) {
            return false;
        }

        if ($this->option('verified')) {
            return true;
        }

        return true;
    }

    private function normalizeLanguageCode(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return null;
        }

        if (isset(self::LANGUAGE_ALIASES[$value])) {
            $value = self::LANGUAGE_ALIASES[$value];
        }

        return preg_match('/^[a-z]{2,8}$/', $value) ? $value : null;
    }

    private function languageLabels(): array
    {
        $labels = self::DEFAULT_LANGUAGE_LABELS;

        foreach ($this->defaultLanguages() as $locale) {
            if (!isset($labels[$locale])) {
                $labels[$locale] = strtoupper($locale);
            }
        }

        return $labels;
    }

    private function createBotUser(
        array $bot,
        string $language,
        string $country,
        bool $verifyEmail,
        string $emailDomain,
        string $password
    ): ?object
    {
        $userClass = app(\Backpack\Profile\app\Services\Profile::class)->userModel();
        $email = $this->generateUniqueEmail($userClass, $emailDomain);
        $password = $password !== '' ? $password : self::DEFAULT_PASSWORD;

        try {
            return DB::transaction(function () use ($userClass, $email, $password, $bot, $language, $country, $verifyEmail) {
                $user = new $userClass();
                $user->name = $bot['display_name'];
                $user->email = $email;
                $user->password = Hash::make($password);

                if ($verifyEmail) {
                    $user->email_verified_at = now();
                }

                $user->save();
                $user->load('profile');

                $profile = $user->profile;
                if (!$profile) {
                    $profile = app('backpack.profile.profile_factory')->makeFor($user);
                    $user->profile()->save($profile);
                }

                $firstName = $bot['first_name'] ?? null;
                $lastName = $bot['last_name'] ?? null;
                $fullName = trim(trim((string) $firstName) . ' ' . trim((string) $lastName));

                $profile->first_name = $firstName;
                $profile->last_name = $lastName;
                $profile->full_name = $fullName !== '' ? $fullName : null;
                $profile->locale = $language;
                $profile->country_code = strtoupper($country);
                $profile->role = 'bot';
                $profile->birthdate = $this->birthdateFromAge($bot['age'] ?? null);

                $profile->setRolePayload('bot', [
                    'character' => $bot['character'] ?? null,
                    'gender' => $bot['gender'] ?? null,
                    'age' => $bot['age'] ?? null,
                    'literacy_level' => $bot['literacy_level'] ?? null,
                    'speech_style' => $bot['speech_style'] ?? null,
                    'emoji_usage' => $bot['emoji_usage'] ?? null,
                    'punctuation_usage' => $bot['punctuation_usage'] ?? null,
                    'message_length' => $bot['message_length'] ?? null,
                ]);

                $profile->save();

                return $user;
            });
        } catch (\Throwable $exception) {
            $this->warn('Failed to create bot user: ' . $exception->getMessage());
            return null;
        }
    }

    private function generateUniqueEmail(string $userClass, string $domain): string
    {
        $domain = ltrim(trim($domain), '@');
        if ($domain === '') {
            $domain = 'bot.local';
        }

        do {
            $local = 'bot' . Str::lower(Str::random(10));
            $email = $local . '@' . $domain;
        } while ($userClass::query()->where('email', $email)->exists());

        return $email;
    }

    private function birthdateFromAge(?int $age): ?\Carbon\CarbonInterface
    {
        if (!$age) {
            return null;
        }

        $age = max(self::DEFAULT_AGE_MIN, min(self::DEFAULT_AGE_MAX, $age));

        return now()->subYears($age)->subDays(random_int(0, 364));
    }

    private function formatPreview(array $bot, string $language, string $country): string
    {
        $payload = [
            'display_name' => $bot['display_name'],
            'first_name' => $bot['first_name'],
            'last_name' => $bot['last_name'],
            'language' => $language,
            'country' => $country,
            'gender' => $bot['gender'],
            'age' => $bot['age'],
            'character' => $bot['character'],
            'literacy_level' => $bot['literacy_level'],
            'speech_style' => $bot['speech_style'],
            'emoji_usage' => $bot['emoji_usage'],
            'punctuation_usage' => $bot['punctuation_usage'],
            'message_length' => $bot['message_length'],
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
