<script setup>
import { ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue'; // <-- Import komponen modal kita

const props = defineProps({ setting: Object });

// === STATE MODAL KUSTOM ===
const showModal = ref(false);
const modalTitle = ref('');
const modalMessage = ref('');
let resolveModal = null;

// Fungsi pemanggil modal dinamis
const customAlert = (title, message) => {
    modalTitle.value = title;
    modalMessage.value = message;
    showModal.value = true;
    
    return new Promise((resolve) => {
        resolveModal = resolve;
    });
};

const handleModalConfirm = () => {
    showModal.value = false;
    if (resolveModal) resolveModal(true);
};

const handleModalClose = () => {
    showModal.value = false;
    if (resolveModal) resolveModal(false);
};

// === FORM STATE ===
const form = useForm({
    store_name: props.setting.store_name,
    store_address: props.setting.store_address,
    store_phone: props.setting.store_phone,
    receipt_note: props.setting.receipt_note,
});

const submit = () => {
    form.post(route('settings.update'), {
        preserveScroll: true,
        onSuccess: async () => {
            // Ganti alert bawaan dengan modal kustom
            await customAlert('Berhasil', 'Pengaturan profil toko berhasil disimpan!');
        }
    });
};
</script>

<template>
    <Head title="Pengaturan Toko" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800">Pengaturan Profil Toko</h2>
        </template>

        <div class="py-12 max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form @submit.prevent="submit" class="space-y-6">
                    
                    <div class="border-b pb-4 mb-4">
                        <h3 class="text-lg font-bold text-gray-800">Informasi Dasar</h3>
                        <p class="text-sm text-gray-500">Informasi ini akan ditampilkan di nota/struk kasir pelanggan.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Nama Toko</label>
                        <input v-model="form.store_name" type="text" class="mt-1 w-full rounded border-gray-300" required />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium">Nomor Telepon / WhatsApp</label>
                        <input v-model="form.store_phone" type="text" class="mt-1 w-full rounded border-gray-300" required />
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Alamat Lengkap</label>
                        <textarea v-model="form.store_address" rows="3" class="mt-1 w-full rounded border-gray-300" required></textarea>
                    </div>

                    <div class="pt-4 border-t mt-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-2">Pengaturan Struk Kasir</h3>
                        <label class="block text-sm font-medium">Pesan Penutup (Footer)</label>
                        <input v-model="form.receipt_note" type="text" placeholder="Cth: Terima kasih, barang yang dibeli tidak dapat ditukar." class="mt-1 w-full rounded border-gray-300" />
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded font-bold shadow hover:bg-indigo-700" :disabled="form.processing">
                            Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL KUSTOM DILETAKKAN DI SINI -->
        <ConfirmModal 
            :show="showModal" 
            :title="modalTitle"
            :message="modalMessage" 
            :isPrompt="false"
            @confirm="handleModalConfirm" 
            @close="handleModalClose" 
        />
        
    </AuthenticatedLayout>
</template>