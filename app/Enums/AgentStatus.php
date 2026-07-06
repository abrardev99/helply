<?php

namespace App\Enums;

enum AgentStatus: string
{
    case Active = 'active';
    case Paused = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Paused => __('Paused'),
        };
    }

    /** @return array<array{value: string, label: string}> */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()])
            ->values()
            ->toArray();
    }
}
