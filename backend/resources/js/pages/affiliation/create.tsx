import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    BadgeCheck,
    Camera,
    Check,
    FileCheck2,
    Focus,
    Images,
    Lightbulb,
    LoaderCircle,
    LockKeyhole,
    ScanLine,
    ShieldCheck,
    Sparkles,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { login } from '@/routes';

export type AffiliationFormProps = {
    ocrAvailable: boolean;
    administrative?: boolean;
};

type FormValues = {
    application_date: string;
    first_name: string;
    paternal_last_name: string;
    maternal_last_name: string;
    curp: string;
    birth_date: string;
    address_street: string;
    neighborhood: string;
    locality: string;
    municipality: string;
    state: string;
    postal_code: string;
    home_phone: string;
    mobile_phone: string;
    email: string;
    occupation: string;
    livestock_association: string;
    oje_v_branch: string;
    signature_name: string;
    consent: boolean;
};

type ExtractedField = {
    value: string;
    confidence: number;
    source: 'ine_ocr';
};

type ExtractionResponse = {
    message: string;
    fields: Partial<Record<keyof FormValues, ExtractedField>>;
    warnings: string[];
};

type ApiErrors = Record<string, string[]>;

const initialValues: FormValues = {
    application_date: new Date().toISOString().slice(0, 10),
    first_name: '',
    paternal_last_name: '',
    maternal_last_name: '',
    curp: '',
    birth_date: '',
    address_street: '',
    neighborhood: '',
    locality: '',
    municipality: '',
    state: 'Veracruz',
    postal_code: '',
    home_phone: '',
    mobile_phone: '',
    email: '',
    occupation: '',
    livestock_association: '',
    oje_v_branch: '',
    signature_name: '',
    consent: false,
};

const stepTwoRequiredFields: Array<keyof FormValues> = [
    'first_name',
    'paternal_last_name',
    'curp',
    'birth_date',
    'address_street',
    'neighborhood',
    'locality',
    'municipality',
    'state',
    'postal_code',
    'mobile_phone',
    'email',
    'occupation',
    'oje_v_branch',
];

const fieldLabels: Partial<Record<keyof FormValues, string>> = {
    first_name: 'Nombre(s)',
    paternal_last_name: 'Apellido paterno',
    curp: 'CURP',
    birth_date: 'Fecha de nacimiento',
    address_street: 'Calle y número',
    neighborhood: 'Colonia',
    locality: 'Localidad',
    municipality: 'Municipio',
    state: 'Entidad',
    postal_code: 'Código postal',
    mobile_phone: 'Teléfono celular',
    email: 'Correo electrónico',
    occupation: 'Ocupación u oficio',
    oje_v_branch: 'Delegación o grupo filial OJEV',
};

const states = [
    'Aguascalientes',
    'Baja California',
    'Baja California Sur',
    'Campeche',
    'Chiapas',
    'Chihuahua',
    'Ciudad de México',
    'Coahuila',
    'Colima',
    'Durango',
    'Estado de México',
    'Guanajuato',
    'Guerrero',
    'Hidalgo',
    'Jalisco',
    'Michoacán',
    'Morelos',
    'Nayarit',
    'Nuevo León',
    'Oaxaca',
    'Puebla',
    'Querétaro',
    'Quintana Roo',
    'San Luis Potosí',
    'Sinaloa',
    'Sonora',
    'Tabasco',
    'Tamaulipas',
    'Tlaxcala',
    'Veracruz',
    'Yucatán',
    'Zacatecas',
];

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function Field({
    label,
    name,
    value,
    onChange,
    error,
    suggested,
    optional,
    className = '',
    ...props
}: {
    label: string;
    name: keyof FormValues;
    value: string;
    onChange: (name: keyof FormValues, value: string) => void;
    error?: string;
    suggested?: boolean;
    optional?: boolean;
    className?: string;
} & Omit<
    React.InputHTMLAttributes<HTMLInputElement>,
    'name' | 'value' | 'onChange'
>) {
    return (
        <label className={`grid gap-2 ${className}`}>
            <span className="flex items-center gap-2 text-sm font-semibold text-stone-700">
                {label}
                {optional && (
                    <span className="font-normal text-stone-400">Opcional</span>
                )}
                {suggested && (
                    <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800">
                        <Sparkles className="size-3" />
                        Sugerido
                    </span>
                )}
            </span>
            <input
                name={name}
                value={value}
                onChange={(event) => onChange(name, event.target.value)}
                className={`h-12 rounded-xl border bg-white px-4 text-[15px] text-stone-900 transition outline-none placeholder:text-stone-400 focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 ${
                    error ? 'border-red-400' : 'border-stone-200'
                }`}
                {...props}
            />
            {error && <span className="text-xs text-red-600">{error}</span>}
        </label>
    );
}

function UploadCard({
    title,
    help,
    file,
    onChange,
    required = true,
    error,
    onOpenCamera,
    captureMode = 'environment',
}: {
    title: string;
    help: string;
    file: File | null;
    onChange: (file: File | null) => void;
    required?: boolean;
    error?: string;
    onOpenCamera?: () => void;
    captureMode?: 'environment' | 'user';
}) {
    const cameraInputRef = useRef<HTMLInputElement>(null);
    const galleryInputRef = useRef<HTMLInputElement>(null);
    const preview = useMemo(
        () => (file ? URL.createObjectURL(file) : null),
        [file],
    );

    return (
        <div>
            <button
                type="button"
                onClick={() => cameraInputRef.current?.click()}
                className={`group relative flex min-h-56 w-full overflow-hidden rounded-2xl border-2 border-dashed bg-stone-50 text-left transition hover:border-amber-500 hover:bg-amber-50/50 ${
                    error ? 'border-red-400' : 'border-stone-250'
                }`}
            >
                {preview ? (
                    <>
                        <img
                            src={preview}
                            alt={`Vista previa de ${title}`}
                            className="absolute inset-0 h-full w-full object-cover"
                        />
                        <span className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/5 to-transparent" />
                        <span className="absolute right-3 bottom-3 left-3 flex items-center justify-between text-white">
                            <span>
                                <strong className="block text-sm">
                                    {title}
                                </strong>
                                <span className="text-xs text-white/75">
                                    {file?.name}
                                </span>
                            </span>
                            <span className="rounded-full bg-white/95 p-2 text-stone-900">
                                <Camera className="size-4" />
                            </span>
                        </span>
                    </>
                ) : (
                    <span className="m-auto flex max-w-60 flex-col items-center px-5 text-center">
                        <span className="mb-4 rounded-2xl bg-white p-3 text-amber-700 shadow-sm ring-1 ring-stone-200">
                            <Camera className="size-6" />
                        </span>
                        <strong className="text-sm text-stone-800">
                            {title}
                            {!required && (
                                <span className="font-normal text-stone-400">
                                    {' '}
                                    · opcional
                                </span>
                            )}
                        </strong>
                        <span className="mt-1 text-xs leading-5 text-stone-500">
                            {help}
                        </span>
                        <span className="mt-4 inline-flex items-center gap-2 text-xs font-bold text-amber-800">
                            <Camera className="size-3.5" />
                            Tomar foto con autofocus
                        </span>
                    </span>
                )}
            </button>
            <input
                ref={cameraInputRef}
                type="file"
                accept="image/*"
                capture={captureMode}
                className="hidden"
                onChange={(event) => {
                    onChange(event.target.files?.[0] ?? null);
                    event.currentTarget.value = '';
                }}
            />
            <input
                ref={galleryInputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="hidden"
                onChange={(event) => {
                    onChange(event.target.files?.[0] ?? null);
                    event.currentTarget.value = '';
                }}
            />
            <div className="mt-3 flex flex-wrap gap-x-5 gap-y-3">
                <button
                    type="button"
                    onClick={() => galleryInputRef.current?.click()}
                    className="inline-flex items-center gap-2 text-xs font-bold text-stone-500 underline decoration-stone-300 underline-offset-4 hover:text-amber-800"
                >
                    <Images className="size-3.5" />
                    Elegir una foto existente
                </button>
                {onOpenCamera && (
                    <button
                        type="button"
                        onClick={onOpenCamera}
                        className="inline-flex items-center gap-2 text-xs font-bold text-stone-500 underline decoration-stone-300 underline-offset-4 hover:text-amber-800"
                    >
                        <ScanLine className="size-3.5" />
                        Usar cámara guiada
                    </button>
                )}
            </div>
            {error && <p className="mt-2 text-xs text-red-600">{error}</p>}
        </div>
    );
}

type CameraCapabilities = MediaTrackCapabilities & {
    focusMode?: string[];
    exposureMode?: string[];
    torch?: boolean | boolean[];
};

type CameraSupportedConstraints = MediaTrackSupportedConstraints & {
    pointsOfInterest?: boolean;
};

type CameraConstraintSet = MediaTrackConstraintSet & {
    focusMode?: string;
    exposureMode?: string;
    pointsOfInterest?: Array<{ x: number; y: number }>;
    torch?: boolean;
};

function CameraCapture({
    title,
    documentMode,
    onCapture,
    onClose,
}: {
    title: string;
    documentMode: boolean;
    onCapture: (file: File) => void;
    onClose: () => void;
}) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const [cameraError, setCameraError] = useState<string | null>(null);
    const [ready, setReady] = useState(false);
    const [torchAvailable, setTorchAvailable] = useState(false);
    const [torchEnabled, setTorchEnabled] = useState(false);
    const [continuousFocusAvailable, setContinuousFocusAvailable] =
        useState(false);
    const [lightLevel, setLightLevel] = useState<
        'checking' | 'low' | 'good' | 'high'
    >('checking');
    const [focusPoint, setFocusPoint] = useState<{
        x: number;
        y: number;
    } | null>(null);

    const currentTrack = () => streamRef.current?.getVideoTracks()[0];

    const capabilitiesFor = (track: MediaStreamTrack): CameraCapabilities =>
        typeof track.getCapabilities === 'function'
            ? (track.getCapabilities() as CameraCapabilities)
            : ({} as CameraCapabilities);

    const applyAdvanced = async (constraints: CameraConstraintSet) => {
        const track = currentTrack();

        if (!track) {
            return;
        }

        await track.applyConstraints({
            advanced: [constraints] as MediaTrackConstraintSet[],
        });
    };

    useEffect(() => {
        let active = true;
        let lightTimer: number | undefined;
        let focusTimer: number | undefined;

        const startCamera = async () => {
            if (!navigator.mediaDevices?.getUserMedia) {
                setCameraError(
                    'Este navegador no permite controlar la cámara. Usa la opción para elegir o tomar una foto con el teléfono.',
                );

                return;
            }

            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    audio: false,
                    video: {
                        facingMode: {
                            ideal: documentMode ? 'environment' : 'user',
                        },
                        width: { ideal: 2560 },
                        height: { ideal: 1440 },
                    },
                });

                if (!active) {
                    stream.getTracks().forEach((track) => track.stop());

                    return;
                }

                streamRef.current = stream;
                const track = stream.getVideoTracks()[0];
                const capabilities = capabilitiesFor(track);
                const supportsContinuousFocus =
                    capabilities.focusMode?.includes('continuous') ?? false;
                setContinuousFocusAvailable(supportsContinuousFocus);
                setTorchAvailable(
                    Array.isArray(capabilities.torch)
                        ? capabilities.torch.includes(true)
                        : capabilities.torch === true,
                );

                const initialConstraints: CameraConstraintSet = {};

                if (capabilities.focusMode?.includes('continuous')) {
                    initialConstraints.focusMode = 'continuous';
                }

                if (capabilities.exposureMode?.includes('continuous')) {
                    initialConstraints.exposureMode = 'continuous';
                }

                if (Object.keys(initialConstraints).length > 0) {
                    try {
                        await track.applyConstraints({
                            advanced: [
                                initialConstraints,
                            ] as MediaTrackConstraintSet[],
                        });
                    } catch {
                        // Keep the camera available when a device rejects optional controls.
                    }
                }

                if (supportsContinuousFocus) {
                    focusTimer = window.setInterval(() => {
                        void track
                            .applyConstraints({
                                advanced: [
                                    { focusMode: 'continuous' },
                                ] as CameraConstraintSet[] as MediaTrackConstraintSet[],
                            })
                            .catch(() => {
                                setContinuousFocusAvailable(false);
                                window.clearInterval(focusTimer);
                            });
                    }, 1800);
                }

                if (videoRef.current) {
                    videoRef.current.srcObject = stream;
                    await videoRef.current.play();
                    setReady(true);
                }

                lightTimer = window.setInterval(() => {
                    const video = videoRef.current;

                    if (!video || video.readyState < 2) {
                        return;
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = 48;
                    canvas.height = 32;
                    const context = canvas.getContext('2d', {
                        willReadFrequently: true,
                    });

                    if (!context) {
                        return;
                    }

                    context.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const pixels = context.getImageData(
                        0,
                        0,
                        canvas.width,
                        canvas.height,
                    ).data;
                    let luminance = 0;

                    for (let index = 0; index < pixels.length; index += 4) {
                        luminance +=
                            pixels[index] * 0.2126 +
                            pixels[index + 1] * 0.7152 +
                            pixels[index + 2] * 0.0722;
                    }

                    const average = luminance / (pixels.length / 4);
                    setLightLevel(
                        average < 65 ? 'low' : average > 220 ? 'high' : 'good',
                    );
                }, 700);
            } catch {
                setCameraError(
                    'No fue posible abrir la cámara. Revisa el permiso de cámara o usa una foto existente.',
                );
            }
        };

        void startCamera();

        return () => {
            active = false;

            if (lightTimer) {
                window.clearInterval(lightTimer);
            }

            if (focusTimer) {
                window.clearInterval(focusTimer);
            }

            streamRef.current?.getTracks().forEach((track) => track.stop());
        };
    }, [documentMode]);

    const focus = async (event?: React.PointerEvent<HTMLVideoElement>) => {
        const track = currentTrack();

        if (!track) {
            return;
        }

        const capabilities = capabilitiesFor(track);
        const supportedConstraints =
            navigator.mediaDevices.getSupportedConstraints() as CameraSupportedConstraints;
        const constraints: CameraConstraintSet = {};

        if (capabilities.focusMode?.includes('single-shot')) {
            constraints.focusMode = 'single-shot';
        } else if (capabilities.focusMode?.includes('continuous')) {
            constraints.focusMode = 'continuous';
        }

        if (event && videoRef.current) {
            const bounds = videoRef.current.getBoundingClientRect();
            const relativeX = Math.max(
                0,
                Math.min(1, (event.clientX - bounds.left) / bounds.width),
            );
            const relativeY = Math.max(
                0,
                Math.min(1, (event.clientY - bounds.top) / bounds.height),
            );
            setFocusPoint({ x: relativeX * 100, y: relativeY * 100 });

            if (supportedConstraints.pointsOfInterest) {
                constraints.pointsOfInterest = [
                    {
                        x: relativeX * videoRef.current.videoWidth,
                        y: relativeY * videoRef.current.videoHeight,
                    },
                ];
            }
        } else {
            setFocusPoint({ x: 50, y: 50 });
        }

        try {
            await applyAdvanced(constraints);
        } catch {
            // Some iPhones report focus capabilities but reject manual changes.
        }

        window.setTimeout(() => setFocusPoint(null), 900);
    };

    const toggleTorch = async () => {
        const next = !torchEnabled;

        try {
            await applyAdvanced({ torch: next });
            setTorchEnabled(next);
        } catch {
            setTorchAvailable(false);
        }
    };

    const capture = () => {
        const video = videoRef.current;

        if (!video || !video.videoWidth || !video.videoHeight) {
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const context = canvas.getContext('2d');

        if (!context) {
            return;
        }

        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    return;
                }

                onCapture(
                    new File([blob], `ine-${Date.now()}.jpg`, {
                        type: 'image/jpeg',
                    }),
                );
            },
            'image/jpeg',
            0.94,
        );
    };

    const lightMessage = {
        checking: 'Comprobando iluminación…',
        low: 'Hay poca luz: activa la lámpara o busca más iluminación.',
        good: 'Iluminación adecuada.',
        high: 'Hay demasiado brillo: evita reflejos sobre la INE.',
    }[lightLevel];

    return (
        <div className="fixed inset-0 z-[100] flex flex-col bg-stone-950 text-white">
            <header className="flex items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <div>
                    <p className="text-xs font-bold text-amber-300">
                        Cámara OJEV
                    </p>
                    <h2 className="font-black">{title}</h2>
                </div>
                <button
                    type="button"
                    onClick={onClose}
                    className="grid size-11 place-items-center rounded-full bg-white/10"
                    aria-label="Cerrar cámara"
                >
                    <X className="size-5" />
                </button>
            </header>

            <div className="relative min-h-0 flex-1 overflow-hidden bg-black">
                {cameraError ? (
                    <div className="grid h-full place-items-center p-8 text-center">
                        <div className="max-w-sm">
                            <Camera className="mx-auto size-10 text-amber-300" />
                            <p className="mt-4 text-sm leading-6 text-stone-200">
                                {cameraError}
                            </p>
                            <button
                                type="button"
                                onClick={onClose}
                                className="mt-6 rounded-xl bg-white px-5 py-3 text-sm font-bold text-stone-900"
                            >
                                Volver al formulario
                            </button>
                        </div>
                    </div>
                ) : (
                    <>
                        <video
                            ref={videoRef}
                            autoPlay
                            muted
                            playsInline
                            onPointerDown={(event) => void focus(event)}
                            className="h-full w-full object-cover"
                        />
                        {documentMode && (
                            <div className="pointer-events-none absolute top-1/2 left-[5%] aspect-[1.586/1] w-[90%] -translate-y-1/2 rounded-2xl border-2 border-white/85 shadow-[0_0_0_9999px_rgba(0,0,0,0.28)] sm:left-[12%] sm:w-[76%]" />
                        )}
                        <p className="pointer-events-none absolute top-[12%] left-1/2 -translate-x-1/2 rounded-full bg-black/60 px-4 py-2 text-center text-xs font-bold backdrop-blur sm:top-[10%]">
                            {documentMode
                                ? 'Incluye las cuatro esquinas · toca para enfocar'
                                : 'Centra el rostro · toca para enfocar'}
                        </p>
                        {focusPoint && (
                            <span
                                className="pointer-events-none absolute size-16 -translate-x-1/2 -translate-y-1/2 animate-pulse rounded-xl border-2 border-amber-300"
                                style={{
                                    left: `${focusPoint.x}%`,
                                    top: `${focusPoint.y}%`,
                                }}
                            />
                        )}
                    </>
                )}
            </div>

            {!cameraError && (
                <footer className="space-y-4 px-4 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-6">
                    <p
                        className={`text-center text-xs font-bold ${lightLevel === 'good' ? 'text-emerald-300' : 'text-amber-300'}`}
                    >
                        {lightMessage}
                    </p>
                    <p
                        className={`text-center text-xs ${continuousFocusAvailable ? 'text-emerald-300' : 'text-stone-300'}`}
                    >
                        {continuousFocusAvailable
                            ? 'Autofocus continuo activo.'
                            : 'El navegador no permite controlar el autofocus; usa la cámara nativa para mejor enfoque.'}
                    </p>
                    <div className="grid grid-cols-3 items-center">
                        <button
                            type="button"
                            onClick={() => void toggleTorch()}
                            disabled={!torchAvailable}
                            className="mx-auto inline-flex min-h-11 items-center gap-2 rounded-full bg-white/10 px-4 text-xs font-bold disabled:opacity-35"
                        >
                            <Lightbulb className="size-4" />
                            {torchEnabled ? 'Apagar luz' : 'Encender luz'}
                        </button>
                        <button
                            type="button"
                            onClick={capture}
                            disabled={!ready}
                            className="mx-auto grid size-18 place-items-center rounded-full border-4 border-white bg-amber-500 shadow-lg disabled:opacity-40"
                            aria-label="Tomar fotografía"
                        >
                            <span className="size-12 rounded-full border border-amber-700/30 bg-amber-400" />
                        </button>
                        <button
                            type="button"
                            onClick={() => void focus()}
                            disabled={!ready}
                            className="mx-auto inline-flex min-h-11 items-center gap-2 rounded-full bg-white/10 px-4 text-xs font-bold disabled:opacity-35"
                        >
                            <Focus className="size-4" />
                            Enfocar
                        </button>
                    </div>
                </footer>
            )}
        </div>
    );
}

export default function CreateAffiliation({
    ocrAvailable,
    administrative = false,
}: AffiliationFormProps) {
    const [step, setStep] = useState(1);
    const [values, setValues] = useState(initialValues);
    const [ineFront, setIneFront] = useState<File | null>(null);
    const [ineBack, setIneBack] = useState<File | null>(null);
    const [profilePhoto, setProfilePhoto] = useState<File | null>(null);
    const [suggestedFields, setSuggestedFields] = useState<
        Set<keyof FormValues>
    >(new Set());
    const [ocrMetadata, setOcrMetadata] = useState<
        ExtractionResponse['fields']
    >({});
    const [warnings, setWarnings] = useState<string[]>([]);
    const [errors, setErrors] = useState<ApiErrors>({});
    const [extracting, setExtracting] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);
    const [folio, setFolio] = useState<string | null>(null);
    const [cameraTarget, setCameraTarget] = useState<
        'ine_front' | 'ine_back' | 'profile_photo' | null
    >(null);

    const updateValue = (name: keyof FormValues, value: string | boolean) => {
        setValues((current) => ({ ...current, [name]: value }));
        setErrors((current) => ({ ...current, [name]: [] }));
    };

    const extractIne = async () => {
        if (!ineFront) {
            setErrors({
                ine_front: ['Toma o selecciona el frente de tu INE.'],
            });

            return;
        }

        setExtracting(true);
        setNotice(null);
        setErrors({});
        const payload = new FormData();
        payload.append('ine_front', ineFront);

        if (ineBack) {
            payload.append('ine_back', ineBack);
        }

        try {
            const response = await fetch(
                administrative
                    ? '/administracion/afiliar/extraer-ine'
                    : '/afiliacion/extraer-ine',
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: payload,
                },
            );
            const data = (await response.json()) as
                ExtractionResponse | { message: string; errors?: ApiErrors };

            if (!response.ok || !('fields' in data)) {
                setNotice(data.message);

                if ('errors' in data && data.errors) {
                    setErrors(data.errors);
                }

                return;
            }

            const nextValues = { ...values };
            const nextSuggested = new Set<keyof FormValues>();

            Object.entries(data.fields).forEach(([key, field]) => {
                if (!field?.value) {
                    return;
                }

                const typedKey = key as keyof FormValues;

                if (typeof nextValues[typedKey] === 'string') {
                    (nextValues[typedKey] as string) = field.value;
                    nextSuggested.add(typedKey);
                }
            });

            setValues(nextValues);
            setSuggestedFields(nextSuggested);
            setOcrMetadata(data.fields);
            setWarnings(data.warnings);
            setNotice(data.message);
            setStep(2);
        } catch {
            setNotice(
                'No fue posible analizar la imagen. Puedes continuar capturando los datos manualmente.',
            );
        } finally {
            setExtracting(false);
        }
    };

    const validateStepTwo = (): boolean => {
        const nextErrors: ApiErrors = {};

        stepTwoRequiredFields.forEach((field) => {
            const value = values[field];

            if (typeof value === 'string' && value.trim() === '') {
                nextErrors[field] = [
                    `El campo ${fieldLabels[field] ?? field} es obligatorio.`,
                ];
            }
        });

        if (
            values.curp &&
            !/^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d$/.test(values.curp)
        ) {
            nextErrors.curp = [
                'La CURP debe contener 18 caracteres y tener un formato válido.',
            ];
        }

        if (values.postal_code && !/^\d{5}$/.test(values.postal_code)) {
            nextErrors.postal_code = [
                'El código postal debe contener 5 números.',
            ];
        }

        if (values.email && !/^\S+@\S+\.\S+$/.test(values.email)) {
            nextErrors.email = ['Escribe un correo electrónico válido.'];
        }

        if (Object.keys(nextErrors).length > 0) {
            setErrors(nextErrors);
            setNotice(
                `Faltan ${Object.keys(nextErrors).length} campos por revisar. Los marcamos en rojo.`,
            );
            setStep(2);
            window.scrollTo({ top: 0, behavior: 'smooth' });

            return false;
        }

        return true;
    };

    const goToConfirmation = () => {
        if (!validateStepTwo()) {
            return;
        }

        if (!values.signature_name.trim()) {
            updateValue(
                'signature_name',
                [
                    values.first_name,
                    values.paternal_last_name,
                    values.maternal_last_name,
                ]
                    .filter(Boolean)
                    .join(' '),
            );
        }

        setNotice(null);
        setStep(3);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const stepForServerErrors = (serverErrors: ApiErrors): 1 | 2 | 3 => {
        const keys = Object.keys(serverErrors);

        if (keys.some((key) => ['ine_front', 'ine_back'].includes(key))) {
            return 1;
        }

        if (
            keys.some((key) =>
                stepTwoRequiredFields.includes(key as keyof FormValues),
            )
        ) {
            return 2;
        }

        return 3;
    };

    const handleCameraCapture = (file: File) => {
        if (cameraTarget === 'ine_front') {
            setIneFront(file);
            setErrors((current) => ({ ...current, ine_front: [] }));
        } else if (cameraTarget === 'ine_back') {
            setIneBack(file);
            setErrors((current) => ({ ...current, ine_back: [] }));
        } else if (cameraTarget === 'profile_photo') {
            setProfilePhoto(file);
            setErrors((current) => ({ ...current, profile_photo: [] }));
        }

        setCameraTarget(null);
    };

    const submit = async (event: FormEvent) => {
        event.preventDefault();

        if (!ineFront || !ineBack) {
            setErrors({
                ...(!ineFront
                    ? { ine_front: ['La fotografía frontal es obligatoria.'] }
                    : {}),
                ...(!ineBack
                    ? { ine_back: ['La fotografía posterior es obligatoria.'] }
                    : {}),
            });
            setStep(1);

            return;
        }

        setSubmitting(true);
        setErrors({});
        setNotice(null);

        const payload = new FormData();
        Object.entries(values).forEach(([key, value]) => {
            payload.append(
                key,
                typeof value === 'boolean' ? (value ? '1' : '0') : value,
            );
        });
        payload.append('ine_front', ineFront);
        payload.append('ine_back', ineBack);

        if (profilePhoto) {
            payload.append('profile_photo', profilePhoto);
        }

        payload.append('ocr_metadata', JSON.stringify(ocrMetadata));

        try {
            const response = await fetch(
                administrative ? '/administracion/afiliar' : '/afiliacion',
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: payload,
                },
            );
            const data = (await response.json()) as {
                message: string;
                folio?: string;
                errors?: ApiErrors;
            };

            if (!response.ok) {
                const serverErrors = data.errors ?? {};
                setErrors(serverErrors);
                setStep(stepForServerErrors(serverErrors));
                setNotice(
                    Object.keys(serverErrors).length > 0
                        ? `No se guardó la solicitud. Revisa los ${Object.keys(serverErrors).length} campos marcados en rojo.`
                        : data.message,
                );
                window.scrollTo({ top: 0, behavior: 'smooth' });

                return;
            }

            setFolio(data.folio ?? null);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch {
            setNotice(
                'No fue posible enviar la solicitud. Revisa tu conexión e intenta nuevamente.',
            );
        } finally {
            setSubmitting(false);
        }
    };

    if (folio) {
        return (
            <>
                <Head title="Solicitud recibida" />
                <main className="grid min-h-screen place-items-center bg-[#f5f1e8] p-6">
                    <section className="w-full max-w-xl rounded-[2rem] bg-white p-8 text-center shadow-xl ring-1 shadow-stone-900/5 ring-stone-200 sm:p-12">
                        <div className="mx-auto mb-6 grid size-20 place-items-center rounded-full bg-emerald-50 text-emerald-700">
                            <BadgeCheck className="size-10" />
                        </div>
                        <p className="text-xs font-black tracking-[0.22em] text-amber-700 uppercase">
                            Solicitud recibida
                        </p>
                        <h1 className="mt-3 text-3xl font-black tracking-tight text-stone-900">
                            {administrative
                                ? 'Afiliación registrada'
                                : 'Gracias por afiliarte a OJEV'}
                        </h1>
                        <p className="mt-4 leading-7 text-stone-600">
                            {administrative
                                ? 'El registro quedó vinculado a tu usuario. Conserva el folio para dar seguimiento a la afiliación.'
                                : 'Conserva tu folio. La delegación revisará tus datos y documentos antes de aprobar la afiliación.'}
                        </p>
                        <div className="mt-8 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-5">
                            <span className="block text-xs font-bold tracking-wider text-amber-700 uppercase">
                                Folio
                            </span>
                            <strong className="mt-1 block text-2xl tracking-wide text-stone-900">
                                {folio}
                            </strong>
                        </div>
                    </section>
                </main>
            </>
        );
    }

    return (
        <>
            <Head title="Registro de afiliación" />
            {cameraTarget && (
                <CameraCapture
                    title={
                        cameraTarget === 'ine_front'
                            ? 'Frente de la INE'
                            : cameraTarget === 'ine_back'
                              ? 'Reverso de la INE'
                              : 'Foto del afiliado'
                    }
                    documentMode={cameraTarget !== 'profile_photo'}
                    onCapture={handleCameraCapture}
                    onClose={() => setCameraTarget(null)}
                />
            )}
            <div
                className={`${administrative ? 'min-h-full rounded-xl' : 'min-h-screen'} bg-[#f5f1e8] text-stone-900`}
            >
                {!administrative && (
                    <header className="border-b border-stone-200/80 bg-white">
                        <div className="mx-auto flex max-w-7xl items-center gap-4 px-5 py-4 sm:px-8">
                            <div className="grid size-12 shrink-0 place-items-center rounded-full border-2 border-[#b88618] bg-gradient-to-br from-[#f2cf6b] to-[#9c6911] text-xs font-black tracking-wider text-white shadow-inner">
                                OJEV
                            </div>
                            <div>
                                <strong className="block text-sm leading-tight">
                                    Jinetes del Estado de Veracruz
                                </strong>
                                <span className="text-xs text-stone-500">
                                    Asociación Civil
                                </span>
                            </div>
                            <div className="ml-auto flex items-center gap-3">
                                <span className="hidden items-center gap-2 text-xs font-semibold text-stone-500 md:flex">
                                    <LockKeyhole className="size-4 text-emerald-700" />
                                    Registro protegido
                                </span>
                                <Link
                                    href={login()}
                                    className="inline-flex h-9 items-center gap-2 rounded-lg border border-stone-200 px-3 text-xs font-bold text-stone-700 transition hover:border-amber-300 hover:bg-amber-50 hover:text-amber-800"
                                >
                                    <LockKeyhole className="size-3.5" />
                                    <span className="hidden sm:inline">
                                        Acceso administrativo
                                    </span>
                                    <span className="sm:hidden">Acceso</span>
                                </Link>
                            </div>
                        </div>
                    </header>
                )}

                <main className="mx-auto max-w-7xl px-5 py-8 sm:px-8 sm:py-12">
                    <section className="mb-8 grid gap-8 lg:grid-cols-[1fr_auto] lg:items-end">
                        <div className="max-w-3xl">
                            <span className="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800">
                                <ShieldCheck className="size-3.5" />
                                Formato oficial digital
                            </span>
                            <h1 className="mt-4 text-3xl font-black tracking-tight sm:text-5xl">
                                {administrative
                                    ? 'Registrar nuevo afiliado'
                                    : 'Registro de afiliación'}
                            </h1>
                            <p className="mt-3 max-w-2xl text-base leading-7 text-stone-600">
                                Fotografía la INE para completar los datos
                                posibles. Podrás revisar y corregir cada campo
                                antes de enviar.
                            </p>
                        </div>
                        <ol className="flex items-center rounded-2xl bg-white p-2 shadow-sm ring-1 ring-stone-200">
                            {[
                                [1, 'INE'],
                                [2, 'Datos'],
                                [3, 'Confirmar'],
                            ].map(([number, label], index) => (
                                <li
                                    key={number}
                                    className="flex items-center text-xs font-bold"
                                >
                                    {index > 0 && (
                                        <span className="mx-1 h-px w-4 bg-stone-200 sm:w-8" />
                                    )}
                                    <span
                                        className={`grid size-7 place-items-center rounded-full ${
                                            step >= Number(number)
                                                ? 'bg-stone-900 text-white'
                                                : 'bg-stone-100 text-stone-400'
                                        }`}
                                    >
                                        {step > Number(number) ? (
                                            <Check className="size-4" />
                                        ) : (
                                            number
                                        )}
                                    </span>
                                    <span
                                        className={`ml-2 hidden sm:inline ${
                                            step >= Number(number)
                                                ? 'text-stone-800'
                                                : 'text-stone-400'
                                        }`}
                                    >
                                        {label}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    </section>

                    {notice && (
                        <div className="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-950">
                            {notice}
                        </div>
                    )}

                    <form onSubmit={submit}>
                        {step === 1 && (
                            <section className="grid gap-6 lg:grid-cols-[1fr_340px]">
                                <div className="rounded-[1.75rem] bg-white p-5 shadow-sm ring-1 ring-stone-200 sm:p-8">
                                    <div className="mb-6 flex items-start gap-4">
                                        <div className="rounded-2xl bg-stone-900 p-3 text-white">
                                            <ScanLine className="size-6" />
                                        </div>
                                        <div>
                                            <h2 className="text-xl font-black">
                                                Captura tu INE
                                            </h2>
                                            <p className="mt-1 text-sm leading-6 text-stone-500">
                                                Colócala sobre una superficie
                                                plana, con buena luz y sin
                                                reflejos.
                                            </p>
                                        </div>
                                    </div>
                                    <div className="grid gap-5 md:grid-cols-2">
                                        <UploadCard
                                            title="Frente de la INE"
                                            help="Asegúrate de que nombre, domicilio y CURP sean legibles."
                                            file={ineFront}
                                            onChange={setIneFront}
                                            captureMode="environment"
                                            onOpenCamera={() =>
                                                setCameraTarget('ine_front')
                                            }
                                            error={errors.ine_front?.[0]}
                                        />
                                        <UploadCard
                                            title="Reverso de la INE"
                                            help="Incluye toda la credencial dentro del encuadre."
                                            file={ineBack}
                                            onChange={setIneBack}
                                            captureMode="environment"
                                            onOpenCamera={() =>
                                                setCameraTarget('ine_back')
                                            }
                                            error={errors.ine_back?.[0]}
                                        />
                                    </div>
                                    <div className="mt-6 flex flex-col gap-3 border-t border-stone-100 pt-6 sm:flex-row sm:items-center sm:justify-between">
                                        <p className="flex items-center gap-2 text-xs leading-5 text-stone-500">
                                            <LockKeyhole className="size-4 shrink-0 text-emerald-700" />
                                            Tus imágenes se procesan de forma
                                            privada y se almacenan cifradas.
                                        </p>
                                        <button
                                            type="button"
                                            onClick={extractIne}
                                            disabled={extracting || !ineFront}
                                            className="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-[#b17b17] px-6 text-sm font-black text-white shadow-sm transition hover:bg-[#966411] disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            {extracting ? (
                                                <LoaderCircle className="size-4 animate-spin" />
                                            ) : (
                                                <Sparkles className="size-4" />
                                            )}
                                            {extracting
                                                ? 'Leyendo credencial…'
                                                : 'Completar con mi INE'}
                                        </button>
                                    </div>
                                </div>

                                <aside className="rounded-[1.75rem] bg-stone-900 p-6 text-white sm:p-7">
                                    <span className="grid size-11 place-items-center rounded-2xl bg-white/10">
                                        <ShieldCheck className="size-5 text-amber-300" />
                                    </span>
                                    <h3 className="mt-5 text-lg font-black">
                                        Tú confirmas la información
                                    </h3>
                                    <p className="mt-2 text-sm leading-6 text-stone-300">
                                        El reconocimiento puede equivocarse. Los
                                        campos detectados se marcarán como
                                        sugerencias y permanecerán editables.
                                    </p>
                                    <ul className="mt-6 grid gap-4 text-sm text-stone-200">
                                        {[
                                            'No se envía nada en este paso.',
                                            'La CURP se valida antes del registro.',
                                            'Puedes continuar manualmente.',
                                        ].map((item) => (
                                            <li
                                                key={item}
                                                className="flex items-start gap-3"
                                            >
                                                <Check className="mt-0.5 size-4 shrink-0 text-amber-300" />
                                                {item}
                                            </li>
                                        ))}
                                    </ul>
                                    {!ocrAvailable && (
                                        <p className="mt-6 rounded-xl bg-white/10 p-3 text-xs leading-5 text-stone-300">
                                            OCR no disponible en este equipo; la
                                            captura manual sigue habilitada.
                                        </p>
                                    )}
                                    <button
                                        type="button"
                                        onClick={() => setStep(2)}
                                        className="mt-6 text-xs font-bold text-amber-300 underline decoration-amber-300/40 underline-offset-4"
                                    >
                                        Prefiero llenar los datos manualmente
                                    </button>
                                </aside>
                            </section>
                        )}

                        {step === 2 && (
                            <section className="rounded-[1.75rem] bg-white p-5 shadow-sm ring-1 ring-stone-200 sm:p-8">
                                <div className="flex flex-col gap-4 border-b border-stone-100 pb-6 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p className="text-xs font-black tracking-wider text-amber-700 uppercase">
                                            Paso 2 de 3
                                        </p>
                                        <h2 className="mt-1 text-2xl font-black">
                                            Revisa tus datos
                                        </h2>
                                        <p className="mt-1 text-sm text-stone-500">
                                            Basado en el formato de afiliación
                                            oficial OJEV.
                                        </p>
                                    </div>
                                    {suggestedFields.size > 0 && (
                                        <span className="inline-flex w-fit items-center gap-2 rounded-full bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">
                                            <Sparkles className="size-4" />
                                            {suggestedFields.size} campos
                                            sugeridos
                                        </span>
                                    )}
                                </div>

                                {warnings.length > 0 && (
                                    <div className="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-950">
                                        {warnings.join(' ')}
                                    </div>
                                )}

                                <div className="mt-8 grid gap-8">
                                    <fieldset>
                                        <legend className="mb-5 text-sm font-black tracking-wider text-stone-900 uppercase">
                                            Datos personales
                                        </legend>
                                        <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                                            <Field
                                                label="Nombre(s)"
                                                name="first_name"
                                                value={values.first_name}
                                                onChange={updateValue}
                                                error={errors.first_name?.[0]}
                                                suggested={suggestedFields.has(
                                                    'first_name',
                                                )}
                                                autoComplete="given-name"
                                            />
                                            <Field
                                                label="Apellido paterno"
                                                name="paternal_last_name"
                                                value={
                                                    values.paternal_last_name
                                                }
                                                onChange={updateValue}
                                                error={
                                                    errors
                                                        .paternal_last_name?.[0]
                                                }
                                                suggested={suggestedFields.has(
                                                    'paternal_last_name',
                                                )}
                                                autoComplete="family-name"
                                            />
                                            <Field
                                                label="Apellido materno"
                                                name="maternal_last_name"
                                                value={
                                                    values.maternal_last_name
                                                }
                                                onChange={updateValue}
                                                error={
                                                    errors
                                                        .maternal_last_name?.[0]
                                                }
                                                suggested={suggestedFields.has(
                                                    'maternal_last_name',
                                                )}
                                                optional
                                            />
                                            <Field
                                                label="CURP"
                                                name="curp"
                                                value={values.curp}
                                                onChange={(name, value) =>
                                                    updateValue(
                                                        name,
                                                        value.toUpperCase(),
                                                    )
                                                }
                                                error={errors.curp?.[0]}
                                                suggested={suggestedFields.has(
                                                    'curp',
                                                )}
                                                maxLength={18}
                                                className="lg:col-span-2"
                                            />
                                            <Field
                                                label="Fecha de nacimiento"
                                                name="birth_date"
                                                value={values.birth_date}
                                                onChange={updateValue}
                                                error={errors.birth_date?.[0]}
                                                suggested={suggestedFields.has(
                                                    'birth_date',
                                                )}
                                                type="date"
                                            />
                                        </div>
                                    </fieldset>

                                    <fieldset className="border-t border-stone-100 pt-8">
                                        <legend className="mb-5 text-sm font-black tracking-wider text-stone-900 uppercase">
                                            Domicilio
                                        </legend>
                                        <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-6">
                                            <Field
                                                label="Calle y número"
                                                name="address_street"
                                                value={values.address_street}
                                                onChange={updateValue}
                                                error={
                                                    errors.address_street?.[0]
                                                }
                                                suggested={suggestedFields.has(
                                                    'address_street',
                                                )}
                                                className="md:col-span-2 lg:col-span-4"
                                                autoComplete="street-address"
                                            />
                                            <Field
                                                label="Colonia"
                                                name="neighborhood"
                                                value={values.neighborhood}
                                                onChange={updateValue}
                                                error={errors.neighborhood?.[0]}
                                                suggested={suggestedFields.has(
                                                    'neighborhood',
                                                )}
                                                className="lg:col-span-2"
                                            />
                                            <Field
                                                label="Localidad"
                                                name="locality"
                                                value={values.locality}
                                                onChange={updateValue}
                                                error={errors.locality?.[0]}
                                                suggested={suggestedFields.has(
                                                    'locality',
                                                )}
                                                className="lg:col-span-2"
                                            />
                                            <Field
                                                label="Municipio"
                                                name="municipality"
                                                value={values.municipality}
                                                onChange={updateValue}
                                                error={errors.municipality?.[0]}
                                                className="lg:col-span-2"
                                            />
                                            <label className="grid gap-2 lg:col-span-1">
                                                <span className="flex items-center gap-2 text-sm font-semibold text-stone-700">
                                                    Entidad
                                                    {suggestedFields.has(
                                                        'state',
                                                    ) && (
                                                        <span className="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] text-amber-800">
                                                            Sugerido
                                                        </span>
                                                    )}
                                                </span>
                                                <select
                                                    value={values.state}
                                                    onChange={(event) =>
                                                        updateValue(
                                                            'state',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="h-12 rounded-xl border border-stone-200 bg-white px-3 text-sm outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10"
                                                >
                                                    {states.map((state) => (
                                                        <option key={state}>
                                                            {state}
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>
                                            <Field
                                                label="C.P."
                                                name="postal_code"
                                                value={values.postal_code}
                                                onChange={updateValue}
                                                error={errors.postal_code?.[0]}
                                                suggested={suggestedFields.has(
                                                    'postal_code',
                                                )}
                                                maxLength={5}
                                                inputMode="numeric"
                                                className="lg:col-span-1"
                                                autoComplete="postal-code"
                                            />
                                        </div>
                                    </fieldset>

                                    <fieldset className="border-t border-stone-100 pt-8">
                                        <legend className="mb-5 text-sm font-black tracking-wider text-stone-900 uppercase">
                                            Contacto y actividad
                                        </legend>
                                        <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                                            <Field
                                                label="Teléfono celular"
                                                name="mobile_phone"
                                                value={values.mobile_phone}
                                                onChange={updateValue}
                                                error={errors.mobile_phone?.[0]}
                                                type="tel"
                                                autoComplete="tel"
                                            />
                                            <Field
                                                label="Teléfono de casa u oficina"
                                                name="home_phone"
                                                value={values.home_phone}
                                                onChange={updateValue}
                                                error={errors.home_phone?.[0]}
                                                type="tel"
                                                optional
                                            />
                                            <Field
                                                label="Correo electrónico"
                                                name="email"
                                                value={values.email}
                                                onChange={updateValue}
                                                error={errors.email?.[0]}
                                                type="email"
                                                autoComplete="email"
                                            />
                                            <Field
                                                label="Ocupación u oficio"
                                                name="occupation"
                                                value={values.occupation}
                                                onChange={updateValue}
                                                error={errors.occupation?.[0]}
                                            />
                                            <Field
                                                label="Asociación ganadera"
                                                name="livestock_association"
                                                value={
                                                    values.livestock_association
                                                }
                                                onChange={updateValue}
                                                error={
                                                    errors
                                                        .livestock_association?.[0]
                                                }
                                                optional
                                            />
                                            <Field
                                                label="Delegación o grupo filial OJEV"
                                                name="oje_v_branch"
                                                value={values.oje_v_branch}
                                                onChange={updateValue}
                                                error={errors.oje_v_branch?.[0]}
                                            />
                                        </div>
                                    </fieldset>
                                </div>

                                <div className="mt-8 flex items-center justify-between border-t border-stone-100 pt-6">
                                    <button
                                        type="button"
                                        onClick={() => setStep(1)}
                                        className="inline-flex h-11 items-center gap-2 rounded-xl px-4 text-sm font-bold text-stone-600 hover:bg-stone-100"
                                    >
                                        <ArrowLeft className="size-4" />
                                        Volver
                                    </button>
                                    <button
                                        type="button"
                                        onClick={goToConfirmation}
                                        className="inline-flex h-12 items-center gap-2 rounded-xl bg-stone-900 px-6 text-sm font-black text-white hover:bg-stone-800"
                                    >
                                        Continuar
                                        <ArrowRight className="size-4" />
                                    </button>
                                </div>
                            </section>
                        )}

                        {step === 3 && (
                            <section className="grid gap-6 lg:grid-cols-[1fr_360px]">
                                <div className="rounded-[1.75rem] bg-white p-5 shadow-sm ring-1 ring-stone-200 sm:p-8">
                                    <p className="text-xs font-black tracking-wider text-amber-700 uppercase">
                                        Paso 3 de 3
                                    </p>
                                    <h2 className="mt-1 text-2xl font-black">
                                        Confirma tu solicitud
                                    </h2>
                                    <p className="mt-2 max-w-2xl text-sm leading-6 text-stone-500">
                                        Agrega una fotografía para tu expediente
                                        y confirma la declaración incluida en el
                                        formato físico.
                                    </p>

                                    <div className="mt-7 grid gap-6 md:grid-cols-[260px_1fr]">
                                        <UploadCard
                                            title="Foto del afiliado"
                                            help="Fotografía reciente, de frente y con el rostro visible."
                                            file={profilePhoto}
                                            onChange={setProfilePhoto}
                                            captureMode="user"
                                            onOpenCamera={() =>
                                                setCameraTarget('profile_photo')
                                            }
                                            required={false}
                                            error={errors.profile_photo?.[0]}
                                        />

                                        <div className="grid content-start gap-5">
                                            <div className="rounded-2xl border border-stone-200 bg-stone-50 p-5">
                                                <div className="flex items-start gap-3">
                                                    <FileCheck2 className="mt-0.5 size-5 shrink-0 text-amber-700" />
                                                    <p className="text-sm leading-6 text-stone-700">
                                                        Declaro voluntariamente
                                                        afiliarme a la
                                                        Asociación Civil{' '}
                                                        <strong>
                                                            Jinetes del Estado
                                                            de Veracruz, OJEV
                                                        </strong>
                                                        , estar de acuerdo con
                                                        las disposiciones de sus
                                                        estatutos y demostrar
                                                        con lealtad y honor el
                                                        emblema de la
                                                        Asociación.
                                                    </p>
                                                </div>
                                            </div>
                                            <Field
                                                label="Nombre completo para confirmación"
                                                name="signature_name"
                                                value={values.signature_name}
                                                onChange={updateValue}
                                                error={
                                                    errors.signature_name?.[0]
                                                }
                                                placeholder="Escribe tu nombre completo"
                                            />
                                            <label className="flex cursor-pointer items-start gap-3 rounded-2xl border border-stone-200 p-4 transition hover:bg-stone-50">
                                                <input
                                                    type="checkbox"
                                                    checked={values.consent}
                                                    onChange={(event) =>
                                                        updateValue(
                                                            'consent',
                                                            event.target
                                                                .checked,
                                                        )
                                                    }
                                                    className="mt-1 size-4 accent-amber-700"
                                                />
                                                <span className="text-sm leading-6 text-stone-600">
                                                    Confirmo que revisé los
                                                    datos, que son correctos y
                                                    que las imágenes
                                                    corresponden a mi
                                                    identificación.
                                                </span>
                                            </label>
                                            {errors.consent?.[0] && (
                                                <p className="text-xs text-red-600">
                                                    {errors.consent[0]}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <aside className="h-fit rounded-[1.75rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                                    <h3 className="font-black">
                                        Resumen de documentos
                                    </h3>
                                    <ul className="mt-5 grid gap-4">
                                        {[
                                            ['Frente de INE', ineFront],
                                            ['Reverso de INE', ineBack],
                                            ['Foto del afiliado', profilePhoto],
                                        ].map(([label, file]) => (
                                            <li
                                                key={String(label)}
                                                className="flex items-center gap-3 text-sm"
                                            >
                                                <span
                                                    className={`grid size-9 place-items-center rounded-full ${
                                                        file
                                                            ? 'bg-emerald-50 text-emerald-700'
                                                            : 'bg-stone-100 text-stone-400'
                                                    }`}
                                                >
                                                    {file ? (
                                                        <Check className="size-4" />
                                                    ) : (
                                                        <Camera className="size-4" />
                                                    )}
                                                </span>
                                                <span>
                                                    <strong className="block text-stone-700">
                                                        {label as string}
                                                    </strong>
                                                    <span className="text-xs text-stone-400">
                                                        {file
                                                            ? 'Adjunto'
                                                            : label ===
                                                                'Foto del afiliado'
                                                              ? 'Opcional'
                                                              : 'Pendiente'}
                                                    </span>
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                    <div className="mt-6 rounded-xl bg-emerald-50 p-3 text-xs leading-5 text-emerald-900">
                                        <LockKeyhole className="mr-1 inline size-3.5" />
                                        Los documentos se cifran antes de
                                        almacenarse.
                                    </div>
                                    <button
                                        type="submit"
                                        disabled={submitting || !values.consent}
                                        className="mt-6 inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#b17b17] px-5 text-sm font-black text-white transition hover:bg-[#966411] disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {submitting ? (
                                            <LoaderCircle className="size-4 animate-spin" />
                                        ) : (
                                            <FileCheck2 className="size-4" />
                                        )}
                                        {submitting
                                            ? 'Enviando solicitud…'
                                            : 'Enviar afiliación'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setStep(2)}
                                        className="mt-3 inline-flex h-10 w-full items-center justify-center gap-2 text-xs font-bold text-stone-500"
                                    >
                                        <ArrowLeft className="size-3.5" />
                                        Editar datos
                                    </button>
                                </aside>
                            </section>
                        )}
                    </form>
                </main>
                {!administrative && (
                    <footer className="border-t border-stone-200 px-5 py-6 text-center text-xs text-stone-500">
                        Jinetes del Estado de Veracruz OJEV, A.C. · Formulario
                        de afiliación
                    </footer>
                )}
            </div>
        </>
    );
}
