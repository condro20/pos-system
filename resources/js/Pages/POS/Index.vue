<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

// Props dari Controller
const props = defineProps({
    products: {
        type: Array,
        default: () => []
    }
});

// =====================================================
// MODAL
// =====================================================

const showModal = ref(false);
const modalTitle = ref('');
const modalMessage = ref('');
const isPromptMode = ref(false);
const modalPlaceholder = ref('');

let resolveModal = null;

const customDialog = ({
    title = 'Peringatan',
    message = '',
    isPrompt = false,
    placeholder = ''
}) => {
    modalTitle.value = title;
    modalMessage.value = message;
    isPromptMode.value = isPrompt;
    modalPlaceholder.value = placeholder;
    showModal.value = true;

    return new Promise((resolve) => {
        resolveModal = resolve;
    });
};

const handleModalConfirm = (value) => {
    showModal.value = false;

    if (resolveModal) {
        resolveModal(value);
        resolveModal = null;
    }
};

const handleModalClose = () => {
    showModal.value = false;

    if (resolveModal) {
        resolveModal(null);
        resolveModal = null;
    }
};

// =====================================================
// STATE UTAMA
// =====================================================

const searchQuery = ref('');
const cart = ref([]);

const getHeldCarts = () => {
    try {
        const stored = localStorage.getItem('pos_held_carts');

        if (!stored) {
            return [];
        }

        const parsed = JSON.parse(stored);

        return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        console.error('Gagal membaca held carts:', error);
        return [];
    }
};

const heldCarts = ref(getHeldCarts());

// Simpan otomatis held cart ke localStorage
watch(
    heldCarts,
    (newVal) => {
        localStorage.setItem(
            'pos_held_carts',
            JSON.stringify(newVal)
        );
    },
    { deep: true }
);

// =====================================================
// SEARCH PRODUK
// =====================================================

const filteredProducts = computed(() => {
    const keyword = searchQuery.value.trim().toLowerCase();

    if (!keyword) {
        return props.products;
    }

    return props.products.filter((product) => {
        const name = String(product.name ?? '').toLowerCase();
        const barcode = String(product.barcode ?? '').toLowerCase();

        return (
            name.includes(keyword) ||
            barcode.includes(keyword)
        );
    });
});

// =====================================================
// TOTAL CART
// =====================================================

const grandTotal = computed(() => {
    return cart.value.reduce((total, item) => {
        const qty = Number(item.qty) || 0;
        const price = Number(item.selling_price) || 0;

        return total + (qty * price);
    }, 0);
});

// =====================================================
// FORMAT RUPIAH
// =====================================================

const formatRp = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(Number(value) || 0);
};

// =====================================================
// TAMBAH PRODUK KE CART
// =====================================================

const addToCart = (product) => {
    const existingItem = cart.value.find(
        item => item.product_id === product.id
    );

    if (existingItem) {
        existingItem.qty = Number(existingItem.qty) + 1;
        return;
    }

    cart.value.push({
        product_id: Number(product.id),
        name: product.name,
        unit: product.unit,
        selling_price: Number(product.selling_price),
        qty: 1
    });
};

// =====================================================
// NORMALISASI QUANTITY
// =====================================================

const normalizeQuantity = (item) => {
    let qty = Number(item.qty);

    if (!Number.isFinite(qty) || qty <= 0) {
        item.qty = 0.01;
        return;
    }

    // Maksimal 3 angka desimal sesuai database
    qty = Math.round(qty * 1000) / 1000;

    item.qty = qty;
};

// =====================================================
// HAPUS ITEM CART
// =====================================================

const removeFromCart = (index) => {
    cart.value.splice(index, 1);
};

// =====================================================
// HOLD CART
// =====================================================

const holdCart = async () => {
    if (cart.value.length === 0) {
        await customDialog({
            title: 'Info',
            message: 'Keranjang masih kosong, tidak ada yang bisa di-hold.'
        });

        return;
    }

    const refName = await customDialog({
        title: 'Input Referensi',
        message: 'Masukkan referensi (Contoh: Ibu Budi / Ambil dompet):',
        isPrompt: true,
        placeholder: 'Nama referensi...'
    });

    if (!refName || refName === true) {
        return;
    }

    heldCarts.value.push({
        id: Date.now(),
        name: String(refName).trim(),
        time: new Date().toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit'
        }),
        items: cart.value.map(item => ({
            ...item,
            qty: Number(item.qty)
        }))
    });

    cart.value = [];
};

// =====================================================
// RESTORE HELD CART
// =====================================================

const restoreHeldCart = async (index) => {
    if (cart.value.length > 0) {
        const confirmed = await customDialog({
            title: 'Konfirmasi Timpa Keranjang',
            message:
                'Peringatan: Keranjang kasir saat ini tidak kosong! ' +
                'Yakin ingin menimpanya? ' +
                '(Gunakan Hold lagi jika ingin menahan keranjang ini)'
        });

        if (!confirmed) {
            return;
        }
    }

    const heldCart = heldCarts.value[index];

    if (!heldCart || !Array.isArray(heldCart.items)) {
        await customDialog({
            title: 'Error',
            message: 'Data transaksi tertahan tidak valid.'
        });

        return;
    }

    cart.value = heldCart.items.map(item => ({
        ...item,
        product_id: Number(item.product_id),
        selling_price: Number(item.selling_price),
        qty: Number(item.qty)
    }));

    heldCarts.value.splice(index, 1);
};

// =====================================================
// DELETE HELD CART
// =====================================================

const removeHeldCart = async (index) => {
    const confirmed = await customDialog({
        title: 'Hapus Transaksi Tertahan',
        message:
            'Yakin ingin menghapus transaksi tertahan ini secara permanen?'
    });

    if (confirmed) {
        heldCarts.value.splice(index, 1);
    }
};

// =====================================================
// CHECKOUT
// =====================================================

const submitCheckout = async () => {
    if (cart.value.length === 0) {
        await customDialog({
            title: 'Info',
            message: 'Keranjang masih kosong!'
        });

        return;
    }

    // Pastikan semua quantity valid sebelum dikirim
    cart.value.forEach(item => {
        normalizeQuantity(item);
    });

    // =================================================
    // PENTING:
    // Hanya kirim product_id + qty ke backend.
    // Harga TIDAK dikirim karena harus berasal dari DB.
    // =================================================

    const items = cart.value.map(item => ({
        product_id: Number(item.product_id),
        qty: Number(item.qty)
    }));

    try {
        const response = await axios.post(
            route('pos.store'),
            {
                items,
                payment_method: 'Cash'
            }
        );

        if (response.data?.print_url) {
            window.open(
                response.data.print_url,
                '_blank'
            );
        }

        // Kosongkan cart setelah transaksi berhasil
        cart.value = [];

        // Reload stok dari server
        router.reload({
            only: ['products']
        });

    } catch (error) {
        console.error('Checkout error:', error);

        let errorMsg =
            'Gagal memproses transaksi. Cek stok atau koneksi.';

        if (error.response?.data?.message) {
            errorMsg = error.response.data.message;
        }

        await customDialog({
            title: 'Transaksi Gagal',
            message: errorMsg
        });
    }
};
</script>

<template>
    <Head title="Point of Sale" />

    <AuthenticatedLayout>

        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Mesin Kasir
                </h2>

                <Link
                    :href="route('pos.history')"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-md font-bold shadow-sm hover:bg-indigo-700 transition text-sm"
                >
                    Liat Riwayat & Cetak Ulang Struk
                </Link>
            </div>
        </template>

        <div class="py-4">
            <div
                class="max-w-7xl mx-auto sm:px-6 lg:px-8 flex flex-col md:flex-row gap-4 h-[80vh]"
            >

                <!-- ========================================= -->
                <!-- DAFTAR PRODUK -->
                <!-- ========================================= -->

                <div
                    class="w-full md:w-2/3 bg-white p-4 shadow-sm sm:rounded-lg flex flex-col"
                >
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari nama produk atau scan barcode..."
                        class="w-full border-gray-300 rounded-md shadow-sm mb-4 focus:ring-indigo-500"
                    />

                    <div
                        class="overflow-y-auto grid grid-cols-2 lg:grid-cols-3 gap-4"
                    >
                        <div
                            v-for="product in filteredProducts"
                            :key="product.id"
                            @click="addToCart(product)"
                            class="border p-4 rounded-lg cursor-pointer hover:bg-blue-50 transition shadow-sm active:scale-95"
                        >
                            <h3 class="font-bold text-gray-800">
                                {{ product.name }}
                            </h3>

                            <p class="text-sm text-gray-500">
                                Stok:
                                {{ parseFloat(product.stock) }}
                                {{ product.unit }}
                            </p>

                            <p class="text-indigo-600 font-bold mt-2">
                                {{ formatRp(product.selling_price) }}
                            </p>
                        </div>

                        <div
                            v-if="filteredProducts.length === 0"
                            class="col-span-full text-center text-gray-400 py-10"
                        >
                            Produk tidak ditemukan.
                        </div>
                    </div>
                </div>

                <!-- ========================================= -->
                <!-- CART -->
                <!-- ========================================= -->

                <div
                    class="w-full md:w-1/3 bg-white p-4 shadow-sm sm:rounded-lg flex flex-col justify-between"
                >

                    <div>
                        <h2
                            class="text-lg font-bold mb-4 border-b pb-2"
                        >
                            Keranjang Belanja
                        </h2>

                        <div class="overflow-y-auto max-h-[50vh]">

                            <div
                                v-if="cart.length === 0"
                                class="text-center text-gray-400 my-10"
                            >
                                Belum ada produk di keranjang.
                            </div>

                            <div
                                v-for="(item, index) in cart"
                                :key="item.product_id"
                                class="flex flex-col mb-4 border-b pb-2"
                            >

                                <div
                                    class="flex justify-between items-start mb-2"
                                >
                                    <span
                                        class="font-semibold text-sm"
                                    >
                                        {{ item.name }}
                                    </span>

                                    <button
                                        @click="removeFromCart(index)"
                                        class="text-red-500 text-xs hover:underline"
                                    >
                                        Hapus
                                    </button>
                                </div>

                                <div
                                    class="flex justify-between items-center"
                                >

                                    <div
                                        class="flex items-center gap-2"
                                    >
                                        <input
                                            v-model.number="item.qty"
                                            @change="normalizeQuantity(item)"
                                            type="number"
                                            step="0.001"
                                            min="0.001"
                                            class="w-20 text-center text-sm border-gray-300 rounded-md"
                                        />

                                        <span
                                            class="text-sm text-gray-500"
                                        >
                                            {{ item.unit }}
                                        </span>
                                    </div>

                                    <span class="font-bold">
                                        {{
                                            formatRp(
                                                Number(item.qty) *
                                                Number(item.selling_price)
                                            )
                                        }}
                                    </span>

                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- ===================================== -->
                    <!-- TOTAL & CHECKOUT -->
                    <!-- ===================================== -->

                    <div
                        class="border-t pt-4 mt-auto"
                    >
                        <div
                            class="flex justify-between items-center mb-4"
                        >
                            <span class="text-lg font-bold">
                                Total:
                            </span>

                            <span
                                class="text-2xl font-bold text-indigo-600"
                            >
                                {{ formatRp(grandTotal) }}
                            </span>
                        </div>

                        <div class="mt-4 flex gap-2">

                            <button
                                @click="holdCart"
                                class="w-1/3 py-3 bg-yellow-500 hover:bg-yellow-600 text-white font-bold rounded-lg shadow-sm transition"
                            >
                                Tahan (Hold)
                            </button>

                            <button
                                @click="submitCheckout"
                                class="w-2/3 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow-sm transition"
                            >
                                Bayar (Checkout)
                            </button>

                        </div>
                    </div>

                    <!-- ===================================== -->
                    <!-- HELD CART -->
                    <!-- ===================================== -->

                    <div
                        v-if="heldCarts.length > 0"
                        class="mt-6 border-t pt-4"
                    >

                        <h3
                            class="font-bold text-gray-800 flex items-center gap-2 mb-3"
                        >
                            <svg
                                class="w-5 h-5 text-yellow-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>

                            Transaksi Tertahan
                            ({{ heldCarts.length }})
                        </h3>

                        <div
                            class="space-y-2 max-h-48 overflow-y-auto"
                        >

                            <div
                                v-for="(hc, index) in heldCarts"
                                :key="hc.id"
                                class="flex justify-between items-center p-3 bg-yellow-50 border border-yellow-200 rounded-md"
                            >

                                <div>
                                    <p class="font-bold text-gray-800">
                                        {{ hc.name }}
                                    </p>

                                    <p class="text-xs text-gray-500">
                                        {{ hc.items.length }}
                                        macam barang
                                        &bull;
                                        Jam:
                                        {{ hc.time }}
                                    </p>
                                </div>

                                <div class="flex gap-2">

                                    <button
                                        @click="restoreHeldCart(index)"
                                        class="px-3 py-1 bg-green-500 text-white text-xs font-bold rounded hover:bg-green-600"
                                    >
                                        Lanjutkan
                                    </button>

                                    <button
                                        @click="removeHeldCart(index)"
                                        class="px-2 py-1 bg-red-100 text-red-600 text-xs font-bold rounded hover:bg-red-200"
                                    >
                                        &times;
                                    </button>

                                </div>

                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ========================================= -->
        <!-- MODAL -->
        <!-- ========================================= -->

        <ConfirmModal
            :show="showModal"
            :title="modalTitle"
            :message="modalMessage"
            :isPrompt="isPromptMode"
            :placeholder="modalPlaceholder"
            @confirm="handleModalConfirm"
            @close="handleModalClose"
        />

    </AuthenticatedLayout>
</template>