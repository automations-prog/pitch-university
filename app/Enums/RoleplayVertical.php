<?php

namespace App\Enums;

use App\Services\AcaRoleplayConsumerPrompt;
use App\Services\AcaRoleplayGrader;
use App\Services\AcaRoleplayPersonaGenerator;
use App\Services\AcaRoleplayScript;
use App\Services\RoleplayConsumerPrompt;
use App\Services\RoleplayGrader;
use App\Services\RoleplayPersonaGenerator;
use App\Services\RoleplayScript;

/**
 * The campaign a roleplay call practices. Every vertical runs on the same
 * call engine; each one brings its own script, personas, consumer prompt
 * and grading rules.
 */
enum RoleplayVertical: string
{
    case Medicare = 'medicare';
    case Aca = 'aca';

    public function label(): string
    {
        return match ($this) {
            self::Medicare => 'Medicare',
            self::Aca => 'ACA',
        };
    }

    /**
     * The route name and URL prefix of the vertical's roleplay pages.
     */
    public function routePrefix(): string
    {
        return "{$this->value}-roleplay";
    }

    /**
     * Who the trainee calls, for the delivery grader and pace feedback.
     */
    public function audience(): string
    {
        return match ($this) {
            self::Medicare => 'seniors',
            self::Aca => 'working-age adults',
        };
    }

    public function script(): RoleplayScript
    {
        return app(match ($this) {
            self::Medicare => RoleplayScript::class,
            self::Aca => AcaRoleplayScript::class,
        });
    }

    public function personaGenerator(): RoleplayPersonaGenerator
    {
        return app(match ($this) {
            self::Medicare => RoleplayPersonaGenerator::class,
            self::Aca => AcaRoleplayPersonaGenerator::class,
        });
    }

    public function consumerPrompt(): RoleplayConsumerPrompt
    {
        return app(match ($this) {
            self::Medicare => RoleplayConsumerPrompt::class,
            self::Aca => AcaRoleplayConsumerPrompt::class,
        });
    }

    public function grader(): RoleplayGrader
    {
        return app(match ($this) {
            self::Medicare => RoleplayGrader::class,
            self::Aca => AcaRoleplayGrader::class,
        });
    }

    /**
     * What the page shows about the vertical.
     *
     * @return array{id: string, label: string, handoff: 'cold'|'warm'}
     */
    public function toPage(): array
    {
        return [
            'id' => $this->value,
            'label' => $this->label(),
            'handoff' => $this === self::Aca ? 'warm' : 'cold',
        ];
    }
}
