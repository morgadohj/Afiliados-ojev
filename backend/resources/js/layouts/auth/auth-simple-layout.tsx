import { Link } from '@inertiajs/react';
import { Check, LockKeyhole, ShieldCheck } from 'lucide-react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <main className="min-h-svh bg-[#f5f1e8] p-4 sm:p-6 lg:grid lg:place-items-center lg:p-10">
            <section className="mx-auto grid min-h-[calc(100svh-2rem)] w-full max-w-5xl overflow-hidden rounded-[2rem] bg-white shadow-2xl shadow-stone-900/10 ring-1 ring-stone-200 sm:min-h-0 lg:grid-cols-[1.05fr_0.95fr]">
                <aside className="relative hidden overflow-hidden bg-stone-900 p-10 text-white lg:flex lg:min-h-[650px] lg:flex-col">
                    <div className="absolute -top-32 -right-24 size-80 rounded-full border border-amber-300/15" />
                    <div className="absolute -top-16 -right-12 size-52 rounded-full border border-amber-300/15" />
                    <div className="relative flex items-center gap-3">
                        <span className="grid size-14 place-items-center rounded-full border-2 border-[#d5a63b] bg-gradient-to-br from-[#e4bd5d] to-[#93600d] text-sm font-black tracking-wider shadow-inner">
                            OJEV
                        </span>
                        <span>
                            <strong className="block text-sm">Jinetes del Estado de Veracruz</strong>
                            <span className="text-xs text-stone-400">Asociación Civil</span>
                        </span>
                    </div>

                    <div className="relative my-auto max-w-md">
                        <span className="mb-6 grid size-12 place-items-center rounded-2xl bg-white/10 text-amber-300">
                            <ShieldCheck className="size-6" />
                        </span>
                        <p className="text-xs font-black tracking-[0.2em] text-amber-300 uppercase">
                            Plataforma segura
                        </p>
                        <h2 className="mt-3 text-4xl font-black tracking-tight">
                            Gestión de afiliados OJEV
                        </h2>
                        <p className="mt-4 text-base leading-7 text-stone-300">
                            Consulta y administra las solicitudes desde un espacio privado para el equipo autorizado.
                        </p>
                        <ul className="mt-8 grid gap-4 text-sm text-stone-200">
                            {[
                                'Acceso protegido por credenciales',
                                'Sesiones seguras y cierre de sesión',
                                'Protección contra intentos repetidos',
                            ].map((item) => (
                                <li key={item} className="flex items-center gap-3">
                                    <span className="grid size-6 place-items-center rounded-full bg-amber-300/15 text-amber-300">
                                        <Check className="size-3.5" />
                                    </span>
                                    {item}
                                </li>
                            ))}
                        </ul>
                    </div>

                    <p className="relative flex items-center gap-2 text-xs text-stone-500">
                        <LockKeyhole className="size-3.5" />
                        Conexión protegida y datos de acceso cifrados
                    </p>
                </aside>

                <div className="flex items-center p-6 sm:p-10 lg:p-14">
                    <div className="mx-auto w-full max-w-sm">
                        <div className="mb-8 flex flex-col items-center gap-4 text-center lg:items-start lg:text-left">
                            <Link
                                href={home()}
                                className="flex items-center gap-3 font-medium lg:hidden"
                            >
                                <span className="grid size-12 place-items-center rounded-full border-2 border-[#bd8a22] bg-gradient-to-br from-[#e4bd5d] to-[#93600d] text-xs font-black tracking-wider text-white">
                                    OJEV
                                </span>
                                <span className="sr-only">{title}</span>
                            </Link>

                            <div className="space-y-2">
                                <p className="text-xs font-black tracking-[0.16em] text-amber-700 uppercase">
                                    Personal autorizado
                                </p>
                                <h1 className="text-2xl font-black tracking-tight text-stone-900">{title}</h1>
                                <p className="text-sm leading-6 text-stone-500">
                                    {description}
                                </p>
                            </div>
                        </div>
                        {children}
                        <Link
                            href={home()}
                            className="mt-8 block text-center text-xs font-semibold text-stone-500 hover:text-amber-700"
                        >
                            Volver al formulario de afiliación
                        </Link>
                    </div>
                </div>
            </section>
        </main>
    );
}
