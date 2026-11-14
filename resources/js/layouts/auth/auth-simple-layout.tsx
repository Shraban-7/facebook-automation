import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-background p-6 md:p-10">
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top,rgba(99,102,241,0.10),transparent_55%),radial-gradient(ellipse_at_bottom_right,rgba(16,185,129,0.08),transparent_50%)]"
            />

            <div className="w-full max-w-md rounded-2xl border bg-card/95 p-6 shadow-lg backdrop-blur-sm sm:p-8">
                <div className="flex flex-col gap-6">
                    <div className="flex flex-col items-center gap-3">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-2 font-medium"
                        >
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl border bg-primary/10 text-primary shadow-xs">
                                <AppLogoIcon className="size-6 fill-current" />
                            </div>
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="space-y-1.5 text-center">
                            <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
                            <p className="text-center text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
