import { Form, Head, Link } from '@inertiajs/react';
import { ShieldCheck, UserPlus, Users } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: 'administrador' | 'afiliador';
    role_label: string;
    registered_affiliates_count: number;
    created_at: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    users: {
        data: UserRow[];
        total: number;
        from: number | null;
        to: number | null;
        links: PaginationLink[];
    };
    roles: Array<{ value: string; label: string }>;
};

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

export default function UsersIndex({ users, roles }: Props) {
    return (
        <>
            <Head title="Usuarios" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-hidden rounded-xl p-4 md:p-6">
                <section>
                    <p className="text-sm font-medium text-muted-foreground">
                        Administración
                    </p>
                    <h1 className="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">
                        Usuarios y perfiles
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Crea accesos y define lo que cada usuario puede
                        administrar.
                    </p>
                </section>

                <div className="grid items-start gap-6 xl:grid-cols-[minmax(320px,420px)_1fr]">
                    <section className="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <div className="flex items-start gap-3">
                            <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-[#f2cf6b] text-[#714b0f]">
                                <UserPlus className="size-5" />
                            </span>
                            <div>
                                <h2 className="font-semibold">
                                    Dar de alta un usuario
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    El acceso quedará activo inmediatamente.
                                </p>
                            </div>
                        </div>

                        <Form
                            action="/administracion/usuarios"
                            method="post"
                            resetOnSuccess
                            className="mt-6 space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">
                                            Nombre completo
                                        </Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            autoComplete="name"
                                            required
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="email">
                                            Correo electrónico
                                        </Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            autoComplete="off"
                                            required
                                        />
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="role">Perfil</Label>
                                        <select
                                            id="role"
                                            name="role"
                                            defaultValue="afiliador"
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus:border-ring focus:ring-3 focus:ring-ring/50"
                                            required
                                        >
                                            {roles.map((role) => (
                                                <option
                                                    key={role.value}
                                                    value={role.value}
                                                >
                                                    {role.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.role} />
                                        <p className="text-xs text-muted-foreground">
                                            Administrador gestiona usuarios;
                                            Afiliador registra afiliaciones.
                                        </p>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password">
                                            Contraseña temporal
                                        </Label>
                                        <Input
                                            id="password"
                                            name="password"
                                            type="password"
                                            autoComplete="new-password"
                                            required
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation">
                                            Confirmar contraseña
                                        </Label>
                                        <Input
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            type="password"
                                            autoComplete="new-password"
                                            required
                                        />
                                    </div>

                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="w-full bg-[#b17b17] text-white hover:bg-[#966411]"
                                    >
                                        <UserPlus />
                                        {processing
                                            ? 'Creando…'
                                            : 'Crear usuario'}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>

                    <section className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                        <div className="flex items-center justify-between border-b border-border px-5 py-4">
                            <div>
                                <h2 className="font-semibold">
                                    Usuarios registrados
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    {users.total}{' '}
                                    {users.total === 1 ? 'usuario' : 'usuarios'}
                                </p>
                            </div>
                            <Users className="size-5 text-[#b17b17]" />
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[680px] text-left text-sm">
                                <thead className="bg-muted/60 text-xs tracking-wide text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-5 py-3 font-semibold">
                                            Usuario
                                        </th>
                                        <th className="px-5 py-3 font-semibold">
                                            Perfil
                                        </th>
                                        <th className="px-5 py-3 text-center font-semibold">
                                            Afiliados
                                        </th>
                                        <th className="px-5 py-3 font-semibold">
                                            Alta
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {users.data.map((user) => (
                                        <tr
                                            key={user.id}
                                            className="transition hover:bg-muted/35"
                                        >
                                            <td className="px-5 py-4">
                                                <div className="flex items-center gap-3">
                                                    <span className="grid size-9 place-items-center rounded-full bg-[#f3ead7] font-bold text-[#8a5d12]">
                                                        {user.name
                                                            .slice(0, 1)
                                                            .toUpperCase()}
                                                    </span>
                                                    <div>
                                                        <strong className="block font-semibold">
                                                            {user.name}
                                                        </strong>
                                                        <span className="text-xs text-muted-foreground">
                                                            {user.email}
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-5 py-4">
                                                <span
                                                    className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset ${user.role === 'administrador' ? 'bg-amber-50 text-amber-900 ring-amber-600/20' : 'bg-stone-100 text-stone-700 ring-stone-500/20'}`}
                                                >
                                                    {user.role ===
                                                        'administrador' && (
                                                        <ShieldCheck className="size-3.5" />
                                                    )}
                                                    {user.role_label}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 text-center font-semibold tabular-nums">
                                                {
                                                    user.registered_affiliates_count
                                                }
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap text-muted-foreground">
                                                {formatDate(user.created_at)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {users.links.length > 3 && (
                            <div className="flex flex-col gap-3 border-t border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <p className="text-xs text-muted-foreground">
                                    Mostrando {users.from}–{users.to} de{' '}
                                    {users.total}
                                </p>
                                <nav
                                    aria-label="Paginación de usuarios"
                                    className="flex gap-1"
                                >
                                    {users.links.map((link, index) => (
                                        <Link
                                            key={`${link.label}-${index}`}
                                            href={link.url ?? '#'}
                                            preserveScroll
                                            className={`inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg px-3 text-sm font-medium transition ${link.active ? 'bg-foreground text-background' : 'border border-border bg-background hover:bg-muted'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    ))}
                                </nav>
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Usuarios',
            href: '/administracion/usuarios',
        },
    ],
};
