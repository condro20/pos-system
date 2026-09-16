<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav class="border-b border-gray-100 bg-white">
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo class="block h-9 w-auto fill-current text-indigo-600" />
                                </Link>
                            </div>

                            <!-- Navigation Links (Desktop) -->
                            <div class="hidden sm:-my-px sm:ms-8 sm:flex space-x-2">
                                
                                <!-- ============================ -->
                                <!-- 1 & 2. DASHBOARD & KASIR     -->
                                <!-- ============================ -->
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                    Dashboard
                                </NavLink>
                                <NavLink :href="route('pos.index')" :active="route().current('pos.*')">
                                    Kasir (POS)
                                </NavLink>

                                <!-- ============================ -->
                                <!-- 3. MASTER DATA               -->
                                <!-- ============================ -->
                                <div class="hidden sm:flex sm:items-center">
                                    <Dropdown align="left" width="48">
                                        <template #trigger>
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150 h-full"
                                            :class="{'text-indigo-700 border-b-2 border-indigo-400': route().current('customers.*') || route().current('products.*') || route().current('categories.*') || route().current('suppliers.*')}">
                                                Master Data
                                                <svg class="ml-1 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                            </button>
                                        </template>
                                        <template #content>
                                            <!-- Semua Role (Kasir/Manager/Owner) bisa melihat Pelanggan -->
                                            <DropdownLink :href="route('customers.index')">Pelanggan</DropdownLink>
                                            <DropdownLink :href="route('products.index')">Produk</DropdownLink>
                                            <DropdownLink :href="route('categories.index')">Kategori</DropdownLink>
                                            
                                            <!-- Hanya Manager & Owner -->
                                            <template v-if="['owner', 'manager'].includes($page.props.auth.user.role_name)">
                                                <DropdownLink :href="route('suppliers.index')">Supplier</DropdownLink>
                                            </template>
                                        </template>
                                    </Dropdown>
                                </div>

                                <!-- ============================ -->
                                <!-- 4. INVENTORI                 -->
                                <!-- ============================ -->
                                <div v-if="['owner', 'manager'].includes($page.props.auth.user.role_name)" class="hidden sm:flex sm:items-center">
                                    <Dropdown align="left" width="48">
                                        <template #trigger>
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150 h-full"
                                            :class="{'text-indigo-700 border-b-2 border-indigo-400': route().current('stock-adjustments.*') || route().current('purchases.*')}">
                                                Inventori
                                                <svg class="ml-1 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                            </button>
                                        </template>
                                        <template #content>
                                            <DropdownLink :href="route('stock-adjustments.index')">Stok Opname</DropdownLink>
                                            <DropdownLink :href="route('purchases.index')">Pembelian (Restock)</DropdownLink>
                                        </template>
                                    </Dropdown>
                                </div>

                                <!-- ============================ -->
                                <!-- 5. LAPORAN                   -->
                                <!-- ============================ -->
                                <div v-if="$page.props.auth.user.role_name === 'owner'" class="hidden sm:flex sm:items-center">
                                    <Dropdown align="left" width="48">
                                        <template #trigger>
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150 h-full"
                                            :class="{'text-indigo-700 border-b-2 border-indigo-400': route().current('reports.*')}">
                                                Laporan
                                                <svg class="ml-1 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                            </button>
                                        </template>
                                        <template #content>
                                            <DropdownLink :href="route('reports.sales')">Penjualan</DropdownLink>
                                            <DropdownLink :href="route('reports.stock_card')">Kartu Stok</DropdownLink>
                                            <DropdownLink :href="route('reports.top_selling')">Barang Terlaris</DropdownLink>
                                        </template>
                                    </Dropdown>
                                </div>

                                <!-- ============================ -->
                                <!-- 6. ADMINISTRASI              -->
                                <!-- ============================ -->
                                <div v-if="$page.props.auth.user.role_name === 'owner'" class="hidden sm:flex sm:items-center">
                                    <Dropdown align="left" width="48">
                                        <template #trigger>
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150 h-full"
                                            :class="{'text-indigo-700 border-b-2 border-indigo-400': route().current('users.*') || route().current('settings.*')}">
                                                Administrasi
                                                <svg class="ml-1 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                            </button>
                                        </template>
                                        <template #content>
                                            <DropdownLink :href="route('users.index')">Pengguna (Users)</DropdownLink>
                                            <DropdownLink :href="route('settings.index')">Pengaturan Toko</DropdownLink>
                                        </template>
                                    </Dropdown>
                                </div>

                            </div>
                        </div>

                        <!-- Profile Dropdown (Kanan) -->
                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 hover:text-gray-700 focus:outline-none transition duration-150">
                                                <div class="w-8 h-8 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center mr-2 font-bold uppercase">
                                                    {{ $page.props.auth.user.name.charAt(0) }}
                                                </div>
                                                {{ $page.props.auth.user.name }}
                                                <svg class="-me-0.5 ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>
                                    <template #content>
                                        <div class="block px-4 py-2 text-xs text-gray-400 font-bold uppercase border-b">
                                            Role: <span class="text-indigo-500">{{ $page.props.auth.user.role_name }}</span>
                                        </div>
                                        <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger (Mobile Only) -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button @click="showingNavigationDropdown = !showingNavigationDropdown" class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:outline-none transition">
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ============================ -->
                <!-- MOBILE NAVIGATION MENU       -->
                <!-- ============================ -->
                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
                    <div class="space-y-1 pb-3 pt-2">
                        
                        <!-- 1 & 2. DASHBOARD & KASIR -->
                        <div class="px-4 py-2 text-xs font-bold text-gray-400 uppercase tracking-wider">Utama</div>
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">Dashboard</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('pos.index')" :active="route().current('pos.*')">Kasir (POS)</ResponsiveNavLink>

                        <!-- 3. MASTER DATA -->
                        <div class="px-4 py-2 mt-2 text-xs font-bold text-gray-400 uppercase tracking-wider border-t pt-4">Master Data</div>
                        <ResponsiveNavLink :href="route('customers.index')" :active="route().current('customers.*')">Pelanggan</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('products.index')" :active="route().current('products.*')">Produk</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('categories.index')" :active="route().current('categories.*')">Kategori</ResponsiveNavLink>

                        <template v-if="['owner', 'manager'].includes($page.props.auth.user.role_name)">
                            <ResponsiveNavLink :href="route('suppliers.index')" :active="route().current('suppliers.*')">Supplier</ResponsiveNavLink>
                            
                            <!-- 4. INVENTORI -->
                            <div class="px-4 py-2 mt-2 text-xs font-bold text-gray-400 uppercase tracking-wider border-t pt-4">Inventori</div>
                            <ResponsiveNavLink :href="route('stock-adjustments.index')" :active="route().current('stock-adjustments.*')">Stok Opname</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('purchases.index')" :active="route().current('purchases.*')">Pembelian (Restock)</ResponsiveNavLink>
                        </template>

                        <template v-if="$page.props.auth.user.role_name === 'owner'">
                            <!-- 5. LAPORAN -->
                            <div class="px-4 py-2 mt-2 text-xs font-bold text-gray-400 uppercase tracking-wider border-t pt-4">Laporan</div>
                            <ResponsiveNavLink :href="route('reports.sales')" :active="route().current('reports.sales')">Penjualan</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('reports.stock_card')" :active="route().current('reports.stock_card')">Kartu Stok</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('reports.top_selling')" :active="route().current('reports.top_selling')">Barang Terlaris</ResponsiveNavLink>
                            
                            <!-- 6. ADMINISTRASI -->
                            <div class="px-4 py-2 mt-2 text-xs font-bold text-gray-400 uppercase tracking-wider border-t pt-4">Administrasi</div>
                            <ResponsiveNavLink :href="route('users.index')" :active="route().current('users.*')">Pengguna (Users)</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('settings.index')" :active="route().current('settings.*')">Pengaturan Toko</ResponsiveNavLink>
                        </template>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="border-t border-gray-200 pb-1 pt-4 bg-gray-50">
                        <div class="px-4 flex items-center gap-3">
                            <div class="w-10 h-10 bg-indigo-200 text-indigo-700 rounded-full flex items-center justify-center font-bold text-lg uppercase">
                                {{ $page.props.auth.user.name.charAt(0) }}
                            </div>
                            <div>
                                <div class="text-base font-medium text-gray-800">{{ $page.props.auth.user.name }}</div>
                                <div class="text-sm font-medium text-gray-500">{{ $page.props.auth.user.email }}</div>
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">Profile</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button">Log Out</ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header class="bg-white shadow-sm border-b" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>