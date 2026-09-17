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


// =========================================================
// FILTER
// =========================================================

const search = ref(
    props.filters?.search || ''
);

const sortField = ref(
    props.filters?.sort || 'created_at'
);

const sortDirection = ref(
    props.filters?.direction || 'desc'
);


// =========================================================
// MODAL
// =========================================================

const showModal = ref(false);

const selectedData = ref(null);


// =========================================================
// FETCH DATA
// =========================================================

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


// =========================================================
// SEARCH
// =========================================================

let searchTimeout = null;

watch(search, () => {

    clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
        fetchData();
    }, 300);

});


// =========================================================
// SORT
// =========================================================

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


// =========================================================
// OPEN DETAIL
// =========================================================

const openModal = (adjustment) => {

    selectedData.value = adjustment;

    showModal.value = true;

};


// =========================================================
// CLOSE DETAIL
// =========================================================

const closeModal = () => {

    showModal.value = false;

    selectedData.value = null;

};


// =========================================================
// FORMAT QTY
// =========================================================

const formatQty = (value) => {

    const number = Number(value);

    if (!Number.isFinite(number)) {
        return '0';
    }

    return number.toLocaleString(
        'id-ID',
        {
            minimumFractionDigits: 0,
            maximumFractionDigits: 3,
        }
    );

};


// =========================================================
// FORMAT DATE
// =========================================================

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


// =========================================================
// ADJUSTMENT COLOR
// =========================================================

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


// =========================================================
// ADJUSTMENT PREFIX
// =========================================================

const adjustmentPrefix = (value) => {

    const number = Number(value);

    return number > 0 ? '+' : '';

};

</script>


<template>

    <Head title="Riwayat Stok Opname" />

    <AuthenticatedLayout>

        <!-- ================================================= -->
        <!-- HEADER -->
        <!-- ================================================= -->

        <template #header>

            <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
            >

                <div>

                    <h2
                        class="font-semibold text-xl text-gray-800 leading-tight"
                    >
                        Riwayat Stok Opname
                    </h2>

                    <p class="text-sm text-gray-500 mt-1">
                        Riwayat penyesuaian stok
                    </p>

                </div>


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


        <!-- ================================================= -->
        <!-- CONTENT -->
        <!-- ================================================= -->

        <div
            class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8"
        >

            <div
                class="bg-white p-6 shadow-sm sm:rounded-lg"
            >

                <!-- SEARCH -->

                <div
                    class="relative w-full md:w-1/3 mb-6"
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
                        class="absolute right-3 top-2.5 text-gray-400 hover:text-red-500 font-bold text-lg"
                    >
                        &times;
                    </button>

                </div>


                <!-- TABLE -->

                <div class="overflow-x-auto">

                    <table
                        class="w-full text-left border-collapse"
                    >

                        <thead>

                            <tr
                                class="bg-gray-100 border-b-2 text-gray-700"
                            >

                                <th
                                    class="p-3 cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="sortBy('created_at')"
                                >

                                    Tanggal & Jam

                                    <span
                                        v-if="sortField === 'created_at'"
                                        class="text-indigo-600 ml-1"
                                    >
                                        {{
                                            sortDirection === 'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>

                                </th>


                                <th class="p-3">
                                    No. Adjustment
                                </th>


                                <th
                                    class="p-3 cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="sortBy('product')"
                                >

                                    Nama Produk

                                    <span
                                        v-if="sortField === 'product'"
                                        class="text-indigo-600 ml-1"
                                    >
                                        {{
                                            sortDirection === 'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>

                                </th>


                                <th
                                    class="p-3 text-center cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="sortBy('system_stock')"
                                >

                                    Stok Sistem

                                    <span
                                        v-if="sortField === 'system_stock'"
                                        class="text-indigo-600 ml-1"
                                    >
                                        {{
                                            sortDirection === 'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>

                                </th>


                                <th
                                    class="p-3 text-center cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="sortBy('physical_stock')"
                                >

                                    Stok Fisik

                                    <span
                                        v-if="sortField === 'physical_stock'"
                                        class="text-indigo-600 ml-1"
                                    >
                                        {{
                                            sortDirection === 'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>

                                </th>


                                <th
                                    class="p-3 text-center cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="sortBy('adjustment')"
                                >

                                    Selisih

                                    <span
                                        v-if="sortField === 'adjustment'"
                                        class="text-indigo-600 ml-1"
                                    >
                                        {{
                                            sortDirection === 'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>

                                </th>


                                <th
                                    class="p-3"
                                >
                                    Alasan
                                </th>


                                <th
                                    class="p-3"
                                >
                                    Dilakukan Oleh
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <tr
                                v-for="item in stockAdjustments.data"
                                :key="item.id"
                                class="border-b hover:bg-gray-50 transition"
                            >

                                <!-- DATE -->

                                <td
                                    class="p-3 text-sm text-gray-600"
                                >
                                    {{
                                        formatDate(
                                            item.created_at
                                        )
                                    }}
                                </td>


                                <!-- CLICKABLE ADJUSTMENT -->

                                <td class="p-3">

                                    <button
                                        type="button"
                                        @click="openModal(item)"
                                        class="font-semibold text-indigo-700 hover:text-indigo-900 hover:underline transition"
                                        :title="'Lihat detail ADJ-' + item.id"
                                    >
                                        ADJ-{{ item.id }}
                                    </button>

                                </td>


                                <!-- PRODUCT -->

                                <td class="p-3">

                                    <div
                                        class="font-semibold"
                                    >
                                        {{
                                            item.product?.name
                                            || 'Produk Dihapus'
                                        }}
                                    </div>

                                    <div
                                        v-if="item.product?.barcode"
                                        class="text-xs text-gray-500"
                                    >
                                        {{
                                            item.product.barcode
                                        }}
                                    </div>

                                </td>


                                <!-- SYSTEM -->

                                <td
                                    class="p-3 text-center text-gray-500"
                                >

                                    {{
                                        formatQty(
                                            item.system_stock
                                        )
                                    }}

                                    <span
                                        v-if="item.product?.unit"
                                    >
                                        {{ item.product.unit }}
                                    </span>

                                </td>


                                <!-- PHYSICAL -->

                                <td
                                    class="p-3 text-center font-bold"
                                >

                                    {{
                                        formatQty(
                                            item.physical_stock
                                        )
                                    }}

                                    <span
                                        v-if="item.product?.unit"
                                    >
                                        {{ item.product.unit }}
                                    </span>

                                </td>


                                <!-- ADJUSTMENT -->

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


                                <!-- REASON -->

                                <td
                                    class="p-3 italic text-gray-600"
                                >
                                    {{ item.reason }}
                                </td>


                                <!-- USER -->

                                <td
                                    class="p-3 text-sm"
                                >
                                    {{
                                        item.user?.name
                                        || '-'
                                    }}
                                </td>

                            </tr>


                            <tr
                                v-if="
                                    stockAdjustments.data.length === 0
                                "
                            >

                                <td
                                    colspan="8"
                                    class="p-10 text-center text-gray-500 font-medium"
                                >
                                    Belum ada riwayat penyesuaian stok.
                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>


                <!-- PAGINATION -->

                <Pagination
                    :links="stockAdjustments.links"
                />

            </div>

        </div>

    </AuthenticatedLayout>


    <!-- ================================================= -->
    <!-- MODAL DETAIL STOCK ADJUSTMENT -->
    <!-- ================================================= -->

    <div
        v-if="showModal && selectedData"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
        @click.self="closeModal"
    >

        <div
            class="bg-white rounded-lg shadow-xl w-full max-w-3xl overflow-hidden"
        >

            <!-- HEADER -->

            <div
                class="px-6 py-4 border-b flex justify-between items-center bg-gray-50"
            >

                <div>

                    <h3
                        class="font-bold text-lg text-gray-800"
                    >
                        Detail Stock Adjustment
                    </h3>

                    <p
                        class="text-sm text-indigo-600 font-semibold mt-1"
                    >
                        ADJ-{{ selectedData.id }}
                    </p>

                </div>


                <button
                    type="button"
                    @click="closeModal"
                    class="text-gray-500 hover:text-red-500 font-bold text-2xl"
                >
                    &times;
                </button>

            </div>


            <!-- BODY -->

            <div
                class="p-6 max-h-[70vh] overflow-y-auto"
            >

                <!-- PRODUCT + USER -->

                <div
                    class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6"
                >

                    <div
                        class="border rounded-lg p-4 bg-gray-50"
                    >

                        <p
                            class="text-xs text-gray-500 uppercase"
                        >
                            Produk
                        </p>

                        <p
                            class="font-bold text-gray-800 mt-1"
                        >
                            {{
                                selectedData.product?.name
                                || 'Produk Dihapus'
                            }}
                        </p>

                        <p
                            class="text-sm text-gray-500 mt-1"
                        >
                            {{
                                selectedData.product?.barcode
                                || '-'
                            }}
                        </p>

                    </div>


                    <div
                        class="border rounded-lg p-4 bg-gray-50"
                    >

                        <p
                            class="text-xs text-gray-500 uppercase"
                        >
                            Dilakukan Oleh
                        </p>

                        <p
                            class="font-bold text-gray-800 mt-1"
                        >
                            {{
                                selectedData.user?.name
                                || '-'
                            }}
                        </p>

                        <p
                            class="text-sm text-gray-500 mt-1"
                        >
                            {{
                                formatDate(
                                    selectedData.created_at
                                )
                            }}
                        </p>

                    </div>

                </div>


                <!-- STOCK COMPARISON -->

                <div
                    class="grid grid-cols-1 md:grid-cols-3 gap-4"
                >

                    <div
                        class="border rounded-lg p-5"
                    >

                        <p
                            class="text-xs text-gray-500 uppercase"
                        >
                            Stok Sistem
                        </p>

                        <p
                            class="text-2xl font-bold text-gray-800 mt-2"
                        >

                            {{
                                formatQty(
                                    selectedData.system_stock
                                )
                            }}

                            <span
                                class="text-sm font-normal text-gray-500"
                            >
                                {{
                                    selectedData.product?.unit
                                    || ''
                                }}
                            </span>

                        </p>

                    </div>


                    <div
                        class="border rounded-lg p-5"
                    >

                        <p
                            class="text-xs text-gray-500 uppercase"
                        >
                            Stok Fisik
                        </p>

                        <p
                            class="text-2xl font-bold text-gray-800 mt-2"
                        >

                            {{
                                formatQty(
                                    selectedData.physical_stock
                                )
                            }}

                            <span
                                class="text-sm font-normal text-gray-500"
                            >
                                {{
                                    selectedData.product?.unit
                                    || ''
                                }}
                            </span>

                        </p>

                    </div>


                    <div
                        class="border rounded-lg p-5"
                    >

                        <p
                            class="text-xs text-gray-500 uppercase"
                        >
                            Selisih
                        </p>

                        <p
                            class="text-2xl font-bold mt-2"
                            :class="
                                adjustmentClass(
                                    selectedData.adjustment
                                )
                            "
                        >

                            {{
                                adjustmentPrefix(
                                    selectedData.adjustment
                                )
                            }}{{
                                formatQty(
                                    selectedData.adjustment
                                )
                            }}

                            <span
                                class="text-sm font-normal"
                            >
                                {{
                                    selectedData.product?.unit
                                    || ''
                                }}
                            </span>

                        </p>

                    </div>

                </div>


                <!-- REASON -->

                <div
                    class="border rounded-lg p-4 mt-6"
                >

                    <p
                        class="text-xs text-gray-500 uppercase"
                    >
                        Alasan Penyesuaian
                    </p>

                    <p
                        class="font-medium text-gray-800 mt-2"
                    >
                        {{
                            selectedData.reason
                            || '-'
                        }}
                    </p>

                </div>

            </div>


            <!-- FOOTER -->

            <div
                class="px-6 py-4 border-t bg-gray-50 flex justify-end"
            >

                <button
                    type="button"
                    @click="closeModal"
                    class="bg-gray-800 hover:bg-gray-900 text-white px-5 py-2 rounded shadow font-bold transition"
                >
                    Tutup
                </button>

            </div>

        </div>

    </div>

</template>