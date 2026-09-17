<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    stockAdjustments: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
        }),
    },

    filters: {
        type: Object,
        default: () => ({}),
    },
});

/*
|--------------------------------------------------------------------------
| Filter State
|--------------------------------------------------------------------------
*/
const search = ref(
    props.filters?.search || ''
);

const sortField = ref(
    props.filters?.sort || 'created_at'
);

const sortDirection = ref(
    props.filters?.direction || 'desc'
);

/*
|--------------------------------------------------------------------------
| Search & Filter
|--------------------------------------------------------------------------
*/
const fetchData = () => {
    router.get(
        route('stock-adjustments.index'),
        {
            search: search.value.trim(),
            sort: sortField.value,
            direction: sortDirection.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

/*
|--------------------------------------------------------------------------
| Debounce Search
|--------------------------------------------------------------------------
*/
let searchTimeout = null;

watch(search, () => {
    clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
        fetchData();
    }, 300);
});

/*
|--------------------------------------------------------------------------
| Sorting
|--------------------------------------------------------------------------
*/
const sortBy = (field) => {
    if (sortField.value === field) {
        sortDirection.value =
            sortDirection.value === 'asc'
                ? 'desc'
                : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = 'asc';
    }

    fetchData();
};

/*
|--------------------------------------------------------------------------
| Formatting
|--------------------------------------------------------------------------
*/
const formatQty = (value) => {
    const number = Number(value);

    if (!Number.isFinite(number)) {
        return '0';
    }

    return number.toLocaleString('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    });
};

const formatDate = (dateString) => {
    if (!dateString) {
        return '-';
    }

    const date = new Date(dateString);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return date.toLocaleDateString(
        'id-ID',
        {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }
    );
};

const adjustmentClass = (value) => {
    const number = Number(value);

    if (number < 0) {
        return 'text-red-600';
    }

    if (number > 0) {
        return 'text-green-600';
    }

    return 'text-gray-600';
};

const adjustmentPrefix = (value) => {
    const number = Number(value);

    return number > 0 ? '+' : '';
};
</script>

<template>
    <Head title="Riwayat Stok Opname" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2
                    class="font-semibold text-xl text-gray-800 leading-tight"
                >
                    Riwayat Stok Opname
                </h2>

                <Link
                    v-if="
                        ['owner', 'manager'].includes(
                            $page.props.auth.user.role_name
                        )
                    "
                    :href="
                        route(
                            'stock-adjustments.create'
                        )
                    "
                    class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 font-bold shadow-sm transition"
                >
                    + Lakukan Opname
                </Link>
            </div>
        </template>

        <div
            class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8"
        >
            <div
                class="bg-white p-6 shadow-sm sm:rounded-lg"
            >

                <!-- Search -->
                <div
                    class="relative w-full md:w-1/3 mb-4"
                >
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari produk, barcode, alasan, atau user..."
                        class="w-full rounded-md border-gray-300 shadow-sm pr-10 focus:border-indigo-500 focus:ring-indigo-500"
                    />

                    <button
                        v-if="search"
                        type="button"
                        @click="search = ''"
                        class="absolute right-3 top-2 text-gray-400 hover:text-red-500 font-bold text-lg"
                        aria-label="Hapus pencarian"
                    >
                        &times;
                    </button>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table
                        class="w-full text-left border-collapse"
                    >
                        <thead>
                            <tr
                                class="bg-gray-100 border-b-2 text-gray-700"
                            >

                                <!-- Tanggal -->
                                <th
                                    class="p-3 cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="sortBy('created_at')"
                                >
                                    Tanggal & Jam

                                    <span
                                        v-if="
                                            sortField ===
                                            'created_at'
                                        "
                                        class="text-indigo-600"
                                    >
                                        {{
                                            sortDirection ===
                                            'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>
                                </th>

                                <!-- Produk -->
                                <th
                                    class="p-3 cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="sortBy('product')"
                                >
                                    Nama Produk

                                    <span
                                        v-if="
                                            sortField ===
                                            'product'
                                        "
                                        class="text-indigo-600"
                                    >
                                        {{
                                            sortDirection ===
                                            'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>
                                </th>

                                <!-- System -->
                                <th
                                    class="p-3 text-center cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="
                                        sortBy(
                                            'system_stock'
                                        )
                                    "
                                >
                                    Stok Sistem

                                    <span
                                        v-if="
                                            sortField ===
                                            'system_stock'
                                        "
                                        class="text-indigo-600"
                                    >
                                        {{
                                            sortDirection ===
                                            'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>
                                </th>

                                <!-- Physical -->
                                <th
                                    class="p-3 text-center cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="
                                        sortBy(
                                            'physical_stock'
                                        )
                                    "
                                >
                                    Stok Fisik

                                    <span
                                        v-if="
                                            sortField ===
                                            'physical_stock'
                                        "
                                        class="text-indigo-600"
                                    >
                                        {{
                                            sortDirection ===
                                            'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>
                                </th>

                                <!-- Adjustment -->
                                <th
                                    class="p-3 text-center cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="
                                        sortBy(
                                            'adjustment'
                                        )
                                    "
                                >
                                    Selisih

                                    <span
                                        v-if="
                                            sortField ===
                                            'adjustment'
                                        "
                                        class="text-indigo-600"
                                    >
                                        {{
                                            sortDirection ===
                                            'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>
                                </th>

                                <!-- Reason -->
                                <th
                                    class="p-3 cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="
                                        sortBy('reason')
                                    "
                                >
                                    Alasan Penyesuaian

                                    <span
                                        v-if="
                                            sortField ===
                                            'reason'
                                        "
                                        class="text-indigo-600"
                                    >
                                        {{
                                            sortDirection ===
                                            'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>
                                </th>

                                <!-- User -->
                                <th
                                    class="p-3 cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="
                                        sortBy('user')
                                    "
                                >
                                    Dilakukan Oleh

                                    <span
                                        v-if="
                                            sortField ===
                                            'user'
                                        "
                                        class="text-indigo-600"
                                    >
                                        {{
                                            sortDirection ===
                                            'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="item in stockAdjustments.data"
                                :key="item.id"
                                class="border-b hover:bg-gray-50 transition"
                            >

                                <!-- Date -->
                                <td
                                    class="p-3 text-sm text-gray-600"
                                >
                                    {{
                                        formatDate(
                                            item.created_at
                                        )
                                    }}
                                </td>

                                <!-- Product -->
                                <td
                                    class="p-3"
                                >
                                    <div
                                        class="font-semibold"
                                    >
                                        {{
                                            item.product?.name ||
                                            'Produk Dihapus'
                                        }}
                                    </div>

                                    <div
                                        v-if="
                                            item.product?.barcode
                                        "
                                        class="text-xs text-gray-500"
                                    >
                                        {{
                                            item.product.barcode
                                        }}
                                    </div>
                                </td>

                                <!-- System -->
                                <td
                                    class="p-3 text-center text-gray-500"
                                >
                                    {{
                                        formatQty(
                                            item.system_stock
                                        )
                                    }}

                                    <span
                                        v-if="
                                            item.product?.unit
                                        "
                                    >
                                        {{
                                            item.product.unit
                                        }}
                                    </span>
                                </td>

                                <!-- Physical -->
                                <td
                                    class="p-3 text-center font-bold"
                                >
                                    {{
                                        formatQty(
                                            item.physical_stock
                                        )
                                    }}

                                    <span
                                        v-if="
                                            item.product?.unit
                                        "
                                    >
                                        {{
                                            item.product.unit
                                        }}
                                    </span>
                                </td>

                                <!-- Adjustment -->
                                <td
                                    class="p-3 text-center font-bold"
                                    :class="
                                        adjustmentClass(
                                            item.adjustment
                                        )
                                    "
                                >
                                    {{
                                        adjustmentPrefix(
                                            item.adjustment
                                        )
                                    }}{{
                                        formatQty(
                                            item.adjustment
                                        )
                                    }}
                                </td>

                                <!-- Reason -->
                                <td
                                    class="p-3 italic text-gray-600"
                                >
                                    {{ item.reason }}
                                </td>

                                <!-- User -->
                                <td
                                    class="p-3 text-sm"
                                >
                                    {{
                                        item.user?.name ||
                                        '-'
                                    }}
                                </td>
                            </tr>

                            <!-- Empty -->
                            <tr
                                v-if="
                                    stockAdjustments.data
                                        .length === 0
                                "
                            >
                                <td
                                    colspan="7"
                                    class="p-6 text-center text-gray-500 font-medium"
                                >
                                    Belum ada riwayat
                                    penyesuaian stok.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <Pagination
                    :links="stockAdjustments.links"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>