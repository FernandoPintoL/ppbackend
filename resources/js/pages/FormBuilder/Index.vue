<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import FormBuilderCanvas from './FormBuilderCanvas.vue';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const flashMessage = computed(() => page.props.flash?.message);
const highlightInvitationId = computed(() => page.props.flash?.highlight_invitation);

// Scroll to highlighted invitation if needed
onMounted(() => {
    if (highlightInvitationId.value) {
        setTimeout(() => {
            const element = document.getElementById(`invitation-${highlightInvitationId.value}`);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth' });
                element.classList.add('highlight-animation');
            }
        }, 500);
    }
});

// Get forms from props
const ownedForms = ref(page.props.ownedForms || []);
const collaboratingForms = ref(page.props.collaboratingForms || []);
const pendingInvitations = ref(page.props.pendingInvitations || []);

// Selected form for editing
const selectedForm = ref(null);
const showNewForm = ref(false);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Form Builder',
        href: '/form-builder',
    },
];

// Select a form to edit
const selectForm = (form) => {
    selectedForm.value = form;
    showNewForm.value = false;
};

// Create a new form
const createNewForm = () => {
    selectedForm.value = null;
    showNewForm.value = true;
};

// Accept an invitation
const acceptInvitation = async (formId) => {
    try {
        await axios.post(`/form-builder/${formId}/accept`);
        // Refresh the page to update the lists
        window.location.reload();
    } catch (error) {
        console.error('Error accepting invitation:', error);
    }
};

// Reject an invitation
const rejectInvitation = async (formId) => {
    try {
        await axios.post(`/form-builder/${formId}/reject`);
        // Refresh the page to update the lists
        window.location.reload();
    } catch (error) {
        console.error('Error rejecting invitation:', error);
    }
};
</script>

<template>
    <Head title="Form Builder" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Flash Message -->
            <div v-if="flashMessage" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ flashMessage }}</span>
            </div>

            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Form Builder</h1>
                    <p class="text-gray-600">Create forms by dragging and dropping elements. Changes are saved automatically and shared with collaborators in real-time.</p>
                </div>
                <button
                    @click="createNewForm"
                    class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
                >
                    Create New Form
                </button>
            </div>

            <!-- Forms List -->
            <div v-if="!selectedForm && !showNewForm" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Owned Forms -->
                <div v-if="ownedForms.length > 0" class="col-span-full mb-4">
                    <h2 class="text-xl font-semibold mb-2">Your Forms</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="form in ownedForms"
                            :key="form.id"
                            @click="selectForm(form)"
                            class="bg-white p-4 rounded-lg shadow-md cursor-pointer hover:shadow-lg transition-shadow"
                        >
                            <h3 class="text-lg font-medium">{{ form.name }}</h3>
                            <p class="text-gray-500 text-sm">Created: {{ new Date(form.created_at).toLocaleDateString() }}</p>
                            <div class="mt-2 flex items-center text-xs text-gray-500">
                                <span>Owner</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Collaborating Forms -->
                <div v-if="collaboratingForms.length > 0" class="col-span-full mb-4">
                    <h2 class="text-xl font-semibold mb-2">Collaborating Forms</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="form in collaboratingForms"
                            :key="form.id"
                            @click="selectForm(form)"
                            class="bg-white p-4 rounded-lg shadow-md cursor-pointer hover:shadow-lg transition-shadow"
                        >
                            <h3 class="text-lg font-medium">{{ form.name }}</h3>
                            <p class="text-gray-500 text-sm">Created: {{ new Date(form.created_at).toLocaleDateString() }}</p>
                            <div class="mt-2 flex items-center text-xs text-gray-500">
                                <span>Collaborator</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Invitations -->
                <div v-if="pendingInvitations.length > 0" class="col-span-full">
                    <h2 class="text-xl font-semibold mb-2">Pending Invitations</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="form in pendingInvitations"
                            :key="form.id"
                            :id="`invitation-${form.id}`"
                            class="bg-white p-4 rounded-lg shadow-md"
                        >
                            <h3 class="text-lg font-medium">{{ form.name }}</h3>
                            <p class="text-gray-500 text-sm">Created: {{ new Date(form.created_at).toLocaleDateString() }}</p>
                            <div class="mt-2 flex space-x-2">
                                <button
                                    @click="acceptInvitation(form.id)"
                                    class="bg-green-500 text-white px-2 py-1 rounded text-xs hover:bg-green-600"
                                >
                                    Accept
                                </button>
                                <button
                                    @click="rejectInvitation(form.id)"
                                    class="bg-red-500 text-white px-2 py-1 rounded text-xs hover:bg-red-600"
                                >
                                    Reject
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No Forms Message -->
                <div v-if="ownedForms.length === 0 && collaboratingForms.length === 0 && pendingInvitations.length === 0" class="col-span-full text-center py-8">
                    <p class="text-gray-500">You don't have any forms yet. Click "Create New Form" to get started.</p>
                </div>
            </div>

            <!-- Form Builder Canvas -->
            <FormBuilderCanvas
                v-if="selectedForm || showNewForm"
                :form-id="selectedForm?.id"
                :initial-form-data="selectedForm || { name: 'New Form', elements: [] }"
                :current-user-id="user.id"
                :current-user-name="user.name"
            />

            <!-- Back Button when editing a form -->
            <div v-if="selectedForm || showNewForm" class="mt-4">
                <button
                    @click="selectedForm = null; showNewForm = false"
                    class="text-blue-500 hover:underline"
                >
                    &larr; Back to Forms List
                </button>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@keyframes highlight {
    0% {
        background-color: #f0f9ff;
        box-shadow: 0 0 0 2px #3b82f6;
    }
    50% {
        background-color: #dbeafe;
        box-shadow: 0 0 0 4px #3b82f6;
    }
    100% {
        background-color: #f0f9ff;
        box-shadow: 0 0 0 2px #3b82f6;
    }
}

.highlight-animation {
    animation: highlight 2s ease-in-out 3;
}
</style>
