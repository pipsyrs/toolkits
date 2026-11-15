<?php

namespace App\Actions\Admin;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Services\Tier\Entitlements;
use App\Services\Tier\PlanLimits;
use App\Services\Tier\Tier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Plan matrix: name/prices per plan, fixed limit keys (Entitlements) × plans, adding and deleting paid plans.
class ManagePlanLimits
{
    // Lowercase, underscore only: the slug is a Livewire binding segment and a ?plan= value.
    public const SLUG_PATTERN = '/^[a-z][a-z0-9_]{1,19}$/';

    public const MAX_PRICE = 100000000;

    private const PRICE_RULES = ['required', 'integer', 'min:1', 'max:'.self::MAX_PRICE];

    private const NAME_RULES = ['required', 'string', 'min:2', 'max:50'];

    public function __construct(
        protected AuditLogger $audit,
        protected Entitlements $entitlements,
        protected PlanLimits $plans,
    ) {}

    /**
     * @param  array<string, mixed>  $values  limit values keyed by plan slug, then limit key
     * @param  array<string, mixed>  $meta  name (all plans) and prices (paid plans) keyed by plan slug
     *
     * @throws ValidationException
     */
    public function update(User $admin, array $values, array $meta, ?string $ip = null): void
    {
        $slugs = Plan::query()->pluck('slug')->all();
        $validated = Validator::make(['values' => $values, 'meta' => $meta], $this->matrixRules($slugs), [], $this->attributes($slugs))->validate();

        DB::transaction(function () use ($admin, $validated, $ip) {
            foreach (Plan::query()->orderBy('sort')->lockForUpdate()->get() as $plan) {
                Gate::forUser($admin)->authorize('update', $plan);

                $old = $plan->limits ?? [];
                // Keys outside the registry are kept as they are.
                $limits = [...$old, ...$this->entitlements->cast($validated['values'][$plan->slug] ?? [])];
                $changes = $this->diff($old, $limits);
                $attributes = $this->metaAttributes($plan, $validated['meta'][$plan->slug] ?? []);

                foreach ($attributes as $key => $value) {
                    if ($plan->{$key} !== $value) {
                        $changes[$key] = [$plan->{$key}, $value];
                    }
                }

                if ($changes !== []) {
                    $plan->forceFill([...$attributes, 'limits' => $limits])->save();
                    $this->audit->log($admin, 'plan.update_limits', $plan, $changes, null, $ip);
                }
            }
        });

        $this->plans->flush();
    }

    /**
     * New paid plan, for sale right away; its limits start as a copy of Free.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(User $admin, array $data, ?string $ip = null): Plan
    {
        Gate::forUser($admin)->authorize('plans.manage');

        $data['slug'] = is_string($data['slug'] ?? null) ? trim($data['slug']) : null;
        $data['name'] = is_string($data['name'] ?? null) ? trim($data['name']) : null;

        $validated = Validator::make($data, [
            'name' => self::NAME_RULES,
            'price_monthly' => self::PRICE_RULES,
            'price_yearly' => self::PRICE_RULES,
            'slug' => ['required', 'string', 'regex:'.self::SLUG_PATTERN, Rule::notIn([Tier::GUEST, Tier::FREE]), Rule::unique('plans', 'slug')],
        ], [
            'slug.regex' => 'Kode paket 2–20 karakter: huruf kecil, angka, atau garis bawah, diawali huruf.',
            'slug.unique' => 'Kode paket sudah dipakai.',
            'slug.not_in' => 'Kode paket sudah dipakai.',
        ], ['slug' => 'kode paket', 'name' => 'nama paket', 'price_monthly' => 'harga bulanan', 'price_yearly' => 'harga tahunan'])->validate();

        try {
            return DB::transaction(function () use ($admin, $validated, $ip) {
                $free = Plan::query()->where('slug', Tier::FREE)->first()?->limits ?? [];

                $plan = Plan::create([
                    'slug' => $validated['slug'],
                    'name' => $validated['name'],
                    'price_monthly' => (int) $validated['price_monthly'],
                    'price_yearly' => (int) $validated['price_yearly'],
                    'is_purchasable' => true,
                    'limits' => [...$free, ...$this->entitlements->fill($free)],
                    'sort' => (int) Plan::query()->max('sort') + 1,
                ]);

                $this->audit->log($admin, 'plan.created', $plan, [
                    'slug' => [null, $plan->slug],
                    'name' => [null, $plan->name],
                ], null, $ip);

                return $plan;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'Kode paket sudah dipakai.']);
        } finally {
            $this->plans->flush();
        }
    }

    /**
     * Paid plans only, and only while nobody holds or is paying for it.
     *
     * @throws ValidationException
     */
    public function delete(User $admin, Plan $plan, ?string $ip = null): void
    {
        Gate::forUser($admin)->authorize('update', $plan);

        if (Tier::isSystem($plan->slug)) {
            throw ValidationException::withMessages(['plan' => 'Paket Free tidak bisa dihapus.']);
        }

        DB::transaction(function () use ($admin, $plan, $ip) {
            // Locked so a checkout cannot start between the checks and the delete.
            Plan::query()->whereKey($plan->getKey())->lockForUpdate()->first();

            $active = User::query()->where('plan', $plan->slug)->where('plan_until', '>', now())->count();

            if ($active > 0) {
                throw ValidationException::withMessages(['plan' => "Paket {$plan->name} masih dipakai {$active} pelanggan aktif. Hapus setelah semua masa aktifnya berakhir."]);
            }

            if (Payment::query()->where('plan', $plan->slug)->where('status', PaymentStatus::Pending->value)->exists()) {
                throw ValidationException::withMessages(['plan' => "Masih ada pembayaran paket {$plan->name} yang menunggu. Coba lagi setelah pembayaran itu selesai atau kedaluwarsa."]);
            }

            $this->audit->log($admin, 'plan.deleted', $plan, ['slug' => [$plan->slug, null], 'name' => [$plan->name, null]], null, $ip);
            $plan->delete();
        });

        $this->plans->flush();
    }

    /**
     * @param  list<string>  $slugs
     * @return array<string, mixed>
     */
    private function matrixRules(array $slugs): array
    {
        $rules = [];

        foreach ($slugs as $slug) {
            $rules += $this->entitlements->rules("values.{$slug}.");
            $rules["meta.{$slug}.name"] = self::NAME_RULES;

            if (! Tier::isSystem($slug)) {
                $rules["meta.{$slug}.price_monthly"] = self::PRICE_RULES;
                $rules["meta.{$slug}.price_yearly"] = self::PRICE_RULES;
            }
        }

        return $rules;
    }

    /**
     * Free plans stay at Rp0; only their name is editable.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function metaAttributes(Plan $plan, array $meta): array
    {
        $attributes = ['name' => trim((string) ($meta['name'] ?? $plan->name))];

        if (! Tier::isSystem($plan->slug)) {
            $attributes['price_monthly'] = (int) $meta['price_monthly'];
            $attributes['price_yearly'] = (int) $meta['price_yearly'];
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function diff(array $old, array $new): array
    {
        $changes = [];

        foreach ($new as $key => $value) {
            if (! array_key_exists($key, $old) || $old[$key] !== $value) {
                $changes[$key] = [$old[$key] ?? null, $value];
            }
        }

        return $changes;
    }

    /**
     * @param  list<string>  $slugs
     * @return array<string, string>
     */
    private function attributes(array $slugs): array
    {
        $attributes = [];

        foreach ($slugs as $slug) {
            $attributes["meta.{$slug}.name"] = 'nama paket';
            $attributes["meta.{$slug}.price_monthly"] = 'harga bulanan';
            $attributes["meta.{$slug}.price_yearly"] = 'harga tahunan';

            foreach ($this->entitlements->all() as $key => $entry) {
                $attributes["values.{$slug}.{$key}"] = $entry['label'];
            }
        }

        return $attributes;
    }
}
