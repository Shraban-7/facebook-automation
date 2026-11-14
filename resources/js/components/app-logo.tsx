import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-gradient-to-br from-[#1877F2] to-indigo-600 text-white shadow-xs">
                <AppLogoIcon className="size-5" />
            </div>
            <div className="ml-1.5 grid flex-1 text-left leading-tight">
                <span className="truncate text-sm font-semibold tracking-tight">
                    MetaPulse
                </span>
                <span className="truncate font-mono text-[10px] text-muted-foreground">
                    Social Studio
                </span>
            </div>
        </>
    );
}
