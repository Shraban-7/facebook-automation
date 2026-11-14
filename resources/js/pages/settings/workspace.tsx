import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Building2,
    Check,
    CheckCircle2,
    Crown,
    Eye,
    PenLine,
    Search,
    Shield,
    ShieldCheck,
    Trash2,
    UserPlus,
    Users,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { WorkspaceSummary } from '@/types';

interface MemberItem {
    id: number;
    user_id: number;
    name: string;
    email: string;
    role: string;
    is_owner: boolean;
}

interface Props {
    workspace: WorkspaceSummary;
    members: MemberItem[];
    canManageMembers: boolean;
}

interface SharedPageProps {
    socialWorkspace?: {
        current: WorkspaceSummary | null;
        workspaces: WorkspaceSummary[];
    };
    flash?: {
        status?: string | null;
        error?: string | null;
    };
    [key: string]: unknown;
}

const ROLE_META: Record<
    string,
    {
        label: string;
        description: string;
        badgeClass: string;
        icon: typeof Crown;
    }
> = {
    owner: {
        label: 'Owner',
        description: 'Full workspace ownership, billing, RBAC & account control.',
        badgeClass: 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
        icon: Crown,
    },
    admin: {
        label: 'Admin',
        description: 'Manage social accounts, approve posts, and invite/remove members.',
        badgeClass: 'border-indigo-500/30 bg-indigo-500/10 text-indigo-700 dark:text-indigo-300',
        icon: Shield,
    },
    editor: {
        label: 'Editor',
        description: 'Create, schedule, and publish content; reply in Unified Inbox.',
        badgeClass: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        icon: PenLine,
    },
    viewer: {
        label: 'Viewer',
        description: 'Read-only access to analytics, calendar, and inbox.',
        badgeClass: 'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300',
        icon: Eye,
    },
};

const PERMISSION_MATRIX = [
    { capability: 'View analytics, calendar & inbox', owner: true, admin: true, editor: true, viewer: true },
    { capability: 'Compose, schedule & reply to messages', owner: true, admin: true, editor: true, viewer: false },
    { capability: 'Approve pending posts', owner: true, admin: true, editor: false, viewer: false },
    { capability: 'Connect & revoke social accounts', owner: true, admin: true, editor: false, viewer: false },
    { capability: 'Manage workspace members & roles', owner: true, admin: true, editor: false, viewer: false },
];

function getInitials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

export default function WorkspaceSettingsPage({ workspace, members, canManageMembers }: Props) {
    const { props } = usePage<SharedPageProps>();
    const availableWorkspaces = props.socialWorkspace?.workspaces ?? [workspace];
    const flashStatus = props.flash?.status;

    const [searchQuery, setSearchQuery] = useState('');
    const [confirmRemoveId, setConfirmRemoveId] = useState<number | null>(null);

    const form = useForm({
        email: '',
        role: 'editor',
    });

    const filteredMembers = useMemo(() => {
        const q = searchQuery.trim().toLowerCase();
        if (!q) {
            return members;
        }
        return members.filter(
            (m) =>
                m.name.toLowerCase().includes(q) ||
                m.email.toLowerCase().includes(q) ||
                m.role.toLowerCase().includes(q),
        );
    }, [members, searchQuery]);

    const roleCounts = useMemo(() => {
        return {
            admins: members.filter((m) => m.role === 'owner' || m.role === 'admin').length,
            editors: members.filter((m) => m.role === 'editor').length,
            viewers: members.filter((m) => m.role === 'viewer').length,
        };
    }, [members]);

    const handleAddMember = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/settings/workspace/members', {
            preserveScroll: true,
            onSuccess: () => form.reset('email'),
        });
    };

    const handleRemove = (memberId: number) => {
        router.delete(`/settings/workspace/members/${memberId}`, {
            preserveScroll: true,
            onFinish: () => setConfirmRemoveId(null),
        });
    };

    const handleSwitchWorkspace = (targetId: number) => {
        if (targetId === workspace.id) {
            return;
        }
        router.post(`/workspaces/${targetId}/switch`, {}, { preserveScroll: true });
    };

    const selectedRoleMeta = ROLE_META[form.data.role] ?? ROLE_META.editor;

    return (
        <>
            <Head title="Workspace & Team" />

            <h1 className="sr-only">Workspace & Team settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Workspace & Team"
                    description="Manage your active workspace, team members, and role-based permissions"
                />

                {flashStatus && (
                    <div className="flex items-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3.5 py-2.5 text-sm text-emerald-700 dark:text-emerald-300">
                        <CheckCircle2 className="h-4 w-4 shrink-0" />
                        <span>{flashStatus}</span>
                    </div>
                )}

                {/* Workspace Identity & Switcher */}
                <Card>
                    <CardHeader className="pb-3">
                        <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <Building2 className="h-5 w-5" />
                                </div>
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <CardTitle className="text-base">{workspace.name}</CardTitle>
                                        <Badge variant="secondary" className="text-[10px] uppercase tracking-wider">
                                            {workspace.plan}
                                        </Badge>
                                    </div>
                                    <CardDescription className="font-mono text-xs">
                                        {workspace.slug}
                                    </CardDescription>
                                </div>
                            </div>

                            {availableWorkspaces.length > 1 && (
                                <select
                                    aria-label="Switch active workspace"
                                    value={workspace.id}
                                    onChange={(e) => handleSwitchWorkspace(Number(e.target.value))}
                                    className="border-input bg-background h-9 rounded-md border px-3 text-xs font-medium"
                                >
                                    {availableWorkspaces.map((ws) => (
                                        <option key={ws.id} value={ws.id}>
                                            {ws.name} ({ws.plan})
                                        </option>
                                    ))}
                                </select>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-4 divide-x rounded-lg border bg-muted/30 py-2.5 text-center">
                            <div>
                                <div className="text-lg font-semibold tabular-nums">{members.length}</div>
                                <div className="text-[11px] text-muted-foreground">Total</div>
                            </div>
                            <div>
                                <div className="text-lg font-semibold tabular-nums">{roleCounts.admins}</div>
                                <div className="text-[11px] text-muted-foreground">Admins</div>
                            </div>
                            <div>
                                <div className="text-lg font-semibold tabular-nums">{roleCounts.editors}</div>
                                <div className="text-[11px] text-muted-foreground">Editors</div>
                            </div>
                            <div>
                                <div className="text-lg font-semibold tabular-nums">{roleCounts.viewers}</div>
                                <div className="text-[11px] text-muted-foreground">Viewers</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Add / Update Member */}
                {canManageMembers && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <UserPlus className="h-4 w-4" />
                                Add or Update Member
                            </CardTitle>
                            <CardDescription>
                                Assign an existing user to <span className="font-medium text-foreground">{workspace.name}</span> or update their role.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleAddMember} className="space-y-4">
                                <div className="grid gap-3 sm:grid-cols-3 sm:items-end">
                                    <div className="space-y-1.5 sm:col-span-2">
                                        <Label htmlFor="member-email">User Email</Label>
                                        <Input
                                            id="member-email"
                                            type="email"
                                            value={form.data.email}
                                            onChange={(e) => form.setData('email', e.target.value)}
                                            placeholder="teammate@example.com"
                                            required
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="member-role">Role</Label>
                                        <select
                                            id="member-role"
                                            value={form.data.role}
                                            onChange={(e) => form.setData('role', e.target.value)}
                                            className="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm"
                                        >
                                            <option value="admin">Admin</option>
                                            <option value="editor">Editor</option>
                                            <option value="viewer">Viewer</option>
                                        </select>
                                    </div>
                                </div>

                                <div className="flex flex-col justify-between gap-3 rounded-lg bg-muted/50 px-3 py-2.5 sm:flex-row sm:items-center">
                                    <p className="text-xs text-muted-foreground">
                                        <span className="font-medium text-foreground">{selectedRoleMeta.label}:</span>{' '}
                                        {selectedRoleMeta.description}
                                    </p>
                                    <Button type="submit" size="sm" disabled={form.processing} className="shrink-0">
                                        {form.processing ? 'Saving...' : 'Save Member'}
                                    </Button>
                                </div>

                                <InputError message={form.errors.email} />
                                <InputError message={form.errors.role} />
                            </form>
                        </CardContent>
                    </Card>
                )}

                {/* Team Directory */}
                <Card>
                    <CardHeader className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Users className="h-4 w-4" />
                                Team Directory ({filteredMembers.length})
                            </CardTitle>
                            <CardDescription>
                                Members with access to this workspace.
                            </CardDescription>
                        </div>
                        {members.length > 3 && (
                            <div className="relative w-full sm:w-48">
                                <Search className="pointer-events-none absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    placeholder="Filter members..."
                                    className="h-9 pl-8 text-xs"
                                />
                            </div>
                        )}
                    </CardHeader>
                    <CardContent className="divide-y">
                        {filteredMembers.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                No matching workspace members found.
                            </p>
                        ) : (
                            filteredMembers.map((member) => {
                                const meta = ROLE_META[member.role] ?? ROLE_META.viewer;
                                const RoleIcon = meta.icon;
                                const confirming = confirmRemoveId === member.id;

                                return (
                                    <div
                                        key={member.id}
                                        className="flex flex-col justify-between gap-3 py-3 sm:flex-row sm:items-center"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                                                {getInitials(member.name)}
                                            </div>
                                            <div className="min-w-0">
                                                <div className="flex items-center gap-1.5 text-sm font-medium">
                                                    <span className="truncate">{member.name}</span>
                                                    {member.is_owner && (
                                                        <span className="text-[11px] font-normal text-muted-foreground">
                                                            (Owner)
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="truncate text-xs text-muted-foreground">{member.email}</div>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-2 self-end sm:self-auto">
                                            <Badge variant="outline" className={`gap-1 capitalize ${meta.badgeClass}`}>
                                                <RoleIcon className="h-3 w-3" />
                                                {meta.label}
                                            </Badge>

                                            {canManageMembers && !member.is_owner && (
                                                confirming ? (
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            size="sm"
                                                            variant="destructive"
                                                            className="h-7 px-2 text-xs"
                                                            onClick={() => handleRemove(member.id)}
                                                        >
                                                            Confirm
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="h-7 px-2 text-xs"
                                                            onClick={() => setConfirmRemoveId(null)}
                                                        >
                                                            Cancel
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        aria-label={`Remove ${member.name}`}
                                                        onClick={() => setConfirmRemoveId(member.id)}
                                                    >
                                                        <Trash2 className="h-4 w-4 text-destructive" />
                                                    </Button>
                                                )
                                            )}
                                        </div>
                                    </div>
                                );
                            })
                        )}
                    </CardContent>
                </Card>

                {/* RBAC Matrix */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <ShieldCheck className="h-4 w-4 text-primary" />
                            RBAC Permission Matrix
                        </CardTitle>
                        <CardDescription>
                            Enforced server-side via Laravel Policies and workspace isolation.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead>
                                    <tr className="border-b text-muted-foreground">
                                        <th className="pb-2 font-medium">Capability</th>
                                        <th className="pb-2 text-center font-medium">Owner</th>
                                        <th className="pb-2 text-center font-medium">Admin</th>
                                        <th className="pb-2 text-center font-medium">Editor</th>
                                        <th className="pb-2 text-center font-medium">Viewer</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {PERMISSION_MATRIX.map((row) => (
                                        <tr key={row.capability}>
                                            <td className="py-2.5 pr-2 text-foreground">{row.capability}</td>
                                            {(['owner', 'admin', 'editor', 'viewer'] as const).map((role) => (
                                                <td key={role} className="py-2.5 text-center">
                                                    {row[role] ? (
                                                        <Check className="mx-auto h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                                                    ) : (
                                                        <X className="mx-auto h-3.5 w-3.5 text-muted-foreground/40" />
                                                    )}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
