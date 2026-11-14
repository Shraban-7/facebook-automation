import { Form, Head } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, Lock, Mail, Sparkles } from 'lucide-react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

const PLATFORMS = ['Facebook', 'Instagram', 'Threads', 'LinkedIn', 'YouTube'];

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Log in" />

            <div className="space-y-5">
                <div className="flex flex-wrap items-center justify-center gap-1.5">
                    {PLATFORMS.map((platform) => (
                        <Badge
                            key={platform}
                            variant="secondary"
                            className="px-2 py-0.5 text-[10px] font-medium tracking-wide"
                        >
                            {platform}
                        </Badge>
                    ))}
                </div>

                {status && (
                    <div className="flex items-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3.5 py-2.5 text-sm font-medium text-emerald-700 dark:text-emerald-300">
                        <CheckCircle2 className="h-4 w-4 shrink-0" />
                        <span>{status}</span>
                    </div>
                )}

                <div className="space-y-3">
                    <PasskeyVerify />

                    <div className="relative flex items-center py-1">
                        <div className="flex-grow border-t" />
                        <span className="mx-3 shrink-0 text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                            Or sign in with email
                        </span>
                        <div className="flex-grow border-t" />
                    </div>
                </div>

                <Form
                    {...store.form()}
                    resetOnSuccess={['password']}
                    className="flex flex-col gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>
                                    <div className="relative">
                                        <Mail className="pointer-events-none absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            id="email"
                                            type="email"
                                            name="email"
                                            required
                                            autoFocus
                                            tabIndex={1}
                                            autoComplete="email"
                                            placeholder="email@example.com"
                                            className="pl-9"
                                        />
                                    </div>
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="password">Password</Label>
                                        {canResetPassword && (
                                            <TextLink
                                                href={request()}
                                                className="text-xs text-muted-foreground hover:text-foreground"
                                                tabIndex={5}
                                            >
                                                Forgot password?
                                            </TextLink>
                                        )}
                                    </div>
                                    <div className="relative">
                                        <Lock className="pointer-events-none absolute top-2.5 left-3 z-10 h-4 w-4 text-muted-foreground" />
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            required
                                            tabIndex={2}
                                            autoComplete="current-password"
                                            placeholder="Enter your password"
                                            className="pl-9"
                                        />
                                    </div>
                                    <InputError message={errors.password} />
                                </div>

                                <div className="flex items-center justify-between pt-0.5">
                                    <div className="flex items-center space-x-2.5">
                                        <Checkbox
                                            id="remember"
                                            name="remember"
                                            tabIndex={3}
                                        />
                                        <Label htmlFor="remember" className="text-sm font-normal text-muted-foreground">
                                            Keep me signed in
                                        </Label>
                                    </div>
                                </div>

                                <Button
                                    type="submit"
                                    className="mt-1 h-10 w-full gap-2 font-medium"
                                    tabIndex={4}
                                    disabled={processing}
                                    data-test="login-button"
                                >
                                    {processing ? (
                                        <Spinner />
                                    ) : (
                                        <>
                                            <span>Sign in to Studio</span>
                                            <ArrowRight className="h-4 w-4" />
                                        </>
                                    )}
                                </Button>
                            </div>

                            <div className="flex items-center justify-center gap-1.5 border-t pt-4 text-center text-sm text-muted-foreground">
                                <Sparkles className="h-3.5 w-3.5 text-primary" />
                                <span>New to the workspace?</span>
                                <TextLink href={register()} tabIndex={5} className="font-medium text-foreground">
                                    Create an account
                                </TextLink>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Login.layout = {
    title: 'Welcome back',
    description: 'Sign in to manage your multi-platform social studio',
};
