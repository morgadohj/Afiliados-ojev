import { Head, Link } from '@inertiajs/react';
import { Globe2, UserPlus, Users } from 'lucide-react';
import { dashboard } from '@/routes';

type AffiliateRow = {
    id: number;
    folio: string | null;
    full_name: string;
    application_date: string;
    branch: string;
    status: string;
    registered_by: {
        id: number;
        name: string;
    } | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    affiliates: {
        data: AffiliateRow[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        links: PaginationLink[];
    };
    summary: {
        total: number;
        administrative: number;
        public: number;
    };
};

const statusLabels: Record<string, string> = {
    submitted: 'Recibida',
    approved: 'Aprobada',
    rejected: 'Rechazada',
};

const statusStyles: Record<string, string> = {
    submitted: 'bg-amber-50 text-amber-800 ring-amber-600/20',
    approved: 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
    rejected: 'bg-red-50 text-red-800 ring-red-600/20',
};

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}

function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav aria-label="Paginación de afiliados" className="flex gap-1">
            {links.map((link, index) => (
                <Link
                    key={`${link.label}-${index}`}
                    href={link.url ?? '#'}
                    preserveScroll
                    className={`inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg px-3 text-sm font-medium transition ${
                        link.active
                            ? 'bg-foreground text-background'
                            : 'border border-border bg-background hover:bg-muted'
                    } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </nav>
    );
}

export default function Dashboard({ affiliates, summary }: Props) {
    const cards = [
        {
            label: 'Total de afiliados',
            value: summary.total,
            icon: Users,
            iconClass: 'bg-stone-900 text-white',
        },
        {
            label: 'Capturados por usuarios',
            value: summary.administrative,
            icon: UserPlus,
            iconClass: 'bg-[#f2cf6b] text-[#714b0f]',
        },
        {
            label: 'Registros públicos',
            value: summary.public,
            icon: Globe2,
            iconClass: 'bg-[#f3ead7] text-[#8a5d12]',
        },
    ];

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-hidden rounded-xl p-4 md:p-6">
                <section>
                    <p className="text-sm font-medium text-muted-foreground">
                        Gestión de afiliaciones
                    </p>
                    <div className="mt-1 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                                Afiliados OJEV
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Consulta los registros y quién realizó cada
                                alta.
                            </p>
                        </div>
                        <Link
                            href="/administracion/afiliar"
                            className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#b17b17] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#966411]"
                        >
                            <UserPlus className="size-4" />
                            Afiliar persona
                        </Link>
                    </div>
                </section>

                <section className="grid gap-4 sm:grid-cols-3">
                    {cards.map((card) => (
                        <article
                            key={card.label}
                            className="rounded-xl border border-border bg-card p-5 shadow-sm"
                        >
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        {card.label}
                                    </p>
                                    <strong className="mt-2 block text-3xl font-bold tabular-nums">
                                        {card.value}
                                    </strong>
                                </div>
                                <span
                                    className={`grid size-11 place-items-center rounded-xl ${card.iconClass}`}
                                >
                                    <card.icon className="size-5" />
                                </span>
                            </div>
                        </article>
                    ))}
                </section>

                <section className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div className="flex items-center justify-between gap-4 border-b border-border px-5 py-4">
                        <div>
                            <h2 className="font-semibold">
                                Listado de afiliados
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                {affiliates.total === 0
                                    ? 'Aún no hay registros.'
                                    : `${affiliates.total} afiliados registrados`}
                            </p>
                        </div>
                    </div>

                    {affiliates.data.length === 0 ? (
                        <div className="grid min-h-64 place-items-center px-6 py-12 text-center">
                            <div>
                                <span className="mx-auto grid size-12 place-items-center rounded-full bg-muted">
                                    <Users className="size-5 text-muted-foreground" />
                                </span>
                                <h3 className="mt-4 font-semibold">
                                    Sin afiliados registrados
                                </h3>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Los nuevos registros aparecerán en esta
                                    tabla.
                                </p>
                            </div>
                        </div>
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[820px] text-left text-sm">
                                    <thead className="bg-muted/60 text-xs tracking-wide text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-5 py-3 font-semibold">
                                                Folio
                                            </th>
                                            <th className="px-5 py-3 font-semibold">
                                                Afiliado
                                            </th>
                                            <th className="px-5 py-3 font-semibold">
                                                Fecha
                                            </th>
                                            <th className="px-5 py-3 font-semibold">
                                                Delegación
                                            </th>
                                            <th className="px-5 py-3 font-semibold">
                                                Estado
                                            </th>
                                            <th className="px-5 py-3 font-semibold">
                                                Dado de alta por
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {affiliates.data.map((affiliate) => (
                                            <tr
                                                key={affiliate.id}
                                                className="transition hover:bg-muted/35"
                                            >
                                                <td className="px-5 py-4 font-mono text-xs font-semibold whitespace-nowrap">
                                                    {affiliate.folio ??
                                                        'Pendiente'}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <strong className="font-semibold">
                                                        {affiliate.full_name}
                                                    </strong>
                                                </td>
                                                <td className="px-5 py-4 whitespace-nowrap text-muted-foreground">
                                                    {formatDate(
                                                        affiliate.application_date,
                                                    )}
                                                </td>
                                                <td className="max-w-56 truncate px-5 py-4 text-muted-foreground">
                                                    {affiliate.branch}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <span
                                                        className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset ${statusStyles[affiliate.status] ?? 'bg-muted text-muted-foreground ring-border'}`}
                                                    >
                                                        {statusLabels[
                                                            affiliate.status
                                                        ] ?? affiliate.status}
                                                    </span>
                                                </td>
                                                <td className="px-5 py-4">
                                                    {affiliate.registered_by ? (
                                                        <div className="flex items-center gap-2">
                                                            <span className="grid size-8 place-items-center rounded-full bg-amber-100 text-xs font-bold text-amber-900">
                                                                {affiliate.registered_by.name
                                                                    .slice(0, 1)
                                                                    .toUpperCase()}
                                                            </span>
                                                            <span className="font-medium">
                                                                {
                                                                    affiliate
                                                                        .registered_by
                                                                        .name
                                                                }
                                                            </span>
                                                        </div>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-2 text-muted-foreground">
                                                            <Globe2 className="size-4" />
                                                            Registro público
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="flex flex-col gap-3 border-t border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <p className="text-xs text-muted-foreground">
                                    Mostrando {affiliates.from}–{affiliates.to}{' '}
                                    de {affiliates.total}
                                </p>
                                <Pagination links={affiliates.links} />
                            </div>
                        </>
                    )}
                </section>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
