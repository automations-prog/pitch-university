import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CallScreen } from '@/components/roleplay/call-screen';
import { LevelPicker } from '@/components/roleplay/level-picker';
import { WrapUp } from '@/components/roleplay/wrap-up';
import { useRoleplayCall } from '@/hooks/use-roleplay-call';
import type { DifficultyLevel } from '@/lib/roleplay-data';
import { generatePersona, type Persona } from '@/lib/roleplay-persona';
import { dashboard } from '@/routes';
import { index as roleplayIndex } from '@/routes/roleplay';

export default function RoleplayIndex() {
    const { auth } = usePage().props;
    const [selectedLevel, setSelectedLevel] = useState<DifficultyLevel>(1);
    const [session, setSession] = useState<{
        id: number;
        persona: Persona;
    } | null>(null);

    const startSession = (level: DifficultyLevel) =>
        setSession((current) => ({
            id: (current?.id ?? 0) + 1,
            persona: generatePersona(level),
        }));

    return (
        <>
            <Head title="Roleplay" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-0.5">
                    <h2 className="text-xl font-semibold tracking-tight">
                        Roleplay
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        {session
                            ? 'Follow the script, rebut every objection, and code the call correctly.'
                            : 'Pick a difficulty and practice a Medicare call against a simulated consumer.'}
                    </p>
                </div>

                {session ? (
                    <RoleplaySession
                        key={session.id}
                        persona={session.persona}
                        agentName={auth.user.name.split(' ')[0]}
                        onRetry={() =>
                            startSession(session.persona.level.level)
                        }
                        onChangeLevel={() => setSession(null)}
                    />
                ) : (
                    <LevelPicker
                        selectedLevel={selectedLevel}
                        onSelect={setSelectedLevel}
                        onStart={() => startSession(selectedLevel)}
                    />
                )}
            </div>
        </>
    );
}

/**
 * One roleplay call, from dial to disposition. Keyed by session so every
 * retry starts with a fresh call state.
 */
function RoleplaySession({
    persona,
    agentName,
    onRetry,
    onChangeLevel,
}: {
    persona: Persona;
    agentName: string;
    onRetry: () => void;
    onChangeLevel: () => void;
}) {
    const call = useRoleplayCall(persona, agentName);
    const [isWrappingUp, setIsWrappingUp] = useState(false);

    if (isWrappingUp && call.endReason) {
        return (
            <WrapUp
                persona={persona}
                endReason={call.endReason}
                stepsRead={call.currentStep}
                facedObjections={call.facedObjections}
                onRetry={onRetry}
                onChangeLevel={onChangeLevel}
            />
        );
    }

    return (
        <CallScreen
            persona={persona}
            agentName={agentName}
            call={call}
            onWrapUp={() => setIsWrappingUp(true)}
        />
    );
}

RoleplayIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Roleplay', href: roleplayIndex() },
    ],
};
