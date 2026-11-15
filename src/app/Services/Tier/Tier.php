<?php

namespace App\Services\Tier;

// Visitor's plan. Free is the only system plan and also covers visitors without a (verified) account;
// every other plan slug is a paid tier (rows in plans).
final readonly class Tier
{
    public const FREE = 'free';

    // Not a plan: marks links made without an account (links.created_tier) and stays reserved as a slug.
    public const GUEST = 'guest';

    private const FREE_LABEL = 'Free';

    public function __construct(public string $value, private string $name, private bool $anonymous = false) {}

    // Free limits; quota is counted per guest cookie and IP instead of per account.
    public static function guest(): self
    {
        return new self(self::FREE, self::FREE_LABEL, anonymous: true);
    }

    public static function free(): self
    {
        return new self(self::FREE, self::FREE_LABEL);
    }

    // Reserved slugs that are never a paid tier.
    public static function isSystem(string $slug): bool
    {
        return $slug === self::FREE || $slug === self::GUEST;
    }

    public function label(): string
    {
        return $this->name;
    }

    public function isGuest(): bool
    {
        return $this->anonymous;
    }

    public function isPaid(): bool
    {
        return ! self::isSystem($this->value);
    }
}
