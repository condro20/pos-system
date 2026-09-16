<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue'; 

const props = defineProps({ purchases: Object, filters: Object });

// Menampung state filter dari URL
const search = ref(props.filters?.search || '');
const sortField = ref(props.filters?.sort || 'created_at');
const sortDirection = ref(props.filters?.direction || 'desc');

// Fungsi ambil data ke Backend
const fetchData = () => {
    router.get(route('purchases.index'), {
        search: search.value,
        sort: sortField.value,
        direction: sortDirection.value
    }, { preserveState: true, preserveScroll: true, replace: true });
};

// Jeda pencarian otomatis (Debounce)
let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => { fetchData(); }, 300);
});

// Fungsi klik Header Tabel
const sortBy = (field) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = 'asc';
    }
    fetchData();
};

// Logika Modal Detail Pembelian
const showModal = ref(false);
const selectedData = ref(null);
const openModal = (data) => {
    selectedData.value = data;
    showModal.value = true;
};

// Helper format uang & tanggal
const formatRp = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <Head title="Riwayat Pembelian" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Riwayat Pembelian (Restock)</h2>
                <!-- Tombol ini mungkin mengarah ke halaman keranjang restock Anda -->
                <Link :href="route('purchases.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 font-bold shadow-sm transition">
                    + Input Pembelian
                </Link>
            </div>
        </template>

        <div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                
                <!-- Search Bar -->
                <div class="relative w-full md:w-1/3 mb-4">
                    <input v-model="search" type="text" placeholder="Cari No. PO atau Nama Supplier..." class="w-full rounded-md border-gray-300 shadow-sm pr-10 focus:border-indigo-500 focus:ring-indigo-500" />
                    <span v-if="search" @click="search = ''" class="absolute right-3 top-2.5 text-gray-400 cursor-pointer font-bold text-lg hover:text-red-500">&times;</span>
                </div>

                <!-- Tabel Data -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100 border-b-2 text-gray-700">
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('created_at')">
                                    Tanggal & Jam <span v-if="sortField === 'created_at'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('invoice_no')">
                                    No. PO <span v-if="sortField === 'invoice_no'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('supplier')">
                                    Supplier / Agen <span v-if="sortField === 'supplier'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 text-right cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('grand_total')">
                                    Total Tagihan <span v-if="sortField === 'grand_total'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in purchases.data" :key="item.id" class="border-b hover:bg-gray-50 transition">
                                <td class="p-3 text-sm text-gray-600">{{ formatDate(item.created_at) }}</td>
                                <td class="p-3 font-semibold">{{ item.invoice_no }}</td>
                                <td class="p-3">{{ item.supplier?.name || '-' }}</td>
                                <td class="p-3 text-right font-bold text-red-600">{{ formatRp(item.grand_total) }}</td>
                                <td class="p-3 text-center space-x-2">
                                    <button @click="openModal(item)" class="bg-blue-100 text-blue-700 px-3 py-1 rounded text-sm font-bold hover:bg-blue-200 transition">
                                        Detail
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="purchases.data.length === 0">
                                <td colspan="5" class="p-6 text-center text-gray-500 font-medium">Belum ada riwayat pembelian barang.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Komponen Pagination -->
                <Pagination :links="purchases.links" />

            </div>
        </div>
    </AuthenticatedLayout>

    <!-- MODAL DETAIL PEMBELIAN -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 px-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl overflow-hidden">
            <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50">
                <h3 class="font-bold text-lg text-gray-800">Detail PO: {{ selectedData.invoice_no }}</h3>
                <button @click="showModal = false" class="text-gray-500 hover:text-red-500 font-bold text-xl">&times;</button>
            </div>
            
            <div class="p-6">
                <p class="mb-4 text-sm text-gray-600">Supplier/Agen: <strong class="text-gray-800">{{ selectedData.supplier?.name || '-' }}</strong></p>
                <div class="max-h-[50vh] overflow-y-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-100 border-b-2">
                            <tr>
                                <th class="p-2">Produk</th>
                                <th class="p-2 text-right">Harga Beli</th>
                                <th class="p-2 text-right">Kuantitas Masuk</th>
                                <th class="p-2 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-if="selectedData?.purchase_details && selectedData.purchase_details.length > 0">
                                <tr v-for="detail in selectedData.purchase_details" :key="detail.id" class="border-b">
                                    <td class="p-2">{{ detail.product?.name || 'Produk Dihapus' }}</td>
                                    <td class="p-2 text-right">{{ formatRp(detail.price || detail.purchase_price) }}</td>
                                    <td class="p-2 text-right font-bold text-green-600">
                                        +{{ parseFloat(detail.quantity) }} {{ detail.product?.unit || '' }}
                                    </td>
                                    <td class="p-2 text-right font-bold">{{ formatRp(detail.subtotal) }}</td>
                                </tr>
                            </template>
                            <template v-else>
                                <tr>
                                    <td colspan="4" class="p-4 text-center text-red-500 font-bold italic">
                                        Data barang masuk tidak ditemukan!
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4 flex justify-end">
                    <div class="text-right">
                        <p class="text-sm text-gray-500">Total Pembelian</p>
                        <p class="text-2xl font-bold text-red-600">{{ formatRp(selectedData.grand_total) }}</p>
                    </div>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t bg-gray-50 text-right">
                <button @click="showModal = false" class="bg-gray-800 hover:bg-gray-900 text-white px-6 py-2 rounded shadow font-bold transition">Tutup</button>
            </div>
        </div>
    </div>
</template>