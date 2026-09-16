<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue'; 
import ConfirmModal from '@/Components/ConfirmModal.vue'; // <-- Import komponen modal

const props = defineProps({ customers: Object, filters: Object });

// === STATE MODAL KUSTOM ===
const showModal = ref(false);
const modalTitle = ref('');
const modalMessage = ref('');
let resolveModal = null;

// Fungsi pemanggil modal dinamis berbasis Promise (Khusus konfirmasi)
const customConfirm = (title, message) => {
    modalTitle.value = title;
    modalMessage.value = message;
    showModal.value = true;
    
    return new Promise((resolve) => {
        resolveModal = resolve;
    });
};

const handleModalConfirm = () => {
    showModal.value = false;
    if (resolveModal) resolveModal(true); // Kirim true jika OK
};

const handleModalClose = () => {
    showModal.value = false;
    if (resolveModal) resolveModal(false); // Kirim false jika Batal
};

// === STATE UTAMA ===
// Menampung state filter dari URL
const search = ref(props.filters?.search || '');
const sortField = ref(props.filters?.sort || 'id');
const sortDirection = ref(props.filters?.direction || 'desc');

// Fungsi ambil data (Search & Sort) ke Backend
const fetchData = () => {
    router.get(route('customers.index'), {
        search: search.value,
        sort: sortField.value,
        direction: sortDirection.value
    }, { preserveState: true, preserveScroll: true, replace: true });
};

// Jeda pencarian otomatis saat mengetik (Debounce 300ms)
let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => { fetchData(); }, 300);
});

// Fungsi klik Header Tabel untuk Sorting
const sortBy = (field) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = 'asc';
    }
    fetchData();
};

// === FUNGSI HAPUS (Diubah menjadi Async) ===
const deleteCustomer = async (id) => {
    // Panggil modal kustom pengganti window.confirm
    const confirmed = await customConfirm(
        'Konfirmasi Hapus',
        'Yakin ingin menghapus pelanggan ini?'
    );

    if (confirmed) {
        router.delete(route('customers.destroy', id));
    }
};
</script>

<template>
    <Head title="Manajemen Pelanggan" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Master Pelanggan</h2>
                <Link :href="route('customers.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 font-bold shadow-sm transition">
                    + Tambah Pelanggan
                </Link>
            </div>
        </template>

        <div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                
                <!-- Search Bar -->
                <div class="relative w-full md:w-1/3 mb-4">
                    <input v-model="search" type="text" placeholder="Cari nama, no HP, atau alamat..." class="w-full rounded-md border-gray-300 shadow-sm pr-10 focus:border-indigo-500 focus:ring-indigo-500" />
                    <span v-if="search" @click="search = ''" class="absolute right-3 top-2.5 text-gray-400 cursor-pointer font-bold text-lg hover:text-red-500">&times;</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100 border-b-2 text-gray-700">
                                <th class="p-3 w-16 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('id')">
                                    ID <span v-if="sortField === 'id'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('name')">
                                    Nama Pelanggan <span v-if="sortField === 'name'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('phone')">
                                    No. Handphone <span v-if="sortField === 'phone'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('address')">
                                    Alamat <span v-if="sortField === 'address'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="customer in customers.data" :key="customer.id" class="border-b hover:bg-gray-50 transition">
                                <td class="p-3 font-bold text-gray-500">#{{ customer.id }}</td>
                                <td class="p-3 font-semibold">{{ customer.name }}</td>
                                <td class="p-3">{{ customer.phone || '-' }}</td>
                                <td class="p-3 text-gray-600">{{ customer.address || '-' }}</td>
                                <td class="p-3 text-center space-x-2">
                                    <Link :href="route('customers.edit', customer.id)" class="text-blue-600 font-medium hover:underline">Edit</Link>
                                    <button @click="deleteCustomer(customer.id)" class="text-red-600 font-medium hover:underline">Hapus</button>
                                </td>
                            </tr>
                            <tr v-if="customers.data.length === 0">
                                <td colspan="5" class="p-6 text-center text-gray-500 font-medium">Data pelanggan tidak ditemukan.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Komponen Pagination -->
                <Pagination :links="customers.links" />

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