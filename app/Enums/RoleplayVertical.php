<?php

namespace App\Enums;

use App\Services\AcaRoleplayConsumerPrompt;
use App\Services\AcaRoleplayGrader;
use App\Services\AcaRoleplayPersonaGenerator;
use App\Services\AcaRoleplayScript;
use App\Services\MealsRoleplayConsumerPrompt;
use App\Services\MealsRoleplayGrader;
use App\Services\MealsRoleplayPersonaGenerator;
use App\Services\MealsRoleplayScript;
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
    case Meals = 'meals';

    public function label(): string
    {
        return match ($this) {
            self::Medicare => 'Medicare',
            self::Aca => 'ACA',
            self::Meals => 'Meals',
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
            self::Meals => 'NYC Medicaid recipients',
        };
    }

    public function script(): RoleplayScript
    {
        return app(match ($this) {
            self::Medicare => RoleplayScript::class,
            self::Aca => AcaRoleplayScript::class,
            self::Meals => MealsRoleplayScript::class,
        });
    }

    public function personaGenerator(): RoleplayPersonaGenerator
    {
        return app(match ($this) {
            self::Medicare => RoleplayPersonaGenerator::class,
            self::Aca => AcaRoleplayPersonaGenerator::class,
            self::Meals => MealsRoleplayPersonaGenerator::class,
        });
    }

    public function consumerPrompt(): RoleplayConsumerPrompt
    {
        return app(match ($this) {
            self::Medicare => RoleplayConsumerPrompt::class,
            self::Aca => AcaRoleplayConsumerPrompt::class,
            self::Meals => MealsRoleplayConsumerPrompt::class,
        });
    }

    public function grader(): RoleplayGrader
    {
        return app(match ($this) {
            self::Medicare => RoleplayGrader::class,
            self::Aca => AcaRoleplayGrader::class,
            self::Meals => MealsRoleplayGrader::class,
        });
    }

    /**
     * Who takes the call at the transfer.
     */
    public function handoffParty(): string
    {
        return match ($this) {
            self::Medicare => 'specialist',
            self::Aca => 'licensed agent',
            self::Meals => 'screener',
        };
    }

    /**
     * How the trainee introduces the consumer on a warm handoff, from the
     * script. Null on a cold transfer, where the trainee stays silent.
     */
    public function handoffIntro(): ?string
    {
        return match ($this) {
            self::Medicare => null,
            self::Aca => 'Hi, I have (name) from (state), please take the call.',
            self::Meals => 'Hey agent, I have (name) in (borough), the zip code for them is (zip).',
        };
    }

    /**
     * What the page shows about the vertical.
     *
     * @return array{id: string, label: string, handoff: 'cold'|'warm', handoffParty: string, handoffIntro: string|null}
     */
    public function toPage(): array
    {
        return [
            'id' => $this->value,
            'label' => $this->label(),
            'handoff' => $this === self::Medicare ? 'cold' : 'warm',
            'handoffParty' => $this->handoffParty(),
            'handoffIntro' => $this->handoffIntro(),
        ];
    }
}
