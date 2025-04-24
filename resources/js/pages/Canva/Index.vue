<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Canvas from './Canvas.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Canva',
        href: '/canva',
    },
];
</script>

<template>
    <Head title="Canva" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h1>HOLA CANVAS</h1>
<!--            <Canvas />-->
        </div>
    </AppLayout>
</template>

<style scoped>

</style>
