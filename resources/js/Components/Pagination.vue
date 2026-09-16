<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: Array,
});
</script>

<template>
    <div v-if="links.length > 3" class="flex flex-wrap gap-1 mt-6 justify-center sm:justify-end">
        <template v-for="(link, key) in links" :key="key">
            <!-- Jika tombol disable (misal tombol "Previous" di halaman 1) -->
            <div v-if="link.url === null" 
                 class="px-4 py-2 text-sm text-gray-400 border border-gray-200 rounded-md bg-gray-50" 
                 v-html="link.label" />
            
            <!-- Jika tombol bisa diklik -->
            <Link v-else 
                  :href="link.url" 
                  class="px-4 py-2 text-sm border rounded-md hover:bg-gray-50 focus:border-indigo-500 focus:text-indigo-500 transition" 
                  :class="{ 'bg-indigo-50 border-indigo-300 text-indigo-600 font-bold': link.active, 'bg-white border-gray-300 text-gray-700': !link.active }" 
                  v-html="link.label" />
        </template>
    </div>
</template>