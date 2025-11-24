@extends('layouts.dashboard')

@section('title', 'Messages - WhatsApp Business API')

@section('content')
<div class="h-[calc(100vh-8rem)]" x-data="messagesManager()">
    <div class="flex h-full bg-white rounded-xl shadow-lg overflow-hidden">
        <!-- Contacts Sidebar -->
        <div class="w-full md:w-1/3 border-r border-gray-200 flex flex-col">
            <!-- Search -->
            <div class="p-4 border-b border-gray-200">
                <div class="relative">
                    <input
                        type="text"
                        x-model="contactSearch"
                        @input="filterContactList"
                        placeholder="Search contacts..."
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                </div>
            </div>

            <!-- Contacts List -->
            <div class="flex-1 overflow-y-auto">
                <template x-for="contact in filteredContactList" :key="contact.id">
                    <div
                        @click="selectContact(contact)"
                        class="flex items-center space-x-3 p-4 hover:bg-gray-50 cursor-pointer transition border-b border-gray-100"
                        :class="{'bg-green-50': selectedContact?.id === contact.id}">
                        <div class="relative flex-shrink-0">
                            <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                                <span x-text="contact.name ? contact.name.charAt(0).toUpperCase() : '?'">?</span>
                            </div>
                            <div x-show="contact.unread_count > 0" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold" x-text="contact.unread_count">0</div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold text-gray-900 truncate" x-text="contact.name || 'Unknown'">Unknown</p>
                                <p class="text-xs text-gray-500" x-text="formatTime(contact.last_message_at)">-</p>
                            </div>
                            <p class="text-xs text-gray-600 truncate" x-text="contact.phone_number">-</p>
                        </div>
                    </div>
                </template>

                <div x-show="filteredContactList.length === 0" class="text-center py-12 text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-4"></i>
                    <p>No contacts found</p>
                </div>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="flex-1 flex flex-col" x-show="selectedContact">
            <!-- Chat Header -->
            <div class="p-4 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-green-400 to-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                            <span x-text="selectedContact?.name ? selectedContact.name.charAt(0).toUpperCase() : '?'">?</span>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900" x-text="selectedContact?.name || 'Unknown'">Unknown</p>
                            <p class="text-xs text-gray-600" x-text="selectedContact?.phone_number">-</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button @click="refreshMessages" class="p-2 text-gray-600 hover:text-gray-900 transition">
                            <i class="fas fa-sync-alt" :class="{'fa-spin': loadingMessages}"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Messages List -->
            <div class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50" x-ref="messagesContainer">
                <template x-for="(message, index) in messages" :key="message?.id || `msg-${index}`">
                    <div x-show="message && message.id" class="flex" :class="message?.direction === 'outgoing' ? 'justify-end' : 'justify-start'">
                        <div class="max-w-xs md:max-w-md lg:max-w-lg">
                            <!-- Message Bubble -->
                            <div class="rounded-lg p-3 shadow"
                                 :class="message?.direction === 'outgoing' ? 'bg-green-500 text-white' : 'bg-white text-gray-900'">

                                <!-- Text Message -->
                                <template x-if="message?.type === 'text'">
                                    <p class="text-sm whitespace-pre-wrap" x-text="message?.content || message?.body"></p>
                                </template>

                                <!-- Template Message -->
                                <template x-if="message?.type === 'template'">
                                    <div class="text-sm">
                                        <i class="fas fa-file-alt mr-1"></i>
                                        <span x-text="message?.body || 'Template message'"></span>
                                    </div>
                                </template>

                                <!-- Image Message -->
                                <template x-if="message?.type === 'image'">
                                    <div>
                                        <img :src="message?.media_url" class="rounded-lg max-w-full mb-2" alt="Image">
                                        <p x-show="message?.caption" class="text-sm" x-text="message?.caption"></p>
                                    </div>
                                </template>

                                <!-- Document Message -->
                                <template x-if="message?.type === 'document'">
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-file text-2xl"></i>
                                        <div>
                                            <p class="text-sm font-medium" x-text="message?.filename || 'Document'"></p>
                                            <a :href="message?.media_url" target="_blank" class="text-xs underline">Download</a>
                                        </div>
                                    </div>
                                </template>

                                <!-- Audio Message -->
                                <template x-if="message?.type === 'audio'">
                                    <audio controls class="max-w-full">
                                        <source :src="message?.media_url" type="audio/mpeg">
                                    </audio>
                                </template>

                                <!-- Video Message -->
                                <template x-if="message?.type === 'video'">
                                    <video controls class="rounded-lg max-w-full">
                                        <source :src="message?.media_url" type="video/mp4">
                                    </video>
                                </template>

                                <!-- Location Message -->
                                <template x-if="message?.type === 'location'">
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-map-marker-alt text-2xl"></i>
                                        <div>
                                            <p class="text-sm font-medium">Location Shared</p>
                                            <p class="text-xs" x-text="`${message?.latitude}, ${message?.longitude}`"></p>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- Message Meta -->
                            <div class="flex items-center justify-between mt-1 px-2">
                                <p class="text-xs text-gray-500" x-text="formatTime(message?.created_at)">-</p>
                                <div x-show="message?.direction === 'outgoing'" class="flex items-center space-x-1">
                                    <span class="text-xs capitalize"
                                          :class="{
                                              'text-gray-500': message?.status === 'sent',
                                              'text-blue-600': message?.status === 'delivered',
                                              'text-green-600': message?.status === 'read',
                                              'text-red-600': message?.status === 'failed'
                                          }"
                                          x-text="message?.status">-</span>
                                    <i class="text-xs"
                                       :class="{
                                           'fas fa-check text-gray-500': message?.status === 'sent',
                                           'fas fa-check-double text-blue-600': message?.status === 'delivered',
                                           'fas fa-check-double text-green-600': message?.status === 'read',
                                           'fas fa-exclamation-triangle text-red-600': message?.status === 'failed'
                                       }"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <div x-show="messages.length === 0" class="text-center py-12 text-gray-500">
                    <i class="fas fa-comments text-4xl mb-4"></i>
                    <p>No messages yet. Start a conversation!</p>
                </div>
            </div>

            <!-- Message Input -->
            <div class="p-4 border-t border-gray-200 bg-white">
                <form @submit.prevent="sendTextMessage" class="space-y-3">
                    <div class="flex items-end space-x-2">
                        <!-- File Upload Button -->
                        <div class="flex space-x-1">
                            <button type="button" @click="$refs.fileInput.click()" class="p-3 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">
                                <i class="fas fa-paperclip"></i>
                            </button>
                            <input type="file" x-ref="fileInput" @change="handleFileUpload" class="hidden">

                            <button type="button" @click="showTemplateModal = true" class="p-3 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">
                                <i class="fas fa-file-alt"></i>
                            </button>
                        </div>

                        <!-- Message Input -->
                        <textarea
                            x-model="newMessage"
                            @keydown.enter.prevent="$event.shiftKey ? (newMessage += '\n') : sendTextMessage()"
                            placeholder="Type a message..."
                            rows="2"
                            class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 resize-none"></textarea>

                        <!-- Send Button -->
                        <button
                            type="submit"
                            :disabled="!newMessage.trim() || sending"
                            class="p-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas" :class="sending ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Empty State -->
        <div x-show="!selectedContact" class="flex-1 flex items-center justify-center bg-gray-50">
            <div class="text-center">
                <i class="fas fa-comment-dots text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Select a Contact</h3>
                <p class="text-gray-500">Choose a contact from the list to start messaging</p>
            </div>
        </div>
    </div>

    <!-- Template Modal -->
    <div x-show="showTemplateModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="showTemplateModal = false">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="showTemplateModal = false"></div>

            <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[80vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-2xl font-bold text-gray-900">Select Template</h3>
                        <button @click="showTemplateModal = false" class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <template x-for="template in templates" :key="template.id">
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-green-500 cursor-pointer transition" @click="sendTemplate(template)">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-semibold text-gray-900" x-text="template.name"></h4>
                                    <span class="text-xs px-2 py-1 rounded-full" :class="template.status === 'APPROVED' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'" x-text="template.status"></span>
                                </div>
                                <p class="text-sm text-gray-600 mb-2" x-text="template.category"></p>
                                <p class="text-sm text-gray-700" x-text="template.body || template.header"></p>
                            </div>
                        </template>

                        <div x-show="templates.length === 0" class="text-center py-8 text-gray-500">
                            <p>No approved templates available</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function messagesManager() {
        return {
            API_BASE_URL: window.location.origin + '/api',
            contacts: [],
            filteredContactList: [],
            selectedContact: null,
            messages: [],
            templates: [],
            newMessage: '',
            contactSearch: '',
            loadingMessages: false,
            sending: false,
            showTemplateModal: false,

            async init() {
                // Initialize messages as empty array to prevent undefined errors
                this.messages = [];
                await this.fetchContacts();
                await this.fetchTemplates();
                this.checkUrlParams();
                this.listenForUpdates();

                // Also listen to custom event as backup
                window.addEventListener('whatsapp-message-received', (event) => {
                    console.log('📩 Messages page - Received via custom event:', event.detail);
                    this.handleIncomingMessage(event.detail);
                });
            },

            handleIncomingMessage(e) {
                console.log('🔔 Handling incoming message:', e);

                // Add message to current chat if it's the same contact
                if (this.selectedContact && e.contact && e.contact.id === this.selectedContact.id) {
                    console.log('✅ Contact matches! Adding message to chat');

                    // Validate message object
                    if (e.message && e.message.id) {
                        // Check if message already exists to avoid duplicates
                        if (!this.messages.find(m => m && m.id === e.message.id)) {
                            this.messages.push(e.message);
                            this.scrollToBottom();
                            console.log('✅ Message added to chat, total messages:', this.messages.length);
                        } else {
                            console.log('⚠️ Message already exists, skipping');
                        }
                    } else {
                        console.error('❌ Invalid message object received:', e.message);
                    }
                } else {
                    console.log('⚠️ Contact does not match current chat or no contact selected');
                }

                // Update contacts list to show new message indicator
                this.fetchContacts();
            },

            async fetchContacts() {
                try {
                    const token = localStorage.getItem('token');
                    const response = await fetch(`${this.API_BASE_URL}/whatsapp/contacts`, {
                        headers: { 'Authorization': `Bearer ${token}` }
                    });
                    const data = await response.json();
                    this.contacts = data.data || [];
                    this.filteredContactList = this.contacts;
                } catch (error) {
                    console.error('Error fetching contacts:', error);
                }
            },

            async fetchTemplates() {
                try {
                    const token = localStorage.getItem('token');
                    const response = await fetch(`${this.API_BASE_URL}/whatsapp/templates`, {
                        headers: { 'Authorization': `Bearer ${token}` }
                    });
                    const data = await response.json();
                    this.templates = (data.data || []).filter(t => t.status === 'APPROVED');
                } catch (error) {
                    console.error('Error fetching templates:', error);
                }
            },

            checkUrlParams() {
                const urlParams = new URLSearchParams(window.location.search);
                const contactId = urlParams.get('contact');
                if (contactId) {
                    const contact = this.contacts.find(c => c.id == contactId);
                    if (contact) {
                        this.selectContact(contact);
                    }
                }
            },

            filterContactList() {
                if (!this.contactSearch) {
                    this.filteredContactList = this.contacts;
                    return;
                }

                const query = this.contactSearch.toLowerCase();
                this.filteredContactList = this.contacts.filter(contact =>
                    (contact.name && contact.name.toLowerCase().includes(query)) ||
                    (contact.phone_number && contact.phone_number.includes(query))
                );
            },

            async selectContact(contact) {
                this.selectedContact = contact;
                await this.fetchMessages();
                await this.markContactMessagesAsRead(contact.id);
                this.scrollToBottom();
            },

            async markContactMessagesAsRead(contactId) {
                try {
                    const token = localStorage.getItem('token');
                    await fetch(`${this.API_BASE_URL}/whatsapp/contacts/${contactId}/mark-read`, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json'
                        }
                    });

                    // Update contact's unread_count to 0 in local state
                    const contactInList = this.contacts.find(c => c.id === contactId);
                    if (contactInList) {
                        contactInList.unread_count = 0;
                    }

                    // Also update in filtered list
                    const contactInFiltered = this.filteredContactList.find(c => c.id === contactId);
                    if (contactInFiltered) {
                        contactInFiltered.unread_count = 0;
                    }

                    console.log('✅ Messages marked as read for contact:', contactId);
                } catch (error) {
                    console.error('Error marking messages as read:', error);
                }
            },

            async fetchMessages() {
                if (!this.selectedContact) {
                    this.messages = [];
                    return;
                }

                this.loadingMessages = true;
                try {
                    const token = localStorage.getItem('token');
                    const response = await fetch(`${this.API_BASE_URL}/whatsapp/contacts/${this.selectedContact.id}/messages`, {
                        headers: { 'Authorization': `Bearer ${token}` }
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();

                    // Ensure we always have an array, filter out null/undefined, and ensure unique IDs
                    let rawMessages = Array.isArray(data.data) ? data.data : [];

                    // Filter out null/undefined and messages without ID
                    let validMessages = rawMessages.filter(m => m != null && m !== undefined && m.id);

                    // Remove duplicates by ID (keep first occurrence)
                    const seenIds = new Set();
                    this.messages = validMessages.filter(m => {
                        if (seenIds.has(m.id)) {
                            return false;
                        }
                        seenIds.add(m.id);
                        return true;
                    });

                    console.log(`Fetched ${rawMessages.length} messages, filtered to ${this.messages.length} valid unique messages`);
                    this.scrollToBottom();
                } catch (error) {
                    console.error('Error fetching messages:', error);
                    this.messages = [];
                } finally {
                    this.loadingMessages = false;
                }
            },

            async refreshMessages() {
                await this.fetchMessages();
            },

            async sendTextMessage() {
                if (!this.newMessage.trim() || !this.selectedContact) return;

                this.sending = true;
                try {
                    const token = localStorage.getItem('token');

                    const payload = {
                        to: this.selectedContact.wa_id,
                        message: this.newMessage
                    };

                    console.log('Sending text message:', payload);

                    const response = await fetch(`${this.API_BASE_URL}/whatsapp/send/text`, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    if (response.ok) {
                        this.newMessage = '';
                        await this.fetchMessages();
                    } else {
                        const error = await response.json();
                        console.error('Failed to send message:', error);
                        alert('Failed to send message: ' + (error.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error sending message:', error);
                    alert('Error sending message: ' + error.message);
                } finally {
                    this.sending = false;
                }
            },

            async handleFileUpload(event) {
                const file = event.target.files[0];
                if (!file || !this.selectedContact) return;

                const formData = new FormData();
                formData.append('to', this.selectedContact.wa_id);
                formData.append('file', file);

                try {
                    const token = localStorage.getItem('token');
                    const endpoint = file.type.startsWith('image/') ? `${this.API_BASE_URL}/whatsapp/send/image` : `${this.API_BASE_URL}/whatsapp/send/document`;

                    const response = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${token}`
                        },
                        body: formData
                    });

                    if (response.ok) {
                        await this.fetchMessages();
                    }
                } catch (error) {
                    console.error('Error uploading file:', error);
                }
            },

            async sendTemplate(template) {
                if (!this.selectedContact) return;

                try {
                    const token = localStorage.getItem('token');

                    const payload = {
                        to: this.selectedContact.wa_id,
                        template_name: template.name,
                        language: template.language
                    };

                    console.log('Sending template:', payload);

                    const response = await fetch(`${this.API_BASE_URL}/whatsapp/send/template`, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    if (response.ok) {
                        this.showTemplateModal = false;
                        await this.fetchMessages();
                    }
                } catch (error) {
                    console.error('Error sending template:', error);
                }
            },

            scrollToBottom() {
                this.$nextTick(() => {
                    const container = this.$refs.messagesContainer;
                    if (container) {
                        container.scrollTop = container.scrollHeight;
                    }
                });
            },

            formatTime(timestamp) {
                const date = new Date(timestamp);
                const now = new Date();
                const isToday = date.toDateString() === now.toDateString();

                if (isToday) {
                    return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                }
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            },

            listenForUpdates() {
                console.log('📡 Messages page - Setting up message listener');

                // Listen to custom event dispatched by echo-setup.js
                // This ensures we receive messages regardless of when Echo initializes
                console.log('✅ Messages page - Listening for whatsapp-message-received events');
            }
        }
    }
</script>
@endsection
