<?php

namespace Backpack\Profile\app\Console\Commands;

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
        {--language=* : Language codes (cs, ua, ru, en)}
        {--country=* : Country codes (CZ, UA)}
        {--driver= : AI driver name}
        {--model= : AI model name}
        {--temperature= : Model temperature}
        {--max-tokens= : Max tokens for the response}
        {--force : Force provider even if marked unavailable}
        {--email-domain=bot.local : Email domain for generated users}
        {--verified : Mark emails as verified}
        {--unverified : Leave emails unverified}
        {--prompt-path= : Path to prompt template file}
        {--dry-run : Do not write to the database}';

    protected $description = 'Generate bot users using the AI content generator.';

    private const ALLOWED_LANGUAGES = ['cs', 'ua', 'ru', 'en'];
    private const LANGUAGE_LABELS = [
        'cs' => 'Czech',
        'ua' => 'Ukrainian',
        'ru' => 'Russian',
        'en' => 'English',
    ];
    private const ALLOWED_COUNTRIES = ['CZ', 'UA'];
    private const DEFAULT_PROMPT_PATH = __DIR__ . '/../../../resources/prompts/bot-user-generator.txt';
    private const DEFAULT_AGE_MIN = 18;
    private const DEFAULT_AGE_MAX = 65;

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
            $languages = $this->normalizeOptionList($this->option('language'), self::ALLOWED_LANGUAGES);
            $countries = $this->normalizeOptionList($this->option('country'), self::ALLOWED_COUNTRIES, true);
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        if ($languages === []) {
            $languages = self::ALLOWED_LANGUAGES;
        }
        if ($countries === []) {
            $countries = self::ALLOWED_COUNTRIES;
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
        $verifyEmail = $this->resolveVerifyEmailOption();
        $dryRun = (bool) $this->option('dry-run');

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

        $created = 0;
        $countryIndex = 0;

        foreach ($languageCounts as $language => $languageTotal) {
            if ($languageTotal < 1) {
                continue;
            }

            $remaining = $languageTotal;

            while ($remaining > 0) {
                $batchCount = min($batchSize, $remaining);
                $prompt = $this->buildPrompt($promptTemplate, $language);

                $payload = [
                    'prompt' => $prompt,
                    'response_format' => 'array',
                    'output_type' => 'collection',
                    'quantity' => $batchCount,
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
                    $this->error('AI response contained no items.');
                    return self::FAILURE;
                }

                if (count($items) !== $batchCount) {
                    $this->warn(sprintf(
                        'AI returned %d items for batch %d (language=%s).',
                        count($items),
                        $batchCount,
                        $language,
                    ));
                }

                foreach ($items as $item) {
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
                        $progress->advance();
                        continue;
                    }

                    $user = $this->createBotUser($bot, $language, $country, $verifyEmail, $emailDomain);
                    if ($user) {
                        $created++;
                        $progress->advance();
                    }
                }

                $remaining -= count($items);

                if (count($items) === 0) {
                    break;
                }
            }
        }

        $progress->finish();
        $this->newLine(2);
        $this->info("Created {$created} bot users.");

        return self::SUCCESS;
    }

    private function normalizeOptionList(mixed $value, array $allowed, bool $upper = false): array
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

        $items = array_values(array_unique($items));
        if ($items === []) {
            return [];
        }

        $allowed = $upper ? array_map('strtoupper', $allowed) : array_map('strtolower', $allowed);
        $invalid = array_diff($items, $allowed);

        if ($invalid) {
            $label = $upper ? 'countries' : 'languages';
            throw new \InvalidArgumentException(sprintf(
                'Unsupported %s: %s.',
                $label,
                implode(', ', $invalid),
            ));
        }

        return $items;
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
        $languageName = self::LANGUAGE_LABELS[$language] ?? strtoupper($language);

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

        return array_is_list($result) ? $result : [$result];
    }

    private function normalizeBotItem(array $item): ?array
    {
        $firstName = $this->firstString($item, ['first_name', 'first', 'firstname']);
        $lastName = $this->firstString($item, ['last_name', 'last', 'lastname']);
        $displayName = $this->firstString($item, ['display_name', 'name']);
        $gender = $this->normalizeGender($this->firstString($item, ['gender', 'sex']));
        $age = (int) ($item['age'] ?? 0);
        $character = $this->firstString($item, ['character', 'personality']);

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

        return [
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'display_name' => $displayName,
            'gender' => $gender,
            'age' => $age,
            'character' => $character !== '' ? $character : null,
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

    private function pickCountry(array $countries, int $index): string
    {
        if ($countries === []) {
            return self::ALLOWED_COUNTRIES[0];
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

    private function createBotUser(array $bot, string $language, string $country, bool $verifyEmail, string $emailDomain): ?object
    {
        $userClass = app(\Backpack\Profile\app\Services\Profile::class)->userModel();
        $email = $this->generateUniqueEmail($userClass, $emailDomain);
        $password = Str::random(32);

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
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
