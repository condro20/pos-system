<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue'; 

const props = defineProps({ stockAdjustments: Object, filters: Object });

// Menampung state filter dari URL
const search = ref(props.filters?.search || '');
const sortField = ref(props.filters?.sort || 'created_at');
const sortDirection = ref(props.filters?.direction || 'desc');

// Fungsi ambil data ke Backend
const fetchData = () => {
    router.get(route('stock-adjustments.index'), {
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

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <Head title="Riwayat Stok Opname" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Riwayat Stok Opname</h2>
                <!-- Hanya Owner / Manager yang boleh menambah stok opname -->
                <Link v-if="['owner', 'manager'].includes($page.props.auth.user.role_name)" :href="route('stock-adjustments.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 font-bold shadow-sm transition">
                    + Lakukan Opname
                </Link>
            </div>
        </template>

        <div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                
                <!-- Search Bar -->
                <div class="relative w-full md:w-1/3 mb-4">
                    <input v-model="search" type="text" placeholder="Cari nama produk, alasan, atau user..." class="w-full rounded-md border-gray-300 shadow-sm pr-10 focus:border-indigo-500 focus:ring-indigo-500" />
                    <span v-if="search" @click="search = ''" class="absolute right-3 top-2.5 text-gray-400 cursor-pointer font-bold text-lg hover:text-red-500">&times;</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100 border-b-2 text-gray-700">
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('created_at')">
                                    Tanggal & Jam <span v-if="sortField === 'created_at'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('product')">
                                    Nama Produk <span v-if="sortField === 'product'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 text-center cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('system_stock')">
                                    Stok Sistem <span v-if="sortField === 'system_stock'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 text-center cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('actual_stock')">
                                    Stok Fisik <span v-if="sortField === 'actual_stock'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 text-center cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('adjustment')">
                                    Selisih <span v-if="sortField === 'adjustment'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('reason')">
                                    Alasan Penyesuaian <span v-if="sortField === 'reason'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('user')">
                                    Dilakukan Oleh <span v-if="sortField === 'user'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in stockAdjustments.data" :key="item.id" class="border-b hover:bg-gray-50 transition">
                                <td class="p-3 text-sm text-gray-600">{{ formatDate(item.created_at) }}</td>
                                <td class="p-3 font-semibold">{{ item.product?.name || 'Produk Dihapus' }}</td>
                                <td class="p-3 text-center text-gray-500">{{ parseFloat(item.system_stock) }}</td>
                                <td class="p-3 text-center font-bold">{{ parseFloat(item.physical_stock) }}</td>
                                <td class="p-3 text-center font-bold" :class="item.adjustment < 0 ? 'text-red-600' : 'text-green-600'">
                                    {{ item.adjustment > 0 ? '+' : '' }}{{ parseFloat(item.adjustment) }}
                                </td>
                                <td class="p-3 italic text-gray-600">{{ item.reason }}</td>
                                <td class="p-3 text-sm">{{ item.user?.name || '-' }}</td>
                            </tr>
                            <tr v-if="stockAdjustments.data.length === 0">
                                <td colspan="7" class="p-6 text-center text-gray-500 font-medium">Belum ada riwayat penyesuaian stok.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Komponen Pagination -->
                <Pagination :links="stockAdjustments.links" />

            </div>
        </div>
    </AuthenticatedLayout>
</template>