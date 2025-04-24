<script setup lang="ts">
import { ref, onMounted, onUnmounted, reactive } from 'vue';
import { io } from 'socket.io-client';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const socket = io("https://socketserverfpl.up.railway.app", {
    reconnectionAttempts: 5,
    reconnectionDelay: 1000,
    timeout: 20000,
    transports: ['websocket', 'polling']
});
const socketConnected = ref(false);
const socketError = ref('');
const messages = ref<string[]>([]);
const message = ref('');
const escribiendoP = reactive({
    escribiendo: '',
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Chat',
        href: '/chat',
    },
];
const sendMessage = () => {
    if (message.value.trim()) {
        socket.emit('mensaje', message.value);
        message.value = '';
    }
};

const onInputText = () => {
    socket.emit('escribiendo', 'Usuario'); // Cambia 'Usuario' por el nombre del usuario real
};

onMounted(() => {
    // Set up socket connection event handlers
    socket.on('connect', () => {
        console.log('Socket connected');
        socketConnected.value = true;
        socketError.value = '';
    });

    socket.on('connect_error', (err) => {
        console.error('Socket connection error:', err);
        socketConnected.value = false;
        socketError.value = `Connection error: ${err.message}`;
    });

    socket.on('disconnect', (reason) => {
        console.log('Socket disconnected:', reason);
        socketConnected.value = false;
        if (reason === 'io server disconnect') {
            // the disconnection was initiated by the server, reconnect manually
            socket.connect();
        }
    });

    socket.on('mensaje', (msg) => {
        console.log('Received message:', msg);
        messages.value.push(msg);
    });

    socket.on('escribiendo', (usuario) => {
        console.log('Usuario escribiendo:', usuario);
        // escribiendoDiv.textContent = usuario+'- está escribiendo...';
        escribiendoP.escribiendo = usuario + ' está escribiendo...';
        setTimeout(() => {
            escribiendoP.escribiendo = '';
        }, 4000);
    });
});

onUnmounted(() => {
    socket.off('mensaje');
    socket.off('escribiendo');
});
</script>

<template>
    <Head title="Chat" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Socket connection status -->
            <div class="mb-2">
                <span v-if="socketConnected" class="text-green-600 flex items-center">
                    <span class="inline-block w-2 h-2 bg-green-600 rounded-full mr-1"></span>
                    Connected to server
                </span>
                <span v-else class="text-red-600 flex items-center">
                    <span class="inline-block w-2 h-2 bg-red-600 rounded-full mr-1"></span>
                    Disconnected from server
                </span>
                <span v-if="socketError" class="block text-red-600 mt-1">{{ socketError }}</span>
            </div>

            <ul id="messages">
                <li v-for="message in messages" :key="message">{{ message }}</li>
            </ul>
            <form @submit.prevent="sendMessage">
                <input id="message-input" v-model="message" autocomplete="off" @input="onInputText" />
                <button id="send-button" type="submit">Send</button>
            </form>
            <p id="escribiendo">{{ escribiendoP.escribiendo }}</p>
        </div>
    </AppLayout>
</template>

<style scoped>
#messages {
    list-style-type: none;
    margin: 0;
    padding: 0;
}
#messages li {
    padding: 8px;
    margin-bottom: 10px;
    background-color: #f4f4f4;
}
</style>
