<?php

namespace App\Actions\Support;

use App\Enums\SupportReportCategory;
use App\Models\SupportReport;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubmitSupportReport
{
    public const SUBJECT_MAX = 120;

    public const MESSAGE_MIN = 10;

    public const MESSAGE_MAX = 5000;

    // Per user, per hour: enough for real issues, blocks flooding the CS queue.
    public const MAX_PER_HOUR = 5;

    /**
     * @param  array{category?: mixed, subject?: mixed, message?: mixed}  $input
     */
    public function handle(User $user, array $input): SupportReport
    {
        $data = Validator::make([
            'category' => $input['category'] ?? null,
            'subject' => is_string($input['subject'] ?? null) ? trim($input['subject']) : null,
            'message' => is_string($input['message'] ?? null) ? trim($input['message']) : null,
        ], [
            'category' => ['required', Rule::enum(SupportReportCategory::class)],
            'subject' => ['required', 'string', 'max:'.self::SUBJECT_MAX, 'regex:/^[^\p{C}]+$/u'],
            'message' => ['required', 'string', 'min:'.self::MESSAGE_MIN, 'max:'.self::MESSAGE_MAX],
        ], [], ['category' => 'kategori', 'subject' => 'judul', 'message' => 'pesan'])->validate();

        $key = 'support-report:'.$user->getKey();

        // Counted before the insert (atomic increment) so parallel submits cannot slip past the limit.
        if (RateLimiter::hit($key, 3600) > self::MAX_PER_HOUR) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));

            throw ValidationException::withMessages(['report' => "Terlalu banyak laporan. Coba lagi dalam {$minutes} menit."]);
        }

        return $user->supportReports()->create($data);
    }
}
