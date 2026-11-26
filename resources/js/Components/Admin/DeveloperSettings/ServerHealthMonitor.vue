<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";

const streamUrl = route("admin.developer-settings.server-health.stream");
const eventSource = ref(null);
const snapshot = ref(null);
const streamState = ref("connecting");
const paused = ref(false);
const errorMessage = ref("");

const statusLabel = computed(() => {
    if (paused.value) {
        return "Paused";
    }

    return {
        connected: "Connected",
        connecting: "Connecting",
        disconnected: "Disconnected",
    }[streamState.value] ?? "Disconnected";
});

const statusClasses = computed(() => (
    streamState.value === "connected" && !paused.value
        ? "bg-server-console-accent text-server-console-base"
        : "border border-server-console-border/70 text-server-console-paper/70"
));

const diagnostics = computed(() => {
    const data = snapshot.value;

    if (!data) {
        return null;
    }

    return {
        cpu: data.cpu ?? {},
        load: data.load_average ?? {},
        ram: data.memory?.ram ?? {},
        swap: data.memory?.swap ?? {},
        disk: data.disk ?? {},
    };
});

const metricCards = computed(() => {
    const data = diagnostics.value;

    if (!data) {
        return [];
    }

    return [
        {
            key: "cpu",
            label: "CPU usage",
            value: formatPercent(data.cpu.usage_percent),
            detail: `${formatNumber(data.cpu.cores ?? 1)} core tersedia`,
            progress: data.cpu.usage_percent,
            icon: "pi pi-bolt",
        },
        {
            key: "load",
            label: "Load average",
            value: [
                formatDecimal(data.load.one),
                formatDecimal(data.load.five),
                formatDecimal(data.load.fifteen),
            ].join(" / "),
            detail: "1 menit / 5 menit / 15 menit",
            progress: null,
            icon: "pi pi-chart-line",
        },
        {
            key: "ram",
            label: "RAM usage",
            value: `${formatGigabytes(data.ram.used_gb)} / ${formatGigabytes(data.ram.total_gb)} GB`,
            detail: formatPercent(data.ram.percent),
            progress: data.ram.percent,
            icon: "pi pi-server",
        },
        {
            key: "swap",
            label: "Swap usage",
            value: `${formatGigabytes(data.swap.used_gb)} / ${formatGigabytes(data.swap.total_gb)} GB`,
            detail: formatPercent(data.swap.percent),
            progress: data.swap.percent,
            icon: "pi pi-database",
        },
        {
            key: "disk",
            label: "Disk space",
            value: `${formatGigabytes(data.disk.used_gb)} / ${formatGigabytes(data.disk.total_gb)} GB`,
            detail: `${formatPercent(data.disk.percent)} · ${data.disk.path || "volume aplikasi"}`,
            progress: data.disk.percent,
            icon: "pi pi-hard-drive",
        },
    ];
});

function connect() {
    closeStream();
    errorMessage.value = "";
    streamState.value = "connecting";

    if (typeof EventSource === "undefined") {
        streamState.value = "disconnected";
        errorMessage.value = "Browser ini tidak mendukung EventSource.";

        return;
    }

    const source = new EventSource(streamUrl, { withCredentials: true });
    eventSource.value = source;

    source.onopen = () => {
        streamState.value = "connected";
        errorMessage.value = "";
    };
    source.onerror = () => {
        streamState.value = "disconnected";
        errorMessage.value = "Stream terputus. Browser akan mencoba tersambung kembali.";
    };
    source.addEventListener("health", (event) => {
        try {
            snapshot.value = JSON.parse(event.data);
            streamState.value = "connected";
            errorMessage.value = "";
        } catch {
            streamState.value = "disconnected";
            errorMessage.value = "Payload metrik server tidak dapat dibaca.";
        }
    });
    source.addEventListener("health-error", (event) => {
        try {
            errorMessage.value = JSON.parse(event.data).message ?? "Metrik server gagal dibaca.";
        } catch {
            errorMessage.value = "Metrik server gagal dibaca.";
        }
    });
}

function closeStream() {
    if (!eventSource.value) {
        return;
    }

    eventSource.value.onopen = null;
    eventSource.value.onerror = null;
    eventSource.value.close();
    eventSource.value = null;
}

function pauseStream() {
    paused.value = true;
    closeStream();
    streamState.value = "disconnected";
    errorMessage.value = "";
}

function resumeStream() {
    paused.value = false;
    connect();
}

function toggleStream() {
    if (paused.value) {
        resumeStream();

        return;
    }

    pauseStream();
}

function handlePageExit() {
    closeStream();
}

function formatNumber(value) {
    return Number(value ?? 0).toLocaleString("id-ID");
}

function formatDecimal(value) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return "N/A";
    }

    return Number(value).toLocaleString("id-ID", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function formatPercent(value) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return "N/A";
    }

    return `${Number(value).toLocaleString("id-ID", {
        maximumFractionDigits: 1,
    })}%`;
}

function formatGigabytes(value) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return "N/A";
    }

    return Number(value).toLocaleString("id-ID", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function formatMegabytes(value) {
    return Number(value ?? 0).toLocaleString("id-ID", {
        maximumFractionDigits: 1,
    });
}

function progressWidth(value) {
    return `${Math.max(0, Math.min(100, Number(value) || 0))}%`;
}

function formatUptime(seconds) {
    if (seconds === null || seconds === undefined) {
        return "N/A";
    }

    const totalMinutes = Math.floor(Number(seconds) / 60);
    const days = Math.floor(totalMinutes / 1440);
    const hours = Math.floor((totalMinutes % 1440) / 60);
    const minutes = totalMinutes % 60;

    return `${formatNumber(days)} hari ${formatNumber(hours)} jam ${formatNumber(minutes)} menit`;
}

function formatDateTime(value) {
    if (!value) {
        return "N/A";
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
        timeStyle: "medium",
        timeZone: "Asia/Jakarta",
    }).format(date);
}

function serviceClasses(service) {
    if (!service.present) {
        return "border-server-console-border/40 text-server-console-paper/45";
    }

    return service.active
        ? "border-server-console-accent/60 bg-server-console-accent/10 text-server-console-accent"
        : "border-server-console-border/60 text-server-console-paper/60";
}

function sslClasses(status) {
    if (status === "healthy") {
        return "border-server-console-accent/60 bg-server-console-accent/10 text-server-console-accent";
    }

    if (status === "expiring" || status === "expired") {
        return "border-server-console-border bg-server-console-border/15 text-server-console-paper";
    }

    return "border-server-console-border/40 text-server-console-paper/55";
}

onMounted(() => {
    window.addEventListener("beforeunload", handlePageExit);
    window.addEventListener("pagehide", handlePageExit);
    connect();
});

onUnmounted(() => {
    window.removeEventListener("beforeunload", handlePageExit);
    window.removeEventListener("pagehide", handlePageExit);
    closeStream();
});
</script>

<template>
    <section class="overflow-hidden rounded-[1.75rem] border border-server-console-border/70 bg-server-console-base text-server-console-paper shadow-2xl shadow-server-console-base/30">
        <header class="flex flex-col gap-4 border-b border-server-console-border/40 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6">
            <div>
                <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.2em] text-server-console-accent">
                    <span class="h-2 w-2 rounded-full bg-server-console-accent shadow-[0_0_12px_var(--color-server-console-accent)]" />
                    Live resource telemetry
                </div>
                <h2 class="mt-2 text-xl font-extrabold tracking-[-0.03em] sm:text-2xl">
                    Server Health & Resource Monitor
                </h2>
                <p class="mt-2 max-w-3xl text-xs leading-5 text-server-console-paper/60">
                    Stream SSE aktif hanya selama panel ini terbuka. Koneksi ditutup saat panel, navigasi, atau tab ditinggalkan.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="inline-flex h-10 items-center gap-2 rounded-full px-3.5 text-xs font-bold"
                    :class="statusClasses"
                >
                    <span
                        class="h-2 w-2 rounded-full"
                        :class="streamState === 'connected' && !paused ? 'animate-pulse bg-server-console-base' : 'bg-current'"
                    />
                    {{ statusLabel }}
                </span>
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-full border border-server-console-border/80 px-4 text-xs font-bold text-server-console-paper transition hover:border-server-console-accent hover:text-server-console-accent focus:outline-none focus-visible:ring-2 focus-visible:ring-server-console-accent/70"
                    @click="toggleStream"
                >
                    <i :class="paused ? 'pi pi-play' : 'pi pi-pause'" />
                    {{ paused ? "Resume" : "Pause" }}
                </button>
            </div>
        </header>

        <div class="space-y-5 p-5 sm:p-6">
            <div
                v-if="errorMessage"
                class="rounded-2xl border border-server-console-border bg-server-console-border/15 px-4 py-3 text-xs leading-5 text-server-console-paper/80"
            >
                <i class="pi pi-info-circle mr-2 text-server-console-accent" />
                {{ errorMessage }}
            </div>

            <div v-if="!snapshot" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div
                    v-for="index in 5"
                    :key="index"
                    class="h-36 animate-pulse rounded-2xl border border-server-console-border/30 bg-server-console-paper/5"
                />
            </div>

            <template v-else>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <article
                        v-for="metric in metricCards"
                        :key="metric.key"
                        class="rounded-2xl border border-server-console-border/50 bg-server-console-paper/5 p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-server-console-paper/50">
                                {{ metric.label }}
                            </p>
                            <i :class="metric.icon" class="text-server-console-accent" />
                        </div>
                        <p class="mt-3 break-words font-mono text-lg font-bold tracking-[-0.03em] text-server-console-paper">
                            {{ metric.value }}
                        </p>
                        <p class="mt-1 truncate text-[11px] text-server-console-paper/45" :title="metric.detail">
                            {{ metric.detail }}
                        </p>
                        <div v-if="metric.progress !== null" class="mt-4 h-1.5 overflow-hidden rounded-full bg-server-console-border/45">
                            <div
                                class="h-full rounded-full bg-server-console-accent transition-[width] duration-500"
                                :style="{ width: progressWidth(metric.progress) }"
                            />
                        </div>
                    </article>
                </div>

                <div class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(280px,0.6fr)]">
                    <article class="rounded-2xl border border-server-console-border/50 bg-server-console-paper/5 p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold">Service health</h3>
                                <p class="mt-1 text-[11px] text-server-console-paper/45">Deteksi proses aktif saat snapshot dibaca.</p>
                            </div>
                            <i class="pi pi-heart-fill text-server-console-accent" />
                        </div>
                        <div class="mt-4 grid gap-2 sm:grid-cols-2">
                            <div
                                v-for="service in snapshot.services"
                                :key="service.key"
                                class="flex items-center justify-between gap-3 rounded-xl border px-3.5 py-3"
                                :class="serviceClasses(service)"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-bold">{{ service.label }}</p>
                                    <p class="mt-0.5 truncate text-[10px] opacity-60">{{ service.detail }}</p>
                                </div>
                                <span class="inline-flex shrink-0 items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current" />
                                    {{ service.present ? (service.active ? "Aktif" : "Mati") : "Tidak ada" }}
                                </span>
                            </div>
                        </div>
                    </article>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                        <article class="rounded-2xl border border-server-console-border/50 bg-server-console-paper/5 p-4">
                            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-server-console-paper/50">Failed jobs</p>
                            <p class="mt-2 font-mono text-2xl font-extrabold text-server-console-paper">
                                {{ snapshot.failed_jobs === null ? "N/A" : formatNumber(snapshot.failed_jobs) }}
                            </p>
                            <p class="mt-1 text-[11px] text-server-console-paper/45">Tabel failed_jobs Laravel</p>
                        </article>
                        <article class="rounded-2xl border border-server-console-border/50 bg-server-console-paper/5 p-4">
                            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-server-console-paper/50">Server uptime</p>
                            <p class="mt-2 text-sm font-bold leading-6 text-server-console-paper">
                                {{ formatUptime(snapshot.uptime_seconds) }}
                            </p>
                            <p class="mt-1 truncate text-[11px] text-server-console-paper/45">
                                {{ snapshot.hostname }} · {{ snapshot.platform }}
                            </p>
                        </article>
                    </div>
                </div>

                <div class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(280px,0.6fr)]">
                    <article class="overflow-hidden rounded-2xl border border-server-console-border/50 bg-server-console-paper/5">
                        <div class="flex items-center justify-between gap-3 border-b border-server-console-border/40 px-4 py-4 sm:px-5">
                            <div>
                                <h3 class="text-sm font-bold">Top 5 proses RAM</h3>
                                <p class="mt-1 text-[11px] text-server-console-paper/45">Snapshot dari daftar proses server.</p>
                            </div>
                            <i class="pi pi-list text-server-console-accent" />
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[440px] text-left text-xs">
                                <thead class="text-[10px] uppercase tracking-[0.12em] text-server-console-border">
                                    <tr>
                                        <th class="px-4 py-3 font-bold sm:px-5">Proses</th>
                                        <th class="px-4 py-3 font-bold">PID</th>
                                        <th class="px-4 py-3 text-right font-bold sm:px-5">RAM</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-server-console-border/25">
                                    <tr v-for="process in snapshot.processes" :key="`${process.pid}-${process.name}`">
                                        <td class="max-w-64 truncate px-4 py-3 font-mono font-semibold text-server-console-paper sm:px-5">
                                            {{ process.name }}
                                        </td>
                                        <td class="px-4 py-3 font-mono text-server-console-paper/50">{{ process.pid ?? "-" }}</td>
                                        <td class="px-4 py-3 text-right font-mono font-bold text-server-console-accent sm:px-5">
                                            {{ formatMegabytes(process.memory_mb) }} MB
                                        </td>
                                    </tr>
                                    <tr v-if="snapshot.processes.length === 0">
                                        <td colspan="3" class="px-5 py-8 text-center text-server-console-paper/45">
                                            Data proses tidak tersedia pada platform ini.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-server-console-border/50 bg-server-console-paper/5 p-4 sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold">SSL certificate</h3>
                                <p class="mt-1 break-all text-[11px] text-server-console-paper/45">{{ snapshot.ssl.domain || "Domain belum tersedia" }}</p>
                            </div>
                            <i class="pi pi-lock text-server-console-accent" />
                        </div>
                        <span
                            class="mt-4 inline-flex rounded-full border px-3 py-1.5 text-[10px] font-bold uppercase tracking-wide"
                            :class="sslClasses(snapshot.ssl.status)"
                        >
                            {{ snapshot.ssl.status }}
                        </span>
                        <p class="mt-4 font-mono text-2xl font-extrabold text-server-console-paper">
                            {{ snapshot.ssl.days_remaining === null ? "N/A" : `${formatNumber(snapshot.ssl.days_remaining)} hari` }}
                        </p>
                        <p class="mt-2 text-xs leading-5 text-server-console-paper/55">{{ snapshot.ssl.message }}</p>
                        <p class="mt-4 border-t border-server-console-border/30 pt-3 text-[10px] leading-5 text-server-console-paper/40">
                            Kedaluwarsa: {{ formatDateTime(snapshot.ssl.expires_at) }}
                        </p>
                    </article>
                </div>

                <div class="flex flex-col gap-2 border-t border-server-console-border/30 pt-4 text-[10px] text-server-console-paper/40 sm:flex-row sm:items-center sm:justify-between">
                    <span>Update terakhir: {{ formatDateTime(snapshot.generated_at) }}</span>
                    <span>Interval 2,5 detik · PHP {{ snapshot.php_version }} · {{ snapshot.hostname }}</span>
                </div>
            </template>
        </div>
    </section>
</template>
