<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue'; // Import komponen modal kita

// Props dari Controller
const props = defineProps({
    products: Array
});

// === STATE MODAL KUSTOM ===
const showModal = ref(false);
const modalTitle = ref('');
const modalMessage = ref('');
const isPromptMode = ref(false);
const modalPlaceholder = ref('');
let resolveModal = null;

// Fungsi pemanggil modal dinamis berbasis Promise
const customDialog = ({ title = 'Peringatan', message = '', isPrompt = false, placeholder = '' }) => {
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
    if (resolveModal) resolveModal(value);
};

const handleModalClose = () => {
    showModal.value = false;
    if (resolveModal) resolveModal(null);
};


// === STATE UTAMA ===
const searchQuery = ref('');
const cart = ref([]);
const heldCarts = ref(JSON.parse(localStorage.getItem('pos_held_carts')) || []);

// Simpan otomatis ke local storage setiap ada perubahan data hold
watch(heldCarts, (newVal) => {
    localStorage.setItem('pos_held_carts', JSON.stringify(newVal));
}, { deep: true });

// Computed: Filter produk berdasarkan pencarian
const filteredProducts = computed(() => {
    return props.products.filter(product => 
        product.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        (product.barcode && product.barcode.includes(searchQuery.value))
    );
});

// Computed: Total Keranjang
const grandTotal = computed(() => {
    return cart.value.reduce((total, item) => total + (item.qty * item.selling_price), 0);
});

// Fungsi: Format Rupiah
const formatRp = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
};

// Fungsi: Tambah ke Keranjang
const addToCart = (product) => {
    const existingItem = cart.value.find(item => item.product_id === product.id);
    if (existingItem) {
        existingItem.qty += 1;
    } else {
        cart.value.push({
            product_id: product.id,
            name: product.name,
            unit: product.unit,
            selling_price: Number(product.selling_price),
            qty: 1
        });
    }
};

// Fungsi: Hapus dari Keranjang
const removeFromCart = (index) => {
    cart.value.splice(index, 1);
};

// Fungsi Menahan (Hold) Keranjang (Diubah menjadi Async)
const holdCart = async () => {
    if (cart.value.length === 0) {
        await customDialog({ title: 'Info', message: 'Keranjang masih kosong, tidak ada yang bisa di-hold.' });
        return;
    }
    
    // Panggil modal pengganti prompt
    const refName = await customDialog({
        title: 'Input Referensi',
        message: 'Masukkan referensi (Contoh: Ibu Budi / Ambil dompet):',
        isPrompt: true,
        placeholder: 'Nama referensi...'
    });

    // Batal jika input kosong atau menekan tombol batal (mengembalikan null)
    if (!refName || refName === true) return; 

    // Simpan keranjang saat ini ke dalam daftar hold
    heldCarts.value.push({
        id: Date.now(),
        name: refName,
        time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
        items: [...cart.value]
    });
    
    cart.value = []; // Kosongkan keranjang utama
};

// Fungsi Melanjutkan (Restore) Keranjang (Diubah menjadi Async)
const restoreHeldCart = async (index) => {
    if (cart.value.length > 0) {
        // Panggil modal pengganti confirm
        const confirmed = await customDialog({
            title: 'Konfirmasi Timpa Keranjang',
            message: 'Peringatan: Keranjang kasir saat ini tidak kosong! Yakin ingin menimpanya? (Gunakan Hold lagi jika ingin menahan keranjang ini)'
        });
        
        if (!confirmed) return; // Jika klik batal
    }
    
    // Kembalikan isi keranjang dan hapus dari antrean hold
    cart.value = [...heldCarts.value[index].items];
    heldCarts.value.splice(index, 1);
};

// Fungsi Menghapus Keranjang Hold (Diubah menjadi Async)
const removeHeldCart = async (index) => {
    const confirmed = await customDialog({
        title: 'Hapus Transaksi Tertahan',
        message: 'Yakin ingin menghapus transaksi tertahan ini secara permanen?'
    });
    
    if (confirmed) {
        heldCarts.value.splice(index, 1);
    }
};

// Fungsi: Checkout (Diubah menjadi Async)
const submitCheckout = async () => {
    if (cart.value.length === 0) {
        await customDialog({ title: 'Info', message: 'Keranjang masih kosong!' });
        return;
    }
    
    axios.post(route('pos.store'), {
        items: cart.value,
        payment_method: 'Cash'
    })
    .then(response => {
        window.open(response.data.print_url, '_blank');
        cart.value = [];
        router.reload({ only: ['products'] });
    })
    .catch(async (error) => {
        console.error(error);
        let errorMsg = 'Gagal memproses transaksi. Cek stok atau koneksi.';
        if (error.response && error.response.data.message) {
            errorMsg = error.response.data.message;
        }
        // Ganti alert bawaan saat error dengan modal kustom
        await customDialog({ title: 'Transaksi Gagal', message: errorMsg });
    });
};
</script>

<template>
    <Head title="Point of Sale" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Mesin Kasir</h2>
                <Link :href="route('pos.history')" class="bg-indigo-600 text-white px-4 py-2 rounded-md font-bold shadow-sm hover:bg-indigo-700 transition text-sm">
                    Liat Riwayat & Cetak Ulang Struk
                </Link>
            </div>
        </template>
        <div class="py-4">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 flex flex-col md:flex-row gap-4 h-[80vh]">
                
                <!-- Kiri: Daftar Produk -->
                <div class="w-full md:w-2/3 bg-white p-4 shadow-sm sm:rounded-lg flex flex-col">
                    <input 
                        v-model="searchQuery" 
                        type="text" 
                        placeholder="Cari nama produk atau scan barcode..." 
                        class="w-full border-gray-300 rounded-md shadow-sm mb-4 focus:ring-indigo-500"
                    />
                    
                    <div class="overflow-y-auto grid grid-cols-2 lg:grid-cols-3 gap-4">
                        <div 
                            v-for="product in filteredProducts" 
                            :key="product.id"
                            @click="addToCart(product)"
                            class="border p-4 rounded-lg cursor-pointer hover:bg-blue-50 transition shadow-sm active:scale-95"
                        >
                            <h3 class="font-bold text-gray-800">{{ product.name }}</h3>
                            <p class="text-sm text-gray-500">Stok: {{ parseFloat(product.stock) }} {{ product.unit }}</p>
                            <p class="text-indigo-600 font-bold mt-2">{{ formatRp(product.selling_price) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Kanan: Keranjang (Cart) -->
                <div class="w-full md:w-1/3 bg-white p-4 shadow-sm sm:rounded-lg flex flex-col justify-between">
                    <div>
                        <h2 class="text-lg font-bold mb-4 border-b pb-2">Keranjang Belanja</h2>
                        
                        <div class="overflow-y-auto max-h-[50vh]">
                            <div v-if="cart.length === 0" class="text-center text-gray-400 my-10">
                                Belum ada produk di keranjang.
                            </div>
                            
                            <div v-for="(item, index) in cart" :key="index" class="flex flex-col mb-4 border-b pb-2">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="font-semibold text-sm">{{ item.name }}</span>
                                    <button @click="removeFromCart(index)" class="text-red-500 text-xs hover:underline">Hapus</button>
                                </div>
                                <div class="flex justify-between items-center">
                                    <div class="flex items-center gap-2">
                                        <input 
                                            v-model.number="item.qty" 
                                            type="number" 
                                            step="0.01" 
                                            min="0.01"
                                            class="w-20 text-center text-sm border-gray-300 rounded-md"
                                        />
                                        <span class="text-sm text-gray-500">{{ item.unit }}</span>
                                    </div>
                                    <span class="font-bold">{{ formatRp(item.qty * item.selling_price) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total & Checkout -->
                    <div class="border-t pt-4 mt-auto">
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-lg font-bold">Total:</span>
                            <span class="text-2xl font-bold text-indigo-600">{{ formatRp(grandTotal) }}</span>
                        </div>
                        <div class="mt-4 flex gap-2">
                            <button @click="holdCart" class="w-1/3 py-3 bg-yellow-500 hover:bg-yellow-600 text-white font-bold rounded-lg shadow-sm transition">
                                Tahan (Hold)
                            </button>
                            <button @click="submitCheckout" class="w-2/3 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow-sm transition">
                                Bayar (Checkout)
                            </button>
                        </div>
                    </div>

                    <!-- DAFTAR TRANSAKSI HOLD -->
                    <div v-if="heldCarts.length > 0" class="mt-6 border-t pt-4">
                        <h3 class="font-bold text-gray-800 flex items-center gap-2 mb-3">
                            <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Transaksi Tertahan ({{ heldCarts.length }})
                        </h3>
                        
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            <div v-for="(hc, index) in heldCarts" :key="hc.id" class="flex justify-between items-center p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                                <div>
                                    <p class="font-bold text-gray-800">{{ hc.name }}</p>
                                    <p class="text-xs text-gray-500">{{ hc.items.length }} macam barang &bull; Jam: {{ hc.time }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <button @click="restoreHeldCart(index)" class="px-3 py-1 bg-green-500 text-white text-xs font-bold rounded hover:bg-green-600">
                                        Lanjutkan
                                    </button>
                                    <button @click="removeHeldCart(index)" class="px-2 py-1 bg-red-100 text-red-600 text-xs font-bold rounded hover:bg-red-200">
                                        &times;
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL KUSTOM DILETAKKAN DI SINI -->
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