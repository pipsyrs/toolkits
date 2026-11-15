<?php

namespace Database\Factories;

use App\Enums\BillingPeriod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_id' => 'TK-'.Str::ulid(),
            'xendit_invoice_id' => fake()->unique()->regexify('[a-f0-9]{24}'),
            'invoice_url' => 'https://checkout-staging.xendit.co/web/'.fake()->regexify('[a-f0-9]{24}'),
            'plan' => 'pro',
            'period' => BillingPeriod::Monthly,
            'kind' => 'new',
            'amount' => 29000,
            'currency' => 'IDR',
            'status' => PaymentStatus::Pending,
            'expires_at' => now()->addDay(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'payment_method' => 'BANK_TRANSFER',
            'payment_channel' => 'BCA',
            'period_start' => now(),
            'period_end' => now()->addMonthNoOverflow(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Expired,
            'expires_at' => now()->subHour(),
        ]);
    }
}
