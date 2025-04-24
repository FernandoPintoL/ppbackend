<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import draggable from 'vuedraggable';
import { io } from 'socket.io-client';
import axios from 'axios';

// Props
const props = defineProps({
    formId: {
        type: Number,
        default: null
    },
    initialFormData: {
        type: Object,
        default: () => ({
            name: 'New Form',
            elements: []
        })
    },
    currentUserId: {
        type: Number,
        required: true
    },
    currentUserName: {
        type: String,
        required: true
    }
});

// Socket.io connection
const socket = io('http://localhost:4000');
const roomId = ref(props.formId ? `form-${props.formId}` : 'new-form'); // Dynamic room ID based on form ID
const currentUser = ref(props.currentUserName || 'User-' + Math.floor(Math.random() * 1000));

// Form elements
const formElements = ref(Array.isArray(props.initialFormData.elements)
    ? props.initialFormData.elements
    : (typeof props.initialFormData.elements === 'string'
        ? JSON.parse(props.initialFormData.elements || '[]')
        : []));
const formName = ref(props.initialFormData.name || 'New Form');
const availableElements = ref([
    { type: 'input', props: { placeholder: 'Text Input', type: 'text', label: 'Text Input', required: false } },
    { type: 'select', props: { options: ['Option 1', 'Option 2', 'Option 3'], label: 'Select', required: false } },
    { type: 'textarea', props: { placeholder: 'Text Area', label: 'Text Area', required: false } },
    { type: 'checkbox', props: { label: 'Checkbox', checked: false } },
    { type: 'radio', props: { options: ['Option 1', 'Option 2'], label: 'Radio Group' } },
    { type: 'number', props: { placeholder: 'Number Input', label: 'Number', min: 0, max: 100, required: false } },
    { type: 'date', props: { label: 'Date', required: false } },
    { type: 'button', props: { text: 'Button', variant: 'primary' } },
]);

// Selected element for editing
const selectedElement = ref(null);

// Collaboration status
const collaborators = ref([]);
const isTyping = ref({});
const inviteEmail = ref('');
const showInviteForm = ref(false);
const inviteError = ref('');
const inviteSuccess = ref('');
const showInviteLink = ref(false);
const inviteLink = ref('');

// Save form to database
const saveForm = async () => {
    try {
        let response;
        if (props.formId) {
            // Update existing form
            response = await axios.put(`/form-builder/${props.formId}`, {
                name: formName.value,
                elements: JSON.stringify(formElements.value)
            });
        } else {
            // Create new form
            response = await axios.post('/form-builder', {
                name: formName.value,
                elements: JSON.stringify(formElements.value)
            });
        }
        console.log('Form saved:', response.data);
        return response.data;
    } catch (error) {
        console.error('Error saving form:', error);
        return null;
    }
};

// Load collaborators
const loadCollaborators = async () => {
    if (!props.formId) return;

    try {
        const response = await axios.get(`/form-builder/${props.formId}/collaborators`);
        collaborators.value = response.data;
    } catch (error) {
        console.error('Error loading collaborators:', error);
    }
};

// Invite a collaborator
const inviteCollaborator = async () => {
    if (!props.formId) {
        // Save the form first if it's new
        const savedForm = await saveForm();
        if (!savedForm) {
            inviteError.value = 'Please save the form before inviting collaborators';
            return;
        }
    }

    inviteError.value = '';
    inviteSuccess.value = '';

    try {
        await axios.post(`/form-builder/${props.formId}/invite`, {
            email: inviteEmail.value
        });

        inviteSuccess.value = `Invitation sent to ${inviteEmail.value}`;
        inviteEmail.value = '';
        showInviteForm.value = false;
    } catch (error) {
        inviteError.value = error.response?.data?.message || 'Error sending invitation';
    }
};

// Generate invitation link
const generateInviteLink = () => {
    if (!props.formId) {
        // Save the form first if it's new
        saveForm().then(savedForm => {
            if (savedForm) {
                const baseUrl = window.location.origin;
                inviteLink.value = `${baseUrl}/form-builder/invite/${savedForm.id}`;
                showInviteLink.value = true;
            } else {
                inviteError.value = 'Please save the form before generating an invitation link';
            }
        });
    } else {
        const baseUrl = window.location.origin;
        inviteLink.value = `${baseUrl}/form-builder/invite/${props.formId}`;
        showInviteLink.value = true;
    }
};

// Copy invitation link to clipboard
const copyInviteLink = () => {
    navigator.clipboard.writeText(inviteLink.value)
        .then(() => {
            inviteSuccess.value = 'Link copied to clipboard!';
            setTimeout(() => {
                inviteSuccess.value = '';
            }, 3000);
        })
        .catch(err => {
            console.error('Could not copy text: ', err);
            inviteError.value = 'Failed to copy link';
        });
};

// Generate Angular code
const generateAngularCode = () => {
    let angularCode = `<form [formGroup]="formGroup" (ngSubmit)="onSubmit()">\n`;

    formElements.value.forEach(element => {
        switch (element.type) {
            case 'input':
                angularCode += `  <div class="form-group">\n`;
                angularCode += `    <label for="${element.props.label.toLowerCase().replace(/\s+/g, '-')}">${element.props.label}${element.props.required ? ' *' : ''}</label>\n`;
                angularCode += `    <input type="${element.props.type}" id="${element.props.label.toLowerCase().replace(/\s+/g, '-')}" formControlName="${element.props.label.toLowerCase().replace(/\s+/g, '-')}" placeholder="${element.props.placeholder}"${element.props.required ? ' required' : ''}>\n`;
                angularCode += `  </div>\n`;
                break;
            case 'select':
                angularCode += `  <div class="form-group">\n`;
                angularCode += `    <label for="${element.props.label.toLowerCase().replace(/\s+/g, '-')}">${element.props.label}${element.props.required ? ' *' : ''}</label>\n`;
                angularCode += `    <select id="${element.props.label.toLowerCase().replace(/\s+/g, '-')}" formControlName="${element.props.label.toLowerCase().replace(/\s+/g, '-')}"${element.props.required ? ' required' : ''}>\n`;
                element.props.options.forEach(option => {
                    angularCode += `      <option value="${option}">${option}</option>\n`;
                });
                angularCode += `    </select>\n`;
                angularCode += `  </div>\n`;
                break;
            case 'textarea':
                angularCode += `  <div class="form-group">\n`;
                angularCode += `    <label for="${element.props.label.toLowerCase().replace(/\s+/g, '-')}">${element.props.label}${element.props.required ? ' *' : ''}</label>\n`;
                angularCode += `    <textarea id="${element.props.label.toLowerCase().replace(/\s+/g, '-')}" formControlName="${element.props.label.toLowerCase().replace(/\s+/g, '-')}" placeholder="${element.props.placeholder}"${element.props.required ? ' required' : ''}></textarea>\n`;
                angularCode += `  </div>\n`;
                break;
            case 'checkbox':
                angularCode += `  <div class="form-check">\n`;
                angularCode += `    <input type="checkbox" id="${element.props.label.toLowerCase().replace(/\s+/g, '-')}" formControlName="${element.props.label.toLowerCase().replace(/\s+/g, '-')}" class="form-check-input"${element.props.checked ? ' checked' : ''}>\n`;
                angularCode += `    <label class="form-check-label" for="${element.props.label.toLowerCase().replace(/\s+/g, '-')}">${element.props.label}</label>\n`;
                angularCode += `  </div>\n`;
                break;
            case 'radio':
                angularCode += `  <div class="form-group">\n`;
                angularCode += `    <label>${element.props.label}</label>\n`;
                element.props.options.forEach((option, index) => {
                    angularCode += `    <div class="form-check">\n`;
                    angularCode += `      <input type="radio" id="${element.props.label.toLowerCase().replace(/\s+/g, '-')}-${index}" formControlName="${element.props.label.toLowerCase().replace(/\s+/g, '-')}" value="${option}" class="form-check-input">\n`;
                    angularCode += `      <label class="form-check-label" for="${element.props.label.toLowerCase().replace(/\s+/g, '-')}-${index}">${option}</label>\n`;
                    angularCode += `    </div>\n`;
                });
                angularCode += `  </div>\n`;
                break;
            case 'number':
                angularCode += `  <div class="form-group">\n`;
                angularCode += `    <label for="${element.props.label.toLowerCase().replace(/\s+/g, '-')}">${element.props.label}${element.props.required ? ' *' : ''}</label>\n`;
                angularCode += `    <input type="number" id="${element.props.label.toLowerCase().replace(/\s+/g, '-')}" formControlName="${element.props.label.toLowerCase().replace(/\s+/g, '-')}" placeholder="${element.props.placeholder}" min="${element.props.min}" max="${element.props.max}"${element.props.required ? ' required' : ''}>\n`;
                angularCode += `  </div>\n`;
                break;
            case 'date':
                angularCode += `  <div class="form-group">\n`;
                angularCode += `    <label for="${element.props.label.toLowerCase().replace(/\s+/g, '-')}">${element.props.label}${element.props.required ? ' *' : ''}</label>\n`;
                angularCode += `    <input type="date" id="${element.props.label.toLowerCase().replace(/\s+/g, '-')}" formControlName="${element.props.label.toLowerCase().replace(/\s+/g, '-')}"${element.props.required ? ' required' : ''}>\n`;
                angularCode += `  </div>\n`;
                break;
            case 'button':
                angularCode += `  <button type="submit" class="btn btn-${element.props.variant}">${element.props.text}</button>\n`;
                break;
        }
    });

    angularCode += `</form>\n\n`;

    // Add TypeScript code
    angularCode += `// Component TypeScript code\n`;
    angularCode += `import { Component, OnInit } from '@angular/core';\n`;
    angularCode += `import { FormBuilder, FormGroup, Validators } from '@angular/forms';\n\n`;
    angularCode += `@Component({\n`;
    angularCode += `  selector: 'app-${formName.value.toLowerCase().replace(/\s+/g, '-')}',\n`;
    angularCode += `  templateUrl: './${formName.value.toLowerCase().replace(/\s+/g, '-')}.component.html',\n`;
    angularCode += `  styleUrls: ['./${formName.value.toLowerCase().replace(/\s+/g, '-')}.component.css']\n`;
    angularCode += `})\n`;
    angularCode += `export class ${formName.value.replace(/\s+/g, '')}Component implements OnInit {\n`;
    angularCode += `  formGroup: FormGroup;\n\n`;
    angularCode += `  constructor(private fb: FormBuilder) { }\n\n`;
    angularCode += `  ngOnInit(): void {\n`;
    angularCode += `    this.formGroup = this.fb.group({\n`;

    formElements.value.forEach((element, index) => {
        if (element.type !== 'button') {
            const fieldName = element.props.label.toLowerCase().replace(/\s+/g, '-');
            angularCode += `      '${fieldName}': [${element.type === 'checkbox' ? element.props.checked : "''"}${element.props.required ? ', Validators.required' : ''}]${index < formElements.value.length - 1 ? ',' : ''}\n`;
        }
    });

    angularCode += `    });\n`;
    angularCode += `  }\n\n`;
    angularCode += `  onSubmit(): void {\n`;
    angularCode += `    if (this.formGroup.valid) {\n`;
    angularCode += `      console.log(this.formGroup.value);\n`;
    angularCode += `      // Submit form data to your API\n`;
    angularCode += `    }\n`;
    angularCode += `  }\n`;
    angularCode += `}\n`;

    return angularCode;
};

// Computed property for Angular code
const angularCode = computed(() => generateAngularCode());

// Select element for editing
const selectElement = (element) => {
    selectedElement.value = element;
    socket.emit('typing', { user: currentUser.value, roomId: roomId.value });
};

// Update element properties
const updateElement = () => {
    // Emit the change to other users
    socket.emit('formUpdate', {
        elements: formElements.value,
        roomId: roomId.value,
        user: currentUser.value
    });

    // Save the form
    saveForm();
};

// Handle form name change
const handleFormNameChange = () => {
    socket.emit('formNameChange', {
        name: formName.value,
        roomId: roomId.value,
        user: currentUser.value
    });
};

// Socket.io event handlers
onMounted(() => {
    // Load collaborators if we have a form ID
    if (props.formId) {
        loadCollaborators();
    }

    // Join the room
    socket.emit('joinRoom', { roomId: roomId.value, user: currentUser.value });

    // Listen for form updates
    socket.on('formUpdate', (data) => {
        if (data.user !== currentUser.value) {
            formElements.value = data.elements;
        }
    });

    // Listen for form name changes
    socket.on('formNameChange', (data) => {
        if (data.user !== currentUser.value) {
            formName.value = data.name;
        }
    });

    // Listen for typing events
    socket.on('typing', (data) => {
        if (data.user !== currentUser.value) {
            isTyping.value[data.user] = true;
            setTimeout(() => {
                isTyping.value[data.user] = false;
            }, 3000);
        }
    });

    // Listen for user join/leave events
    socket.on('userJoined', (data) => {
        collaborators.value.push(data.user);
    });

    socket.on('userLeft', (data) => {
        const index = collaborators.value.indexOf(data.user);
        if (index !== -1) {
            collaborators.value.splice(index, 1);
        }
    });

    // Get current users in the room
    socket.on('roomUsers', (data) => {
        collaborators.value = data.users.filter(user => user !== currentUser.value);
    });
});

onUnmounted(() => {
    // Leave the room
    socket.emit('leaveRoom', { roomId: roomId.value, user: currentUser.value });

    // Disconnect socket events
    socket.off('formUpdate');
    socket.off('formNameChange');
    socket.off('typing');
    socket.off('userJoined');
    socket.off('userLeft');
    socket.off('roomUsers');
});
</script>

<template>
    <div class="grid grid-cols-12 gap-4">
        <!-- Sidebar with available elements -->
        <div class="col-span-3 bg-gray-100 p-4 rounded-lg">
            <h2 class="text-lg font-semibold mb-4">Form Elements</h2>
            <draggable
                :list="availableElements"
                :group="{ name: 'formElements', pull: 'clone', put: false }"
                item-key="type"
                :clone="item => ({ ...item })"
                class="space-y-2"
            >
                <template #item="{ element }">
                    <div class="bg-white p-3 rounded shadow cursor-move hover:bg-gray-50 border border-gray-200">
                        {{ element.props.label || element.type }}
                    </div>
                </template>
            </draggable>
        </div>

        <!-- Form builder area -->
        <div class="col-span-6">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="mb-4 flex justify-between items-center">
                    <input
                        v-model="formName"
                        class="text-xl font-bold border-none focus:outline-none focus:ring-2 focus:ring-blue-500 rounded px-2 py-1 w-full"
                        @input="handleFormNameChange"
                    />
                    <div class="flex space-x-2">
                        <button
                            v-if="props.formId"
                            @click="showInviteForm = !showInviteForm"
                            class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600"
                        >
                            Invite
                        </button>
                        <button
                            @click="generateInviteLink"
                            class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600"
                        >
                            Generate Link
                        </button>
                        <button
                            @click="saveForm"
                            class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
                        >
                            Save
                        </button>
                    </div>
                </div>

                <!-- Invite Form -->
                <div v-if="showInviteForm" class="mb-4 p-4 bg-gray-100 rounded-lg">
                    <h3 class="text-lg font-semibold mb-2">Invite Collaborator</h3>
                    <div class="flex space-x-2">
                        <input
                            v-model="inviteEmail"
                            type="email"
                            placeholder="Enter email address"
                            class="flex-1 p-2 border border-gray-300 rounded"
                        />
                        <button
                            @click="inviteCollaborator"
                            class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600"
                        >
                            Send Invite
                        </button>
                    </div>
                    <div v-if="inviteError" class="mt-2 text-red-500">{{ inviteError }}</div>
                    <div v-if="inviteSuccess" class="mt-2 text-green-500">{{ inviteSuccess }}</div>
                </div>

                <!-- Invitation Link -->
                <div v-if="showInviteLink" class="mb-4 p-4 bg-gray-100 rounded-lg">
                    <h3 class="text-lg font-semibold mb-2">Invitation Link</h3>
                    <div class="flex space-x-2">
                        <input
                            v-model="inviteLink"
                            type="text"
                            readonly
                            class="flex-1 p-2 border border-gray-300 rounded bg-white"
                        />
                        <button
                            @click="copyInviteLink"
                            class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600"
                        >
                            Copy Link
                        </button>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">Share this link with anyone you want to invite to collaborate on this form.</p>
                </div>

                <!-- Collaborators -->
                <div class="mb-4">
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="text-lg font-semibold">Collaborators</h3>
                    </div>

                    <!-- Database Collaborators -->
                    <div v-if="collaborators.length > 0" class="mb-2">
                        <h4 class="text-sm font-medium text-gray-700 mb-1">Invited Users:</h4>
                        <div class="flex flex-wrap gap-2">
                            <div v-for="user in collaborators" :key="user.id" class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                                {{ user.name || user.email }}
                            </div>
                        </div>
                    </div>

                    <!-- Real-time Collaborators -->
                    <div v-if="Object.keys(isTyping).length > 0" class="mb-2">
                        <h4 class="text-sm font-medium text-gray-700 mb-1">Currently Active:</h4>
                        <div class="flex flex-wrap gap-2">
                            <div v-for="(typing, user) in isTyping" :key="user" class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">
                                {{ user }} {{ typing ? '(typing...)' : '' }}
                            </div>
                        </div>
                    </div>

                    <div v-if="collaborators.length === 0 && Object.keys(isTyping).length === 0" class="text-gray-500 text-sm">
                        No collaborators yet. Invite someone to collaborate on this form.
                    </div>
                </div>

                <!-- Form elements -->
                <draggable
                    v-model="formElements"
                    group="formElements"
                    item-key="id"
                    class="min-h-[300px] border-2 border-dashed border-gray-300 p-4 rounded-lg"
                    @end="updateElement"
                >
                    <template #item="{ element, index }">
                        <div
                            class="mb-4 p-4 border border-gray-200 rounded-lg hover:border-blue-500 cursor-pointer relative"
                            :class="{ 'border-blue-500 bg-blue-50': selectedElement === element }"
                            @click="selectElement(element)"
                        >
                            <!-- Element label -->
                            <div class="text-sm font-medium text-gray-500 mb-2">{{ element.props.label || element.type }}</div>

                            <!-- Render the actual form element -->
                            <component
                                :is="element.type"
                                v-bind="element.props"
                                class="w-full"
                            >
                                <template v-if="element.type === 'select'">
                                    <option v-for="option in element.props.options" :key="option">{{ option }}</option>
                                </template>
                                <template v-else-if="element.type === 'button'">
                                    {{ element.props.text }}
                                </template>
                            </component>

                            <!-- Remove button -->
                            <button
                                @click.stop="formElements.splice(index, 1); updateElement()"
                                class="absolute top-2 right-2 text-red-500 hover:text-red-700"
                            >
                                ×
                            </button>
                        </div>
                    </template>
                    <template #header>
                        <div v-if="formElements.length === 0" class="text-center text-gray-500 py-8">
                            Drag and drop elements here to build your form
                        </div>
                    </template>
                </draggable>
            </div>
        </div>

        <!-- Properties panel -->
        <div class="col-span-3 bg-gray-100 p-4 rounded-lg">
            <h2 class="text-lg font-semibold mb-4">Properties</h2>
            <div v-if="selectedElement" class="space-y-4">
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Label</label>
                    <input
                        v-model="selectedElement.props.label"
                        class="w-full p-2 border border-gray-300 rounded"
                        @input="updateElement"
                    />
                </div>

                <div v-if="['input', 'textarea', 'number'].includes(selectedElement.type)" class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Placeholder</label>
                    <input
                        v-model="selectedElement.props.placeholder"
                        class="w-full p-2 border border-gray-300 rounded"
                        @input="updateElement"
                    />
                </div>

                <div v-if="selectedElement.type === 'input'" class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Input Type</label>
                    <select
                        v-model="selectedElement.props.type"
                        class="w-full p-2 border border-gray-300 rounded"
                        @change="updateElement"
                    >
                        <option value="text">Text</option>
                        <option value="email">Email</option>
                        <option value="password">Password</option>
                        <option value="tel">Telephone</option>
                        <option value="url">URL</option>
                    </select>
                </div>

                <div v-if="selectedElement.type === 'number'" class="grid grid-cols-2 gap-2">
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Min</label>
                        <input
                            v-model.number="selectedElement.props.min"
                            type="number"
                            class="w-full p-2 border border-gray-300 rounded"
                            @input="updateElement"
                        />
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Max</label>
                        <input
                            v-model.number="selectedElement.props.max"
                            type="number"
                            class="w-full p-2 border border-gray-300 rounded"
                            @input="updateElement"
                        />
                    </div>
                </div>

                <div v-if="['select', 'radio'].includes(selectedElement.type)" class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Options</label>
                    <div v-for="(option, i) in selectedElement.props.options" :key="i" class="flex space-x-2">
                        <input
                            v-model="selectedElement.props.options[i]"
                            class="flex-1 p-2 border border-gray-300 rounded"
                            @input="updateElement"
                        />
                        <button
                            @click="selectedElement.props.options.splice(i, 1); updateElement()"
                            class="text-red-500 hover:text-red-700"
                        >
                            ×
                        </button>
                    </div>
                    <button
                        @click="selectedElement.props.options.push('New Option'); updateElement()"
                        class="w-full p-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                    >
                        Add Option
                    </button>
                </div>

                <div v-if="selectedElement.type === 'button'" class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Button Text</label>
                    <input
                        v-model="selectedElement.props.text"
                        class="w-full p-2 border border-gray-300 rounded"
                        @input="updateElement"
                    />

                    <label class="block text-sm font-medium text-gray-700">Button Variant</label>
                    <select
                        v-model="selectedElement.props.variant"
                        class="w-full p-2 border border-gray-300 rounded"
                        @change="updateElement"
                    >
                        <option value="primary">Primary</option>
                        <option value="secondary">Secondary</option>
                        <option value="success">Success</option>
                        <option value="danger">Danger</option>
                    </select>
                </div>

                <div v-if="!['button', 'checkbox'].includes(selectedElement.type)" class="flex items-center space-x-2">
                    <input
                        type="checkbox"
                        id="required"
                        v-model="selectedElement.props.required"
                        @change="updateElement"
                    />
                    <label for="required" class="text-sm font-medium text-gray-700">Required</label>
                </div>
            </div>
            <div v-else class="text-gray-500 text-center py-8">
                Select an element to edit its properties
            </div>
        </div>

        <!-- Code preview -->
        <div class="col-span-12 mt-6">
            <div class="bg-gray-800 text-white p-4 rounded-lg">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold">Angular Code</h2>
                    <button
                        @click="navigator.clipboard.writeText(angularCode)"
                        class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
                    >
                        Copy Code
                    </button>
                </div>
                <pre class="text-green-400 overflow-x-auto whitespace-pre-wrap">{{ angularCode }}</pre>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Add any component-specific styles here */
</style>
