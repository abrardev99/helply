import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowUp, ChevronLeft, MoreHorizontal, Smile, X } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { dashboard, login } from '@/routes';
import { store as joinWaitlist } from '@/routes/waitlist';

export default function Welcome() {
    const { auth, currentTeam, features } = usePage().props;
    const dashboardUrl = currentTeam ? dashboard(currentTeam.slug) : '/';

    return (
        <>
            <Head title="Helply — a support agent that already knows your product">
                <meta
                    name="description"
                    content="Helply turns everything you've already written into a support agent that answers your customers instantly, in your voice, around the clock. Join the waitlist."
                />
            </Head>

            <div className="flex min-h-svh flex-col bg-background text-foreground">
                <header className="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-6">
                    <Link
                        href="/"
                        className="flex items-center gap-2 font-medium"
                    >
                        <span className="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <AppLogoIcon className="size-4 fill-current" />
                        </span>
                        <span className="text-base font-semibold tracking-tight">
                            Helply
                        </span>
                    </Link>

                    {auth.user ? (
                        <Link
                            href={dashboardUrl}
                            className="text-sm text-muted-foreground underline-offset-4 transition-colors hover:text-foreground hover:underline"
                        >
                            Dashboard
                        </Link>
                    ) : (
                        features.auth && (
                            <Link
                                href={login()}
                                className="text-sm text-muted-foreground underline-offset-4 transition-colors hover:text-foreground hover:underline"
                            >
                                Log in
                            </Link>
                        )
                    )}
                </header>

                <main className="mx-auto grid w-full max-w-6xl flex-1 items-center gap-16 px-6 py-16 lg:grid-cols-[1.1fr_1fr] lg:gap-12 lg:py-24">
                    <div className="max-w-xl">
                        <span className="inline-flex items-center gap-2 rounded-full border border-border px-3 py-1 font-mono text-xs tracking-wide text-muted-foreground uppercase">
                            <span className="relative flex size-1.5">
                                <span className="absolute inline-flex size-full animate-ping rounded-full bg-foreground opacity-60" />
                                <span className="relative inline-flex size-1.5 rounded-full bg-foreground" />
                            </span>
                            Private beta · Coming soon
                        </span>

                        <h1 className="mt-6 text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                            Support that already knows your product.
                        </h1>

                        <p className="mt-5 text-lg leading-relaxed text-pretty text-muted-foreground">
                            Helply reads everything you have already written —
                            your website, your docs, your PDFs — and turns it
                            into a support agent that answers customer questions
                            instantly, in your voice, day and night.
                        </p>

                        <dl className="mt-8 grid gap-x-8 gap-y-4 border-t border-border pt-8 sm:grid-cols-2">
                            <div>
                                <dt className="font-mono text-xs tracking-wide text-muted-foreground uppercase">
                                    Learns from your content
                                </dt>
                                <dd className="mt-1 text-sm text-foreground">
                                    Point it at your site. It reads the rest.
                                </dd>
                            </div>
                            <div>
                                <dt className="font-mono text-xs tracking-wide text-muted-foreground uppercase">
                                    Answers in your voice
                                </dt>
                                <dd className="mt-1 text-sm text-foreground">
                                    Grounded in your words — not made up.
                                </dd>
                            </div>
                            <div>
                                <dt className="font-mono text-xs tracking-wide text-muted-foreground uppercase">
                                    Always on
                                </dt>
                                <dd className="mt-1 text-sm text-foreground">
                                    Every question, at any hour.
                                </dd>
                            </div>
                            <div>
                                <dt className="font-mono text-xs tracking-wide text-muted-foreground uppercase">
                                    One line to embed
                                </dt>
                                <dd className="mt-1 text-sm text-foreground">
                                    Drop in a script. That is it.
                                </dd>
                            </div>
                        </dl>

                        <div className="mt-10">
                            <Form
                                {...joinWaitlist.form()}
                                resetOnSuccess={['email']}
                                disableWhileProcessing
                                className="max-w-md"
                            >
                                {({
                                    processing,
                                    errors,
                                    recentlySuccessful,
                                }) =>
                                    recentlySuccessful ? (
                                        <p
                                            className="flex items-center gap-2 text-sm font-medium"
                                            role="status"
                                        >
                                            <span className="flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                                <svg
                                                    className="size-3"
                                                    viewBox="0 0 12 12"
                                                    fill="none"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        d="M2.5 6.5 5 9l4.5-5.5"
                                                        stroke="currentColor"
                                                        strokeWidth="1.5"
                                                        strokeLinecap="round"
                                                        strokeLinejoin="round"
                                                    />
                                                </svg>
                                            </span>
                                            You&apos;re on the list. We&apos;ll
                                            be in touch.
                                        </p>
                                    ) : (
                                        <>
                                            <div className="flex flex-col gap-2 sm:flex-row">
                                                <Input
                                                    id="email"
                                                    type="email"
                                                    name="email"
                                                    required
                                                    autoComplete="email"
                                                    placeholder="you@company.com"
                                                    aria-label="Email address"
                                                    className="h-11 sm:flex-1"
                                                />
                                                <Button
                                                    type="submit"
                                                    size="lg"
                                                    className="h-11"
                                                >
                                                    {processing && <Spinner />}
                                                    Join the waitlist
                                                </Button>
                                            </div>
                                            <InputError
                                                message={errors.email}
                                                className="mt-2"
                                            />
                                            <p className="mt-3 text-xs text-muted-foreground">
                                                We'll only ever use your email
                                                for product announcements —
                                                never anything else.
                                            </p>
                                        </>
                                    )
                                }
                            </Form>
                        </div>
                    </div>

                    <div className="flex justify-center px-2 lg:justify-self-end">
                        <SupportAgentPreview />
                    </div>
                </main>

                <footer className="mx-auto w-full max-w-6xl px-6 py-8 text-xs text-muted-foreground">
                    © {new Date().getFullYear()} Helply. All rights reserved.
                </footer>
            </div>
        </>
    );
}

const previewAgents = [
    { initials: 'AM', className: 'bg-rose-200 text-rose-800' },
    { initials: 'JC', className: 'bg-amber-200 text-amber-900' },
    { initials: 'SR', className: 'bg-sky-200 text-sky-800' },
];

/**
 * A static, non-interactive mock of the embedded Support Agent popup — shown purely
 * to illustrate what visitors experience on a customer's site. It renders like a
 * floating widget but is inert (no handlers, pointer events disabled).
 */
function SupportAgentPreview() {
    return (
        <div
            className="pointer-events-none relative w-full max-w-sm select-none"
            aria-hidden="true"
        >
            {/* Soft glow so the panel lifts off the page. */}
            <div className="absolute -inset-6 -z-10 rounded-[2.5rem] bg-primary/10 blur-2xl" />

            <div className="overflow-hidden rounded-[1.75rem] border border-border bg-card shadow-2xl ring-1 ring-black/5 dark:ring-white/10">
                <div className="flex items-center gap-3 border-b border-border px-4 py-3.5">
                    <ChevronLeft className="size-5 shrink-0 text-muted-foreground" />
                    <div className="flex -space-x-2">
                        {previewAgents.map((agent) => (
                            <span
                                key={agent.initials}
                                className={`flex size-7 items-center justify-center rounded-full text-[10px] font-semibold ring-2 ring-card ${agent.className}`}
                            >
                                {agent.initials}
                            </span>
                        ))}
                    </div>
                    <p className="flex-1 truncate text-sm font-semibold">
                        Helply
                    </p>
                    <MoreHorizontal className="size-5 shrink-0 text-muted-foreground" />
                    <X className="size-5 shrink-0 text-muted-foreground" />
                </div>

                <div className="space-y-5 px-4 py-5">
                    <div>
                        <div className="w-fit max-w-[85%] rounded-2xl rounded-bl-md bg-muted px-3.5 py-2.5 text-sm leading-relaxed text-foreground">
                            Hello! 👋
                            <br />
                            How can I help?
                        </div>
                        <p className="mt-1.5 text-xs text-muted-foreground">
                            Helply · Support Agent · Just now
                        </p>
                    </div>

                    <div className="flex justify-end">
                        <div className="max-w-[80%] rounded-2xl rounded-br-md bg-primary px-3.5 py-2.5 text-sm leading-relaxed text-primary-foreground">
                            Do you offer a yearly plan?
                        </div>
                    </div>

                    <div>
                        <div className="w-fit max-w-[85%] rounded-2xl rounded-bl-md bg-muted px-3.5 py-2.5 text-sm leading-relaxed text-foreground">
                            Yes — annual billing saves you two months, and you
                            can switch anytime from your account settings.
                        </div>
                        <p className="mt-1.5 text-xs text-muted-foreground">
                            Helply · Support Agent · Just now
                        </p>
                    </div>
                </div>

                <div className="px-3 pb-3">
                    <div className="rounded-2xl border border-border px-4 py-3">
                        <p className="text-sm text-muted-foreground">
                            Message…
                        </p>
                        <div className="mt-4 flex items-center justify-between">
                            <Smile className="size-5 text-muted-foreground" />
                            <span className="flex size-8 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                <ArrowUp className="size-4" />
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
