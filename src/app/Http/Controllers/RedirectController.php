<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Services\Links\BlockedDomainList;
use App\Services\Links\HostPresenter;
use App\Services\Tier\PlanLimits;
use App\Services\Tier\Tier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RedirectController extends Controller
{
    public function __invoke(Request $request, string $code, BlockedDomainList $blocked, HostPresenter $hosts, PlanLimits $plans): Response
    {
        $link = Link::query()
            ->select(['id', 'target_url', 'target_host', 'expires_at', 'disabled_at', 'created_tier'])
            ->where('code', Str::lower($code))
            ->first();

        // Re-check host: domains may be blocked after the link was created.
        if ($link === null || $link->disabled_at !== null || $blocked->isBlocked($link->target_host)) {
            return $this->noStore(response()->view('links.unavailable', ['status' => 404], 404));
        }

        if ($link->expires_at !== null && $link->expires_at->isPast()) {
            return $this->noStore(response()->view('links.unavailable', ['status' => 410], 410));
        }

        if (! preg_match((string) config('toolkits.bot_user_agent_regex'), (string) $request->userAgent())) {
            $id = $link->getKey();

            // After the response is sent.
            defer(fn () => Link::query()->whereKey($id)->update([
                'clicks_total' => DB::raw('clicks_total + 1'),
                'last_clicked_at' => now(),
            ]));
        }

        // Plan of the creator at creation time; links made without an account follow Free.
        $plan = $link->created_tier === Tier::GUEST ? Tier::FREE : (string) $link->created_tier;

        if ((bool) ($plans->limits($plan)['interstitial'] ?? false)) {
            return $this->noStore(response()->view('links.interstitial', [
                'targetUrl' => $link->target_url,
                'seconds' => (int) config('toolkits.interstitial_seconds'),
                ...$hosts->describe($link->target_url),
            ]));
        }

        return $this->noStore(redirect()->away($link->target_url, 302));
    }

    private function noStore(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer-when-downgrade');

        return $response;
    }
}
