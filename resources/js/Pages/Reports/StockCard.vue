<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue'; 

const props = defineProps({ 
    products: Array, 
    movements: Object, 
    selected_product: Object, 
    filters: Object 
});

// Menampung state filter dari URL
const productId = ref(props.filters?.product_id || '');
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');
const search = ref(props.filters?.search || '');
const sortField = ref(props.filters?.sort || 'date');
const sortDirection = ref(props.filters?.direction || 'desc');

// Fungsi utama ambil data ke Backend
const fetchData = () => {
    // route().current() digunakan agar otomatis memakai nama route halaman ini
    router.get(route('reports.stock_card'), {
        product_id: productId.value,
        start_date: startDate.value,
        end_date: endDate.value,
        search: search.value,
        sort: sortField.value,
        direction: sortDirection.value
    }, { preserveState: true, preserveScroll: true, replace: true });
};

// Jeda pencarian otomatis saat mengetik (Debounce)
let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => { fetchData(); }, 300);
});

// Trigger fetch langsung jika combobox atau kalender diubah
const applyFilter = () => fetchData();

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
</script>

<template>
    <Head title="Kartu Stok (Pergerakan Barang)" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Laporan Kartu Stok</h2>
        </template>

        <div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                
                <!-- FILTER BAR (Produk, Tanggal, dan Pencarian) -->
                <div class="flex flex-col md:flex-row justify-between gap-4 mb-6 bg-gray-50 p-4 rounded-lg border">
                    <div class="flex flex-col md:flex-row gap-4 w-full md:w-auto items-center">
                        <!-- Dropdown Produk -->
                        <select v-model="productId" @change="applyFilter" class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 w-full md:w-64 font-bold">
                            <option value="">-- Pilih Produk Dulu --</option>
                            <option v-for="prod in products" :key="prod.id" :value="prod.id">
                                {{ prod.barcode ? '['+prod.barcode+'] ' : '' }}{{ prod.name }}
                            </option>
                        </select>
                        
                        <!-- Rentang Tanggal -->
                        <div class="flex items-center gap-2 w-full md:w-auto">
                            <input type="date" v-model="startDate" @change="applyFilter" class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <span class="text-gray-500">s/d</span>
                            <input type="date" v-model="endDate" @change="applyFilter" class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        </div>
                    </div>

                    <!-- Kotak Pencarian -->
                    <div class="relative w-full md:w-1/4">
                        <input v-model="search" type="text" placeholder="Cari ref / tipe..." :disabled="!productId" class="w-full rounded-md border-gray-300 shadow-sm pr-10 focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-400" />
                        <span v-if="search" @click="search = ''" class="absolute right-3 top-2.5 text-gray-400 cursor-pointer font-bold text-lg hover:text-red-500">&times;</span>
                    </div>
                </div>

                <!-- INFO STOK SAAT INI -->
                <div v-if="selected_product" class="mb-4 text-right">
                    Stok sistem saat ini: 
                    <strong class="text-indigo-600 text-2xl ml-2 bg-indigo-50 px-3 py-1 rounded border border-indigo-200">
                        {{ parseFloat(selected_product.stock) }} {{ selected_product.unit }}
                    </strong>
                </div>

                <!-- TABEL PERGERAKAN -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100 border-b-2 text-gray-700">
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('date')">
                                    Waktu <span v-if="sortField === 'date'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('type')">
                                    Tipe Transaksi <span v-if="sortField === 'type'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('reference')">
                                    No. Referensi <span v-if="sortField === 'reference'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('description')">
                                    Keterangan <span v-if="sortField === 'description'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 text-center cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('in')">
                                    Masuk (In) <span v-if="sortField === 'in'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 text-center cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('out')">
                                    Keluar (Out) <span v-if="sortField === 'out'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody v-if="productId">
                            <tr v-for="(item, index) in movements.data" :key="index" class="border-b hover:bg-gray-50 transition">
                                <td class="p-3 text-sm text-gray-600">{{ item.date_formatted }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-1 text-xs font-bold rounded" 
                                          :class="{'bg-green-100 text-green-700': item.type.includes('Pembelian'), 'bg-blue-100 text-blue-700': item.type.includes('Penjualan'), 'bg-gray-200 text-gray-800': item.type.includes('Opname')}">
                                        {{ item.type }}
                                    </span>
                                </td>
                                <td class="p-3 font-semibold text-gray-700">{{ item.reference }}</td>
                                <td class="p-3 italic text-gray-600 text-sm">{{ item.description }}</td>
                                <td class="p-3 text-center font-bold text-green-600">
                                    {{ item.in > 0 ? `+${parseFloat(item.in)}` : '-' }}
                                </td>
                                <td class="p-3 text-center font-bold text-red-600">
                                    {{ item.out > 0 ? `-${parseFloat(item.out)}` : '-' }}
                                </td>
                            </tr>
                            <tr v-if="movements.data.length === 0">
                                <td colspan="6" class="p-6 text-center text-gray-500 font-medium">Tidak ada pergerakan stok untuk produk ini pada rentang waktu yang dipilih.</td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="6" class="p-10 text-center text-gray-400 italic">Silakan pilih produk terlebih dahulu melalui dropdown di atas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Komponen Pagination -->
                <Pagination v-if="productId && movements.links" :links="movements.links" />

            </div>
        </div>
    </AuthenticatedLayout>
</template>