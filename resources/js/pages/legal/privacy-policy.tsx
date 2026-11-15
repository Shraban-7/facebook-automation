import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ShieldCheck } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';

export default function PrivacyPolicy() {
    return (
        <div className="min-h-svh bg-muted/30 px-4 py-10 text-foreground sm:px-6 lg:px-8">
            <Head title="Privacy Policy — MetaPulse" />

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

                <div className="rounded-xl border bg-card p-6 shadow-xs sm:p-8">
                    <div className="mb-6 flex items-start gap-3 border-b pb-5">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <ShieldCheck className="size-5" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">Privacy Policy</h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Effective Date: November 15, 2026 · MetaPulse Social Studio
                            </p>
                        </div>
                    </div>

                    <div className="space-y-6 text-sm leading-relaxed text-muted-foreground">
                        <section className="space-y-2">
                            <h2 className="text-base font-semibold text-foreground">1. Information We Collect</h2>
                            <p>
                                When you connect a Facebook Page, Instagram Business account, or other social channel to
                                MetaPulse via official OAuth 2.0 authorization, we collect only the permissions and data
                                you explicitly approve:
                            </p>
                            <ul className="list-disc space-y-1 pl-5">
                                <li>Workspace account details (name and email address).</li>
                                <li>Connected Page/Account identifiers, display names, handles, and profile avatars.</li>
                                <li>OAuth access and refresh tokens (encrypted at rest using Laravel encryption).</li>
                                <li>Scheduled posts, published content metadata, comments, inbox messages, and Page insights.</li>
                            </ul>
                        </section>

                        <section className="space-y-2">
                            <h2 className="text-base font-semibold text-foreground">2. How We Use Your Information</h2>
                            <p>
                                Your data is used strictly to provide the social media management features you trigger,
                                including scheduling and publishing posts, moderating comments and inbox replies, and
                                displaying analytics inside your workspace. We never sell your data or use Meta platform
                                data for advertising or third-party profiling.
                            </p>
                        </section>

                        <section className="space-y-2">
                            <h2 className="text-base font-semibold text-foreground">3. Data Storage & Security</h2>
                            <p>
                                All OAuth tokens are stored encrypted at rest in our token vault and isolated per
                                workspace. API calls are logged for rate-limit compliance and operational auditing, with
                                automatic log retention pruning.
                            </p>
                        </section>

                        <section className="space-y-2">
                            <h2 className="text-base font-semibold text-foreground">4. Data Deletion & Revocation</h2>
                            <p>
                                You can disconnect any connected social account at any time from your workspace settings
                                or revoke access directly in your Facebook Business Integrations settings. To view full
                                deletion instructions or check a deletion request, visit our{' '}
                                <Link href="/data-deletion" className="font-medium text-primary underline underline-offset-4">
                                    User Data Deletion
                                </Link>{' '}
                                page.
                            </p>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    );
}
