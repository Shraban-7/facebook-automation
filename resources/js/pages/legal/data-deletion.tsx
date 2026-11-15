import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Trash2 } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';

interface DataDeletionProps {
    confirmationCode?: string | null;
}

export default function DataDeletion({ confirmationCode = null }: DataDeletionProps) {
    return (
        <div className="min-h-svh bg-muted/30 px-4 py-10 text-foreground sm:px-6 lg:px-8">
            <Head title="User Data Deletion — MetaPulse" />

            <div className="mx-auto max-w-3xl space-y-6">
                <div className="flex items-center justify-between">
                    <Link href="/" className="flex items-center gap-2.5 font-semibold tracking-tight">
                        <div className="flex size-9 items-center justify-center rounded-lg bg-gradient-to-br from-[#1877F2] to-indigo-600 text-white shadow-xs">
                            <AppLogoIcon className="size-5" />
                        </div>
                        <span>MetaPulse</span>
                    </Link>

                    <Button variant="outline" size="sm" asChild>
                        <Link href="/">
                            <ArrowLeft className="mr-1.5 size-3.5" />
                            Back to App
                        </Link>
                    </Button>
                </div>

                {confirmationCode && (
                    <div className="flex items-start gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-900 dark:text-emerald-200">
                        <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <div>
                            <p className="font-semibold">Data Deletion Request Completed</p>
                            <p className="mt-0.5 text-xs opacity-90">
                                All associated Meta OAuth tokens and connected page credentials have been purged.
                                Confirmation code: <code className="font-mono font-semibold">{confirmationCode}</code>
                            </p>
                        </div>
                    </div>
                )}

                <div className="rounded-xl border bg-card p-6 shadow-xs sm:p-8">
                    <div className="mb-6 flex items-start gap-3 border-b pb-5">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-destructive/10 text-destructive">
                            <Trash2 className="size-5" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">User Data Deletion Instructions</h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                How to remove your Facebook / Meta data from MetaPulse
                            </p>
                        </div>
                    </div>

                    <div className="space-y-6 text-sm leading-relaxed text-muted-foreground">
                        <section className="space-y-2">
                            <h2 className="text-base font-semibold text-foreground">
                                Option 1: Disconnect Inside MetaPulse
                            </h2>
                            <ol className="list-decimal space-y-1 pl-5">
                                <li>Sign in to your MetaPulse workspace.</li>
                                <li>
                                    Open <strong>Social Accounts</strong> or <strong>Facebook Pages</strong>.
                                </li>
                                <li>
                                    Click <strong>Disconnect / Remove</strong> on the connected Page or account to purge
                                    its encrypted OAuth tokens immediately.
                                </li>
                            </ol>
                        </section>

                        <section className="space-y-2">
                            <h2 className="text-base font-semibold text-foreground">
                                Option 2: Remove via Facebook Business Integrations
                            </h2>
                            <ol className="list-decimal space-y-1 pl-5">
                                <li>
                                    Go to your Facebook account <strong>Settings &amp; Privacy → Settings</strong>.
                                </li>
                                <li>
                                    Open <strong>Security and Login → Business Integrations</strong> (or{' '}
                                    <strong>Apps and Websites</strong>).
                                </li>
                                <li>
                                    Select <strong>MetaPulse</strong> and click <strong>Remove</strong>.
                                </li>
                                <li>
                                    Facebook will automatically trigger our data-deletion callback endpoint, purging your
                                    stored tokens and returning a confirmation code.
                                </li>
                            </ol>
                        </section>

                        <section className="space-y-2">
                            <h2 className="text-base font-semibold text-foreground">
                                Automated Callback Endpoint
                            </h2>
                            <p>
                                Meta platform deletion requests are processed automatically at{' '}
                                <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-foreground">
                                    /api/compliance/meta/data-deletion
                                </code>{' '}
                                and deauthorization callbacks at{' '}
                                <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-foreground">
                                    /api/compliance/meta/deauthorize
                                </code>
                                .
                            </p>
                            <p className="pt-1">
                                See our{' '}
                                <Link href="/privacy-policy" className="font-medium text-primary underline underline-offset-4">
                                    Privacy Policy
                                </Link>{' '}
                                for full details on how workspace data is handled.
                            </p>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    );
}
