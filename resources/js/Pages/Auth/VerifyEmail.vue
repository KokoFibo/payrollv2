<script setup>
import { computed } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import AuthCard from "@/Components/AuthCard.vue";

const props = defineProps({
    status: { type: String, default: null },
});

const form = useForm({});
const verificationLinkSent = computed(() => props.status === "verification-link-sent");

function submit() {
    form.post(route("verification.send"));
}
</script>

<template>
    <GuestLayout>
        <Head title="Verifikasi Email" />
        <AuthCard
            title="Verifikasi alamat email"
            description="Klik tautan pada email verifikasi. Jika tidak menerimanya, kirim ulang tautan dari halaman ini."
            icon="pi pi-envelope"
        >
            <Message v-if="verificationLinkSent" severity="success" :closable="false" class="mb-5">
                Tautan verifikasi baru sudah dikirim.
            </Message>
            <div class="flex flex-col gap-3">
                <Button label="Kirim ulang verifikasi" icon="pi pi-refresh" severity="danger" class="h-12" :loading="form.processing" @click="submit" />
                <Link :href="route('logout')" method="post" as="button">
                    <Button label="Keluar" icon="pi pi-sign-out" severity="secondary" outlined class="h-12 w-full" />
                </Link>
            </div>
        </AuthCard>
    </GuestLayout>
</template>
