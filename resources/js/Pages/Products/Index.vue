<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue'; 
import ConfirmModal from '@/Components/ConfirmModal.vue'; // <-- Import komponen modal kita

const props = defineProps({ products: Object, filters: Object });

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
const search = ref(props.filters.search || '');
const sortField = ref(props.filters.sort || 'id');
const sortDirection = ref(props.filters.direction || 'desc');

// Fungsi utama untuk memanggil data baru ke Backend
const fetchData = () => {
    router.get(route('products.index'), {
        search: search.value,
        sort: sortField.value,
        direction: sortDirection.value
    }, { 
        preserveState: true, 
        preserveScroll: true, 
        replace: true 
    });
};

// Fitur pencarian otomatis dengan jeda waktu (Debounce 300ms)
let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchData();
    }, 300);
});

// Fungsi untuk Mengurutkan Kolom saat Header di-klik
const sortBy = (field) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = 'asc';
    }
    fetchData();
};

const formatRp = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);

// === FUNGSI HAPUS (Diubah menjadi Async) ===
const deleteProduct = async (id) => {
    const confirmed = await customConfirm(
        'Konfirmasi Hapus',
        'Yakin ingin menghapus produk ini? Histori penjualan tidak akan terpengaruh.'
    );

    if (confirmed) {
        router.delete(route('products.destroy', id));
    }
};
</script>

<template>
    <Head title="Manajemen Produk" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Master Produk</h2>
                <Link v-if="['owner', 'manager'].includes($page.props.auth.user.role_name)" :href="route('products.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 font-bold">
                    + Tambah Produk
                </Link>
            </div>
        </template>

        <div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <!-- Search Bar -->
                <div class="relative w-full md:w-1/3 mb-4">
                    <input v-model="search" type="text" placeholder="Cari nama atau barcode..." class="w-full rounded-md border-gray-300 shadow-sm pr-10 focus:border-indigo-500 focus:ring-indigo-500" />
                    <span v-if="search" @click="search = ''" class="absolute right-3 top-2.5 text-gray-400 cursor-pointer font-bold text-lg hover:text-red-500">&times;</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100 border-b-2 text-gray-700">
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('barcode')">
                                    Barcode <span v-if="sortField === 'barcode'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('name')">
                                    Nama Produk <span v-if="sortField === 'name'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('category')">
                                    Kategori <span v-if="sortField === 'category'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('stock')">
                                    Stok <span v-if="sortField === 'stock'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 text-right cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('purchase_price')">
                                    Harga Beli <span v-if="sortField === 'purchase_price'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 text-right cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('selling_price')">
                                    Harga Jual <span v-if="sortField === 'selling_price'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th v-if="['owner', 'manager'].includes($page.props.auth.user.role_name)" class="p-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="product in products.data" :key="product.id" class="border-b hover:bg-gray-50 transition">
                                <td class="p-3">{{ product.barcode || '-' }}</td>
                                <td class="p-3 font-semibold">{{ product.name }}</td>
                                <td class="p-3">{{ product.category?.name }}</td>
                                <td class="p-3 font-bold" :class="product.stock <= 10 ? 'text-red-600' : 'text-green-600'">
                                    {{ parseFloat(product.stock) }} {{ product.unit }}
                                </td>
                                <td class="p-3 text-right">{{ formatRp(product.purchase_price) }}</td>
                                <td class="p-3 text-right text-indigo-600 font-bold">{{ formatRp(product.selling_price) }}</td>
                                <td v-if="['owner', 'manager'].includes($page.props.auth.user.role_name)" class="p-3 text-center space-x-2">
                                    <Link :href="route('products.edit', product.id)" class="text-blue-600 hover:underline">Edit</Link>
                                    <button @click="deleteProduct(product.id)" class="text-red-600 hover:underline">Hapus</button>
                                </td>
                            </tr>
                            <tr v-if="products.data.length === 0">
                                <td colspan="7" class="p-6 text-center text-gray-500 font-medium">Produk tidak ditemukan.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Komponen Pagination -->
                <Pagination :links="products.links" />

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