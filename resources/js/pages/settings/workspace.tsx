import { Head, router, useForm } from '@inertiajs/react';
import { Trash2, UserPlus, Users } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, WorkspaceSummary } from '@/types';

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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/workspace' },
    { title: 'Workspace Team', href: '/settings/workspace' },
];

export default function WorkspaceSettingsPage({ workspace, members, canManageMembers }: Props) {
    const form = useForm({
        email: '',
        role: 'editor',
    });

    const handleAddMember = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/settings/workspace/members', {
            preserveScroll: true,
            onSuccess: () => form.reset('email'),
        });
    };

    const handleRemove = (memberId: number) => {
        router.delete(`/settings/workspace/members/${memberId}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workspace Team" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{workspace.name} · Team & RBAC</h1>
                    <p className="text-sm text-muted-foreground">
                        Plan: <span className="capitalize">{workspace.plan}</span> · Manage workspace roles (Owner, Admin, Editor, Viewer).
                    </p>
                </div>

                {canManageMembers && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <UserPlus className="h-4 w-4" />
                                Add Workspace Member
                            </CardTitle>
                            <CardDescription>Assign an existing user to this workspace with an RBAC role.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleAddMember} className="grid gap-4 sm:grid-cols-3 sm:items-end">
                                <div className="space-y-1.5">
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
                                <Button type="submit" disabled={form.processing}>
                                    Add / Update Member
                                </Button>
                            </form>
                            {form.errors.email && (
                                <p className="mt-2 text-xs text-destructive">{form.errors.email}</p>
                            )}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Users className="h-4 w-4" />
                            Members ({members.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="divide-y">
                        {members.map((member) => (
                            <div key={member.id} className="flex items-center justify-between py-3">
                                <div>
                                    <div className="font-medium">{member.name}</div>
                                    <div className="text-xs text-muted-foreground">{member.email}</div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge variant="outline" className="capitalize">
                                        {member.role}
                                    </Badge>
                                    {canManageMembers && !member.is_owner && (
                                        <Button size="sm" variant="ghost" onClick={() => handleRemove(member.id)}>
                                            <Trash2 className="h-4 w-4 text-destructive" />
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
