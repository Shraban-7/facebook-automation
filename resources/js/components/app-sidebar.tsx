import { Link, router, usePage } from '@inertiajs/react';
import {
    Activity,
    BarChart3,
    CalendarClock,
    CheckCircle2,
    ChevronDown,
    Flag,
    LayoutGrid,
    MessageSquareReply,
    Sparkles,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { FacebookPageSummary, NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Command Overview',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Connected Pages',
        href: '/facebook/pages',
        icon: Flag,
    },
    {
        title: 'Post Scheduler',
        href: '/facebook/scheduler',
        icon: CalendarClock,
    },
    {
        title: 'Auto-Reply Studio',
        href: '/facebook/auto-replies',
        icon: MessageSquareReply,
    },
    {
        title: 'Page Analytics',
        href: '/facebook/analytics',
        icon: BarChart3,
    },
    {
        title: 'Activity & Webhooks',
        href: '/facebook/activity-logs',
        icon: Activity,
    },
];

const footerNavItems: NavItem[] = [];

interface SharedWorkspaceProps {
    facebookWorkspace?: {
        connectedPages: FacebookPageSummary[];
        activePage: FacebookPageSummary | null;
    };
    [key: string]: unknown;
}

export function AppSidebar() {
    const { props } = usePage<SharedWorkspaceProps>();
    const connectedPages = props.facebookWorkspace?.connectedPages ?? [];
    const activePage = props.facebookWorkspace?.activePage ?? null;

    const handleSwitchPage = (pageId: number) => {
        router.post(`/facebook/pages/${pageId}/switch`, {}, { preserveScroll: true });
    };

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="gap-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>

                {connectedPages.length > 0 ? (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                className="flex w-full items-center justify-between gap-2 rounded-lg border border-sidebar-border bg-sidebar-accent/40 px-2.5 py-2 text-left text-xs transition hover:bg-sidebar-accent group-data-[collapsible=icon]:hidden"
                            >
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-1.5">
                                        <span
                                            className={`size-2 shrink-0 rounded-full ${
                                                activePage?.status === 'active'
                                                    ? 'bg-emerald-500'
                                                    : 'bg-amber-500'
                                            }`}
                                        />
                                        <span className="truncate font-semibold text-sidebar-foreground">
                                            {activePage?.name ?? 'Select Page'}
                                        </span>
                                    </div>
                                    <p className="mt-0.5 truncate font-mono text-[10px] text-muted-foreground">
                                        ID: {activePage?.page_id} · {(activePage?.followers_count ?? 0).toLocaleString()} followers
                                    </p>
                                </div>
                                <ChevronDown className="size-3.5 shrink-0 text-muted-foreground" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" className="w-64">
                            <DropdownMenuLabel className="text-xs text-muted-foreground">
                                Switch Active Facebook Page ({connectedPages.length})
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            {connectedPages.map((page) => (
                                <DropdownMenuItem
                                    key={page.id}
                                    onClick={() => handleSwitchPage(page.id)}
                                    className="flex cursor-pointer items-center justify-between gap-2"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-xs font-medium">{page.name}</p>
                                        <p className="truncate font-mono text-[10px] text-muted-foreground">
                                            {page.category ?? 'Page'} · {page.followers_count.toLocaleString()}
                                        </p>
                                    </div>
                                    {activePage?.id === page.id && (
                                        <CheckCircle2 className="size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                                    )}
                                </DropdownMenuItem>
                            ))}
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                <Link href="/facebook/pages" className="cursor-pointer text-xs font-medium text-blue-600 dark:text-blue-400">
                                    + Connect or Manage Pages
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : (
                    <div className="rounded-lg border border-dashed border-sidebar-border p-2.5 text-xs group-data-[collapsible=icon]:hidden">
                        <p className="font-medium text-sidebar-foreground">No Page Connected</p>
                        <div className="mt-1.5 flex items-center gap-1.5">
                            <Link
                                href="/facebook/pages"
                                className="inline-flex items-center gap-1 rounded bg-blue-600 px-2 py-1 text-[11px] font-medium text-white hover:bg-blue-700"
                            >
                                Connect OAuth
                            </Link>
                            <button
                                type="button"
                                onClick={() => router.post('/facebook/pages/seed-demo')}
                                className="inline-flex items-center gap-1 rounded border border-sidebar-border px-2 py-1 text-[11px] font-medium hover:bg-sidebar-accent"
                            >
                                <Sparkles className="size-3" />
                                Demo Data
                            </button>
                        </div>
                    </div>
                )}
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                {footerNavItems.length > 0 && <NavFooter items={footerNavItems} className="mt-auto" />}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
