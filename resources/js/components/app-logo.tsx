import { Radio } from 'lucide-react';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-[#1877F2] text-white shadow-xs">
                <Radio className="size-4.5" />
            </div>
            <div className="ml-1.5 grid flex-1 text-left leading-tight">
                <span className="truncate text-sm font-semibold tracking-tight">
                    MetaPulse
                </span>
                <span className="truncate font-mono text-[10px] text-muted-foreground">
                    Graph API v21.0
                </span>
            </div>
        </>
    );
}
