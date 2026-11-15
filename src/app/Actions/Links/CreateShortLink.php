<?php

namespace App\Actions\Links;

use App\Exceptions\QuotaExceededException;
use App\Models\Link;
use App\Models\User;
use App\Rules\SafeTargetUrl;
use App\Rules\ValidAlias;
use App\Services\Links\ShortCodeGenerator;
use App\Services\Quota\QuotaService;
use App\Services\Tier\PlanLimits;
use App\Services\Tier\Tier;
use App\Services\Tier\TierResolver;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateShortLink
{
    public const METRIC = 'links';

    public function __construct(
        protected TierResolver $tiers,
        protected PlanLimits $plans,
        protected QuotaService $quota,
        protected ShortCodeGenerator $codes,
    ) {}

    /**
     * @param  array{target_url?: mixed, alias?: mixed, expires_at?: mixed}  $data
     *
     * @throws ValidationException
     * @throws QuotaExceededException
     */
    public function handle(?User $user, string $guestId, string $ip, array $data): Link
    {
        $tier = $this->tiers->forUser($user);
        $limits = $this->plans->for($tier);

        $input = [
            'target_url' => is_string($data['target_url'] ?? null) ? trim($data['target_url']) : null,
            'alias' => is_string($data['alias'] ?? null) && trim($data['alias']) !== '' ? Str::lower(trim($data['alias'])) : null,
            'expires_at' => is_string($data['expires_at'] ?? null) && $data['expires_at'] !== '' ? $data['expires_at'] : null,
        ];

        // custom_alias_max: null = unlimited, 0 or missing = not allowed. Aliases are counted per account, so they need one.
        $aliasAllowed = ! $tier->isGuest()
            && array_key_exists('custom_alias_max', $limits)
            && ($limits['custom_alias_max'] === null || (int) $limits['custom_alias_max'] > 0);

        if ($input['alias'] !== null && ! $aliasAllowed) {
            throw ValidationException::withMessages(['alias' => 'Alias kustom hanya untuk akun terdaftar dan terverifikasi.']);
        }

        if ($input['expires_at'] !== null && empty($limits['link_custom_expiry'])) {
            throw ValidationException::withMessages(['expires_at' => 'Atur kedaluwarsa hanya untuk akun Pro.']);
        }

        $input['expires_at'] = $this->toUtc($input['expires_at']);

        $validated = Validator::make($input, self::rules(), [], self::attributes())->validate();

        $expiresAt = $this->expiresAt($limits, $validated['expires_at'] ?? null);
        $alias = $validated['alias'] ?? null;
        $targetUrl = $validated['target_url'];
        $targetHost = SafeTargetUrl::normalizeHost((string) parse_url($targetUrl, PHP_URL_HOST));

        try {
            return DB::transaction(function () use ($user, $tier, $limits, $guestId, $ip, $alias, $targetUrl, $targetHost, $expiresAt) {
                if ($user !== null && ! $tier->isGuest()) {
                    $this->checkAccountLimits($user, $limits, $alias !== null);
                }

                $subjects = $this->quota->subjectsFor($user, $tier, $guestId, $ip);

                if (! $this->quota->reserve($subjects, self::METRIC, 1, $this->quota->limitsFor($subjects, self::METRIC, $limits))) {
                    throw new QuotaExceededException('Kuota link harian habis. Upgrade ke Pro untuk tanpa batas harian.', self::METRIC);
                }

                return Link::create([
                    'user_id' => $user?->getKey(),
                    'guest_id' => $user === null ? $guestId : null,
                    'code' => $alias ?? $this->codes->generate(),
                    'is_custom' => $alias !== null,
                    'target_url' => $targetUrl,
                    'target_host' => $targetHost,
                    'expires_at' => $expiresAt,
                    'created_tier' => $tier->isGuest() ? Tier::GUEST : $tier->value,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['alias' => 'Alias sudah dipakai.']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'target_url' => ['required', 'string', 'max:'.SafeTargetUrl::MAX_LENGTH, new SafeTargetUrl],
            'alias' => ['nullable', 'string', new ValidAlias],
            'expires_at' => ['nullable', 'date', 'after:now', 'before_or_equal:'.now()->addYears(5)->toDateTimeString()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'target_url' => 'URL tujuan',
            'alias' => 'alias',
            'expires_at' => 'tanggal kedaluwarsa',
        ];
    }

    // Serialised per user via row lock (ADR-002).
    protected function checkAccountLimits(User $user, array $limits, bool $isCustom): void
    {
        User::query()->whereKey($user->getKey())->lockForUpdate()->first();

        $activeMax = $limits['links_active_max'] ?? null;

        if ($activeMax !== null && Link::query()->where('user_id', $user->getKey())->active()->count() >= $activeMax) {
            throw ValidationException::withMessages([
                'target_url' => "Batas {$activeMax} link aktif tercapai. Hapus link lama atau upgrade ke Pro.",
            ]);
        }

        $aliasMax = $limits['custom_alias_max'] ?? null;

        if ($isCustom && $aliasMax !== null && Link::query()->where('user_id', $user->getKey())->where('is_custom', true)->count() >= $aliasMax) {
            throw ValidationException::withMessages([
                'alias' => "Batas {$aliasMax} alias kustom tercapai. Upgrade ke Pro untuk alias tanpa batas.",
            ]);
        }
    }

    protected function expiresAt(array $limits, ?string $input): ?Carbon
    {
        if (($limits['link_lifetime_days'] ?? null) !== null) {
            return now()->addDays((int) $limits['link_lifetime_days']);
        }

        if ($input !== null && ! empty($limits['link_custom_expiry'])) {
            return Carbon::parse($input, 'UTC');
        }

        return null;
    }

    // Form input is local time (WIB); validate and store as UTC.
    protected function toUtc(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value, config('toolkits.timezone'))->utc()->toDateTimeString();
        } catch (\Throwable) {
            return $value;
        }
    }
}
