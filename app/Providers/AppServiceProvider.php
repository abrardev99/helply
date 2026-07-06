<?php

namespace App\Providers;

use App\Models\Agent;
use App\Models\Chunk;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteElement;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        $this->configureModels();
        $this->configureRouteBindings();
        $this->configureRateLimiting();

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    private function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::unguard();

        Relation::enforceMorphMap([
            'user' => User::class,
            'team' => Team::class,
            'membership' => Membership::class,
            'team_invitation' => TeamInvitation::class,
            'agent' => Agent::class,
            'document' => Document::class,
            'chunk' => Chunk::class,
            'conversation' => Conversation::class,
            'message' => Message::class,
        ]);
    }

    /**
     * Scope dashboard route-model bindings to the team in the URL so a agent is never
     * resolvable across teams. Public routes (e.g. the widget endpoint) have no
     * `current_team` and resolve the agent directly by id.
     */
    private function configureRouteBindings(): void
    {
        Route::bind('agent', function (string $value, RouteElement $route): Agent {
            $teamSlug = $route->parameter('current_team');

            if ($teamSlug === null) {
                return Agent::query()->findOrFail($value);
            }

            $team = Team::where('slug', $teamSlug)->firstOrFail();

            return $team->agents()->findOrFail($value);
        });
    }

    /**
     * Rate limiter for the public widget chat endpoint: throttled per agent + client IP to
     * absorb floods, plus a coarse per-agent daily cap so a distributed client pool cannot
     * run up the customer's OpenAI spend past what the per-IP limit alone would allow.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('widget-chat', $this->widgetChatLimits(...));
    }

    /**
     * @return array<int, Limit>
     */
    private function widgetChatLimits(Request $request): array
    {
        $agent = $request->route('agent');
        $agentId = $agent instanceof Agent ? $agent->id : 'unknown';

        return [
            Limit::perMinute(30)->by("{$agentId}|{$request->ip()}"),
            Limit::perDay((int) config('widget.daily_cap'))->by("agent:{$agentId}"),
        ];
    }
}
