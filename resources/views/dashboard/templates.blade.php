@extends('layouts.app')

@section('title', 'Templates - QashierWise')

@section('content')
<!-- Toast Notification Container -->
<div id="toast-container" class="fixed top-4 right-4 z-[100] flex flex-col gap-2" x-data="toastManager()">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-show="toast.visible"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-8"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-8"
             class="flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg min-w-[300px] max-w-[400px]"
             :class="{
                 'bg-emerald-50 border border-emerald-200 text-emerald-800': toast.type === 'success',
                 'bg-red-50 border border-red-200 text-red-800': toast.type === 'error',
                 'bg-amber-50 border border-amber-200 text-amber-800': toast.type === 'warning',
                 'bg-blue-50 border border-blue-200 text-blue-800': toast.type === 'info'
             }">
            <div class="flex-shrink-0">
                <i class="fas text-lg"
                   :class="{
                       'fa-check-circle text-emerald-500': toast.type === 'success',
                       'fa-exclamation-circle text-red-500': toast.type === 'error',
                       'fa-exclamation-triangle text-amber-500': toast.type === 'warning',
                       'fa-info-circle text-blue-500': toast.type === 'info'
                   }"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium" x-text="toast.message"></p>
            </div>
            <button @click="removeToast(toast.id)" class="flex-shrink-0 p-1 rounded hover:bg-black/5 transition-colors">
                <i class="fas fa-times text-xs opacity-60"></i>
            </button>
        </div>
    </template>
</div>

<div x-data="templatesApp()" class="min-h-screen flex bg-[hsl(var(--muted)/0.4)]">
    <!-- Sidebar -->
    @include('components.dashboard-sidebar', ['activePage' => 'templates'])

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen">
        <!-- Header -->
        @include('components.dashboard-header', ['title' => 'Templates', 'description' => 'Manage your WhatsApp message templates'])

        <!-- Page Content -->
        <main class="flex-1 p-6">
            <div class="max-w-7xl mx-auto space-y-6" x-data="templatesManager()">
                <!-- Header with Create Button -->
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Message Templates</h2>
                        <p class="text-sm text-[hsl(var(--muted-foreground))]">Create and manage your WhatsApp message templates</p>
                    </div>
                    <div class="flex gap-2">
                        @if(config('app.debug'))
                        <button @click="refreshTemplates()" :disabled="refreshing" class="btn btn-outline btn-md">
                            <i class="fas" :class="refreshing ? 'fa-spinner animate-spin' : 'fa-sync-alt'"></i>
                            <span x-text="refreshing ? 'Syncing...' : 'Sync from Meta'"></span>
                        </button>
                        @endif
                        <button @click="openCreateModal()" class="btn btn-primary btn-md">
                            <i class="fas fa-plus mr-2"></i>
                            Create Template
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card p-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5 block">Status</label>
                            <select x-model="filters.status" @change="filterTemplates" class="input w-full">
                                <option value="">All Statuses</option>
                                <option value="APPROVED">Approved</option>
                                <option value="PENDING">Pending</option>
                                <option value="REJECTED">Rejected</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5 block">Category</label>
                            <select x-model="filters.category" @change="filterTemplates" class="input w-full">
                                <option value="">All Categories</option>
                                <option value="MARKETING">Marketing</option>
                                <option value="UTILITY">Utility</option>
                                <option value="AUTHENTICATION">Authentication</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5 block">Search</label>
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] text-sm pointer-events-none"></i>
                                <input type="text" x-model="filters.search" @input="filterTemplates" placeholder="Search templates..." class="input pl-10 w-full">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Templates Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Loading -->
                    <template x-if="loading">
                        <template x-for="i in 6" :key="'skeleton-'+i">
                            <div class="card p-5">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="skeleton h-5 w-32"></div>
                                    <div class="skeleton h-5 w-20 rounded-full"></div>
                                </div>
                                <div class="skeleton h-24 w-full rounded-lg mb-4"></div>
                                <div class="flex gap-2">
                                    <div class="skeleton h-9 flex-1 rounded-md"></div>
                                    <div class="skeleton h-9 flex-1 rounded-md"></div>
                                </div>
                            </div>
                        </template>
                    </template>

                    <!-- Templates -->
                    <template x-if="!loading">
                        <template x-for="template in filteredTemplates" :key="template.id">
                            <div class="card p-5 hover:shadow-md transition-shadow">
                                <!-- Header -->
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold truncate" x-text="template.name"></h3>
                                        <div class="flex items-center gap-2 mt-1.5">
                                            <span class="badge text-xs"
                                                :class="{
                                                    'bg-emerald-100 text-emerald-700': template.status === 'APPROVED',
                                                    'bg-amber-100 text-amber-700': template.status === 'PENDING',
                                                    'bg-red-100 text-red-700': template.status === 'REJECTED'
                                                }">
                                                <i class="mr-1 text-[10px]" :class="{
                                                    'fas fa-check-circle': template.status === 'APPROVED',
                                                    'fas fa-clock': template.status === 'PENDING',
                                                    'fas fa-times-circle': template.status === 'REJECTED'
                                                }"></i>
                                                <span x-text="template.status"></span>
                                            </span>
                                            <span class="badge badge-secondary text-xs" x-text="template.category"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Preview -->
                                <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-3 mb-4 min-h-[100px] text-sm">
                                    <div x-show="template.header" class="mb-2">
                                        <template x-if="template.header_type === 'TEXT'">
                                            <p class="font-medium" x-text="template.header"></p>
                                        </template>
                                        <template x-if="['IMAGE', 'VIDEO', 'DOCUMENT'].includes(template.header_type)">
                                            <div class="flex items-center gap-2 text-[hsl(var(--muted-foreground))]">
                                                <i :class="{
                                                    'fas fa-image': template.header_type === 'IMAGE',
                                                    'fas fa-video': template.header_type === 'VIDEO',
                                                    'fas fa-file': template.header_type === 'DOCUMENT'
                                                }"></i>
                                                <span class="text-xs" x-text="template.header_type + ' Header'"></span>
                                            </div>
                                        </template>
                                    </div>
                                    <p x-show="template.body" class="text-[hsl(var(--muted-foreground))] line-clamp-3" x-text="template.body"></p>
                                    <p x-show="template.footer" class="text-xs text-[hsl(var(--muted-foreground))] italic mt-2" x-text="template.footer"></p>
                                </div>

                                <!-- Meta -->
                                <div class="flex items-center gap-3 text-xs text-[hsl(var(--muted-foreground))] mb-4">
                                    <span class="flex items-center gap-1">
                                        <i class="fas fa-language"></i>
                                        <span x-text="template.language"></span>
                                    </span>
                                </div>

                                <!-- Actions -->
                                <div class="flex gap-2">
                                    <button @click="viewTemplate(template)" class="btn btn-outline btn-md flex-1">
                                        <i class="fas fa-eye"></i>
                                        <span>View</span>
                                    </button>
                                    <button @click="openEditModal(template)" class="btn btn-outline btn-md flex-1">
                                        <i class="fas fa-edit"></i>
                                        <span>Edit</span>
                                    </button>
                                    <button @click="openDeleteModal(template)" class="btn btn-outline btn-md text-red-600 hover:bg-red-50 hover:border-red-300">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button x-show="template.status === 'APPROVED'" @click="sendTemplateModal(template)" class="btn btn-primary btn-md flex-1">
                                        <i class="fas fa-paper-plane"></i>
                                        <span>Send</span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </template>
                </div>

                <!-- Empty State -->
                <div x-show="!loading && filteredTemplates.length === 0" class="card">
                    <div class="empty-state py-16">
                        <div class="empty-state-icon">
                            <i class="fas fa-file-alt text-2xl"></i>
                        </div>
                        <h3 class="font-semibold mt-4">No templates found</h3>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] mt-1">Your message templates will appear here.</p>
                    </div>
                </div>

                <!-- View Modal -->
                <div x-show="showViewModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div x-show="showViewModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-black/50" @click="closeViewModal"></div>
                    <div x-show="showViewModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="card relative w-full max-w-lg max-h-[80vh] overflow-y-auto scroll-area">
                        <div class="p-6 border-b border-[hsl(var(--border))] flex items-center justify-between">
                            <h3 class="text-lg font-semibold">Template Details</h3>
                            <button @click="closeViewModal" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="p-6" x-show="selectedTemplate">
                            <h4 class="font-semibold text-lg mb-3" x-text="selectedTemplate?.name"></h4>
                            <div class="flex flex-wrap gap-2 mb-4">
                                <span class="badge" :class="{'bg-emerald-100 text-emerald-700': selectedTemplate?.status === 'APPROVED', 'bg-amber-100 text-amber-700': selectedTemplate?.status === 'PENDING', 'bg-red-100 text-red-700': selectedTemplate?.status === 'REJECTED'}" x-text="selectedTemplate?.status"></span>
                                <span class="badge badge-secondary" x-text="selectedTemplate?.category"></span>
                                <span class="badge badge-outline" x-text="selectedTemplate?.language"></span>
                            </div>
                            <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4 space-y-3">
                                <div x-show="selectedTemplate?.header">
                                    <p class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1">Header</p>
                                    <p x-text="selectedTemplate?.header || `[${selectedTemplate?.header_type}]`"></p>
                                </div>
                                <div x-show="selectedTemplate?.body">
                                    <p class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1">Body</p>
                                    <p class="whitespace-pre-wrap" x-text="selectedTemplate?.body"></p>
                                </div>
                                <div x-show="selectedTemplate?.footer">
                                    <p class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1">Footer</p>
                                    <p class="text-sm italic" x-text="selectedTemplate?.footer"></p>
                                </div>
                                <div x-show="selectedTemplate?.buttons?.length > 0">
                                    <p class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-2">Buttons</p>
                                    <div class="space-y-1">
                                        <template x-for="btn in selectedTemplate?.buttons" :key="btn.text">
                                            <div class="bg-white border border-[hsl(var(--border))] rounded-md px-3 py-2 text-sm" x-text="btn.text"></div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Send Modal -->
                <div x-show="showSendModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div x-show="showSendModal" x-transition class="fixed inset-0 bg-black/50" @click="closeSendModal"></div>
                    <div x-show="showSendModal" x-transition class="card relative w-full max-w-md">
                        <div class="p-6 border-b border-[hsl(var(--border))] flex items-center justify-between">
                            <h3 class="text-lg font-semibold">Send Template</h3>
                            <button @click="closeSendModal" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
                        </div>
                        <form @submit.prevent="sendTemplate" class="p-6">
                            <div class="mb-4">
                                <label class="text-sm font-medium mb-1.5 block">Select Contact</label>
                                <select x-model="sendForm.contactId" required class="input w-full">
                                    <option value="">Choose a contact...</option>
                                    <template x-for="contact in contacts" :key="contact.id">
                                        <option :value="contact.id" x-text="`${contact.name} (${contact.phone_number})`"></option>
                                    </template>
                                </select>
                            </div>
                            <div x-show="selectedTemplate" class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-3 mb-4">
                                <p class="text-xs text-[hsl(var(--muted-foreground))] mb-1">Template</p>
                                <p class="font-medium text-sm" x-text="selectedTemplate?.name"></p>
                            </div>
                            <div class="flex gap-3">
                                <button type="button" @click="closeSendModal" class="btn btn-outline btn-md flex-1">Cancel</button>
                                <button type="submit" :disabled="sending" class="btn btn-primary btn-md flex-1">
                                    <i class="fas" :class="sending ? 'fa-spinner animate-spin' : 'fa-paper-plane'"></i>
                                    <span x-text="sending ? 'Sending...' : 'Send'"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Create Template Modal -->
                <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div x-show="showCreateModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-black/50" @click="!creating && closeCreateModal()"></div>
                    <div x-show="showCreateModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="card relative w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                        <!-- Loading Overlay -->
                        <div x-show="creating" x-transition class="absolute inset-0 bg-white/80 z-10 flex items-center justify-center">
                            <div class="text-center">
                                <i class="fas fa-spinner fa-spin text-3xl text-primary mb-3"></i>
                                <p class="text-sm font-medium text-[hsl(var(--muted-foreground))]">Creating template...</p>
                            </div>
                        </div>
                        
                        <!-- Modal Header -->
                        <div class="p-6 border-b border-[hsl(var(--border))] flex items-center justify-between flex-shrink-0">
                            <h3 class="text-lg font-semibold">Create New Template</h3>
                            <button @click="closeCreateModal" :disabled="creating" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
                        </div>
                        
                        <!-- Modal Body -->
                        <div class="flex-1 overflow-y-auto">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 p-6">
                                <!-- Form Section -->
                                <div class="space-y-5">
                                    <!-- Basic Info -->
                                    <div class="space-y-4">
                                        <h4 class="font-medium text-sm text-[hsl(var(--muted-foreground))] uppercase tracking-wide">Basic Information</h4>
                                        
                                        <!-- Template Name -->
                                        <div>
                                            <label class="text-sm font-medium mb-1.5 block">Template Name <span class="text-red-500">*</span></label>
                                            <input type="text" x-model="createForm.name" @input="validateName" placeholder="e.g., order_confirmation" class="input w-full" :class="{'border-red-500': errors.name}">
                                            <p x-show="errors.name" x-text="errors.name" class="text-xs text-red-500 mt-1"></p>
                                            <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">Only lowercase letters, numbers, and underscores</p>
                                        </div>

                                        <!-- Category -->
                                        <div>
                                            <label class="text-sm font-medium mb-1.5 block">Category <span class="text-red-500">*</span></label>
                                            <select x-model="createForm.category" class="input w-full" :class="{'border-red-500': errors.category}">
                                                <option value="">Select category...</option>
                                                <option value="MARKETING">Marketing</option>
                                                <option value="UTILITY">Utility</option>
                                                <option value="AUTHENTICATION">Authentication</option>
                                            </select>
                                            <p x-show="errors.category" x-text="errors.category" class="text-xs text-red-500 mt-1"></p>
                                        </div>

                                        <!-- Language -->
                                        <div>
                                            <label class="text-sm font-medium mb-1.5 block">Language <span class="text-red-500">*</span></label>
                                            <select x-model="createForm.language" class="input w-full" :class="{'border-red-500': errors.language}">
                                                <option value="">Select language...</option>
                                                <option value="en">English</option>
                                                <option value="en_US">English (US)</option>
                                                <option value="en_GB">English (UK)</option>
                                                <option value="id">Indonesian</option>
                                                <option value="ms">Malay</option>
                                                <option value="zh_CN">Chinese (Simplified)</option>
                                                <option value="zh_TW">Chinese (Traditional)</option>
                                                <option value="ja">Japanese</option>
                                                <option value="ko">Korean</option>
                                                <option value="th">Thai</option>
                                                <option value="vi">Vietnamese</option>
                                                <option value="es">Spanish</option>
                                                <option value="pt_BR">Portuguese (Brazil)</option>
                                                <option value="fr">French</option>
                                                <option value="de">German</option>
                                                <option value="it">Italian</option>
                                                <option value="ar">Arabic</option>
                                                <option value="hi">Hindi</option>
                                            </select>
                                            <p x-show="errors.language" x-text="errors.language" class="text-xs text-red-500 mt-1"></p>
                                        </div>
                                    </div>

                                    <!-- Header Component -->
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <h4 class="font-medium text-sm text-[hsl(var(--muted-foreground))] uppercase tracking-wide">Header (Optional)</h4>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" x-model="createForm.hasHeader" class="rounded border-[hsl(var(--border))]">
                                                <span class="text-sm">Enable</span>
                                            </label>
                                        </div>
                                        
                                        <div x-show="createForm.hasHeader" x-collapse class="space-y-3">
                                            <div>
                                                <label class="text-sm font-medium mb-1.5 block">Header Type</label>
                                                <select x-model="createForm.header.type" class="input w-full">
                                                    <option value="TEXT">Text</option>
                                                    <option value="IMAGE">Image</option>
                                                    <option value="VIDEO">Video</option>
                                                    <option value="DOCUMENT">Document</option>
                                                </select>
                                            </div>
                                            
                                            <div x-show="createForm.header.type === 'TEXT'">
                                                <label class="text-sm font-medium mb-1.5 block">Header Text</label>
                                                <input type="text" x-model="createForm.header.text" placeholder="Enter header text..." class="input w-full" maxlength="60">
                                                <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1"><span x-text="(createForm.header.text || '').length"></span>/60 characters</p>
                                            </div>
                                            
                                            <div x-show="['IMAGE', 'VIDEO', 'DOCUMENT'].includes(createForm.header.type)">
                                                <label class="text-sm font-medium mb-1.5 block">Example Media URL</label>
                                                <input type="url" x-model="createForm.header.example" placeholder="https://example.com/media.jpg" class="input w-full">
                                                <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">Provide an example URL for template approval</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Body Component -->
                                    <div class="space-y-4">
                                        <h4 class="font-medium text-sm text-[hsl(var(--muted-foreground))] uppercase tracking-wide">Body <span class="text-red-500">*</span></h4>
                                        
                                        <div>
                                            <label class="text-sm font-medium mb-1.5 block">Body Text</label>
                                            <textarea x-model="createForm.body.text" @input="validateBody" placeholder="Enter your message body. Use {{1}}, {{2}}, etc. for variables..." class="input w-full min-h-[120px] resize-y" :class="{'border-red-500': errors.body}" maxlength="1024"></textarea>
                                            <div class="flex justify-between mt-1">
                                                <p x-show="errors.body" x-text="errors.body" class="text-xs text-red-500"></p>
                                                <p x-show="warnings.body" x-text="warnings.body" class="text-xs text-amber-500"></p>
                                                <p class="text-xs text-[hsl(var(--muted-foreground))]"><span x-text="(createForm.body.text || '').length"></span>/1024 characters</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Footer Component -->
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <h4 class="font-medium text-sm text-[hsl(var(--muted-foreground))] uppercase tracking-wide">Footer (Optional)</h4>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" x-model="createForm.hasFooter" class="rounded border-[hsl(var(--border))]">
                                                <span class="text-sm">Enable</span>
                                            </label>
                                        </div>
                                        
                                        <div x-show="createForm.hasFooter" x-collapse>
                                            <label class="text-sm font-medium mb-1.5 block">Footer Text</label>
                                            <input type="text" x-model="createForm.footer.text" @input="validateFooter" placeholder="Enter footer text..." class="input w-full" :class="{'border-red-500': errors.footer}" maxlength="60">
                                            <div class="flex justify-between mt-1">
                                                <p x-show="errors.footer" x-text="errors.footer" class="text-xs text-red-500"></p>
                                                <p class="text-xs text-[hsl(var(--muted-foreground))]"><span x-text="(createForm.footer.text || '').length"></span>/60 characters</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Buttons Component -->
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <h4 class="font-medium text-sm text-[hsl(var(--muted-foreground))] uppercase tracking-wide">Buttons (Optional)</h4>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" x-model="createForm.hasButtons" class="rounded border-[hsl(var(--border))]">
                                                <span class="text-sm">Enable</span>
                                            </label>
                                        </div>
                                        
                                        <div x-show="createForm.hasButtons" x-collapse class="space-y-3">
                                            <div>
                                                <label class="text-sm font-medium mb-1.5 block">Button Type</label>
                                                <select x-model="createForm.buttonType" @change="resetButtons" class="input w-full">
                                                    <option value="QUICK_REPLY">Quick Reply (max 10)</option>
                                                    <option value="CTA">Call to Action (max 2)</option>
                                                </select>
                                            </div>
                                            
                                            <!-- Quick Reply Buttons -->
                                            <div x-show="createForm.buttonType === 'QUICK_REPLY'" class="space-y-2">
                                                <template x-for="(btn, index) in createForm.buttons" :key="index">
                                                    <div class="flex gap-2">
                                                        <input type="text" x-model="btn.text" :placeholder="'Button ' + (index + 1) + ' text'" class="input flex-1" maxlength="25">
                                                        <button type="button" @click="removeButton(index)" class="btn btn-ghost btn-icon text-red-500" x-show="createForm.buttons.length > 1">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </template>
                                                <button type="button" @click="addQuickReplyButton" x-show="createForm.buttons.length < 10" class="btn btn-outline btn-sm w-full">
                                                    <i class="fas fa-plus mr-2"></i>Add Quick Reply Button
                                                </button>
                                            </div>
                                            
                                            <!-- CTA Buttons -->
                                            <div x-show="createForm.buttonType === 'CTA'" class="space-y-3">
                                                <template x-for="(btn, index) in createForm.buttons" :key="index">
                                                    <div class="p-3 bg-[hsl(var(--muted)/0.3)] rounded-lg space-y-2">
                                                        <div class="flex items-center justify-between">
                                                            <span class="text-sm font-medium">Button <span x-text="index + 1"></span></span>
                                                            <button type="button" @click="removeButton(index)" class="btn btn-ghost btn-icon btn-sm text-red-500" x-show="createForm.buttons.length > 1">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                        <select x-model="btn.type" class="input w-full">
                                                            <option value="URL">URL</option>
                                                            <option value="PHONE_NUMBER">Phone Number</option>
                                                        </select>
                                                        <input type="text" x-model="btn.text" placeholder="Button text" class="input w-full" maxlength="25">
                                                        <input x-show="btn.type === 'URL'" type="url" x-model="btn.url" placeholder="https://example.com" class="input w-full">
                                                        <input x-show="btn.type === 'PHONE_NUMBER'" type="tel" x-model="btn.phone_number" placeholder="+1234567890" class="input w-full">
                                                    </div>
                                                </template>
                                                <button type="button" @click="addCtaButton" x-show="createForm.buttons.length < 2" class="btn btn-outline btn-sm w-full">
                                                    <i class="fas fa-plus mr-2"></i>Add CTA Button
                                                </button>
                                            </div>
                                            
                                            <p x-show="errors.buttons" x-text="errors.buttons" class="text-xs text-red-500"></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Preview Section -->
                                <div class="lg:sticky lg:top-0">
                                    <h4 class="font-medium text-sm text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-4">Preview</h4>
                                    <div class="bg-[#e5ddd5] rounded-lg p-4 min-h-[300px]">
                                        <!-- WhatsApp-style message bubble -->
                                        <div class="bg-white rounded-lg shadow-sm max-w-[280px] overflow-hidden">
                                            <!-- Header Preview -->
                                            <div x-show="createForm.hasHeader && createForm.header.type">
                                                <!-- Text Header -->
                                                <div x-show="createForm.header.type === 'TEXT' && createForm.header.text" class="px-3 pt-3">
                                                    <p class="font-semibold text-sm" x-text="createForm.header.text"></p>
                                                </div>
                                                <!-- Media Header -->
                                                <div x-show="['IMAGE', 'VIDEO', 'DOCUMENT'].includes(createForm.header.type)" class="bg-[hsl(var(--muted))] h-32 flex items-center justify-center">
                                                    <div class="text-center text-[hsl(var(--muted-foreground))]">
                                                        <i class="text-3xl mb-2" :class="{
                                                            'fas fa-image': createForm.header.type === 'IMAGE',
                                                            'fas fa-video': createForm.header.type === 'VIDEO',
                                                            'fas fa-file-alt': createForm.header.type === 'DOCUMENT'
                                                        }"></i>
                                                        <p class="text-xs" x-text="createForm.header.type"></p>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Body Preview -->
                                            <div class="px-3 py-2">
                                                <p class="text-sm whitespace-pre-wrap" x-html="getPreviewBody()"></p>
                                            </div>
                                            
                                            <!-- Footer Preview -->
                                            <div x-show="createForm.hasFooter && createForm.footer.text" class="px-3 pb-2">
                                                <p class="text-xs text-[hsl(var(--muted-foreground))]" x-text="createForm.footer.text"></p>
                                            </div>
                                            
                                            <!-- Buttons Preview -->
                                            <div x-show="createForm.hasButtons && createForm.buttons.length > 0" class="border-t border-[hsl(var(--border))]">
                                                <template x-for="(btn, index) in createForm.buttons" :key="'preview-btn-'+index">
                                                    <div class="border-b border-[hsl(var(--border))] last:border-b-0">
                                                        <button class="w-full py-2 text-sm text-[#00a5f4] font-medium flex items-center justify-center gap-2">
                                                            <i x-show="btn.type === 'URL'" class="fas fa-external-link-alt text-xs"></i>
                                                            <i x-show="btn.type === 'PHONE_NUMBER'" class="fas fa-phone text-xs"></i>
                                                            <i x-show="btn.type === 'QUICK_REPLY'" class="fas fa-reply text-xs"></i>
                                                            <span x-text="btn.text || 'Button ' + (index + 1)"></span>
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                        
                                        <!-- Variable Legend -->
                                        <div x-show="getVariableCount() > 0" class="mt-4 p-3 bg-white/80 rounded-lg">
                                            <p class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-2">Variables in this template:</p>
                                            <div class="flex flex-wrap gap-2">
                                                <template x-for="i in getVariableCount()" :key="'var-'+i">
                                                    <span class="inline-flex items-center px-2 py-1 bg-[#dcf8c6] text-xs rounded">
                                                        <span x-text="'{{' + i + '}}'"></span>
                                                        <span class="ml-1 text-[hsl(var(--muted-foreground))]">→ [Variable <span x-text="i"></span>]</span>
                                                    </span>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Modal Footer -->
                        <div class="p-6 border-t border-[hsl(var(--border))] flex-shrink-0 bg-[hsl(var(--card))]">
                            <!-- API Error Display -->
                            <div x-show="apiError" x-transition class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                                <div class="flex items-start gap-2">
                                    <i class="fas fa-exclamation-circle text-red-500 mt-0.5"></i>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-red-800">API Error</p>
                                        <p class="text-sm text-red-600" x-text="apiError"></p>
                                    </div>
                                    <button @click="apiError = null" class="text-red-400 hover:text-red-600">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <p x-show="Object.keys(errors).length > 0" class="text-sm text-red-500">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    Please fix the errors above
                                </p>
                                <div class="flex gap-3 ml-auto">
                                    <button type="button" @click="closeCreateModal" class="btn btn-outline btn-md" :disabled="creating">Cancel</button>
                                    <button type="button" @click="submitCreate" :disabled="creating || !isFormValid()" class="btn btn-primary btn-md">
                                        <i class="fas" :class="creating ? 'fa-spinner animate-spin' : 'fa-check'"></i>
                                        <span x-text="creating ? 'Creating...' : 'Create Template'"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Template Modal -->
                <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div x-show="showEditModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-black/50" @click="!updating && closeEditModal()"></div>
                    <div x-show="showEditModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="card relative w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                        <!-- Loading Overlay -->
                        <div x-show="updating" x-transition class="absolute inset-0 bg-white/80 z-10 flex items-center justify-center">
                            <div class="text-center">
                                <i class="fas fa-spinner fa-spin text-3xl text-primary mb-3"></i>
                                <p class="text-sm font-medium text-[hsl(var(--muted-foreground))]">Updating template...</p>
                            </div>
                        </div>
                        
                        <!-- Modal Header -->
                        <div class="p-6 border-b border-[hsl(var(--border))] flex items-center justify-between flex-shrink-0">
                            <h3 class="text-lg font-semibold">Edit Template</h3>
                            <button @click="closeEditModal" :disabled="updating" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
                        </div>
                        
                        <!-- Modal Body -->
                        <div class="flex-1 overflow-y-auto">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 p-6">
                                <!-- Form Section -->
                                <div class="space-y-5">
                                    <!-- Basic Info -->
                                    <div class="space-y-4">
                                        <h4 class="font-medium text-sm text-[hsl(var(--muted-foreground))] uppercase tracking-wide">Basic Information</h4>
                                        
                                        <!-- Template Name (Read-only) -->
                                        <div>
                                            <label class="text-sm font-medium mb-1.5 block">Template Name</label>
                                            <input type="text" x-model="editForm.name" disabled class="input w-full bg-[hsl(var(--muted)/0.5)] cursor-not-allowed">
                                            <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">Template name cannot be changed</p>
                                        </div>

                                        <!-- Category (Read-only) -->
                                        <div>
                                            <label class="text-sm font-medium mb-1.5 block">Category</label>
                                            <input type="text" x-model="editForm.category" disabled class="input w-full bg-[hsl(var(--muted)/0.5)] cursor-not-allowed">
                                        </div>

                                        <!-- Language (Read-only) -->
                                        <div>
                                            <label class="text-sm font-medium mb-1.5 block">Language</label>
                                            <input type="text" x-model="editForm.language" disabled class="input w-full bg-[hsl(var(--muted)/0.5)] cursor-not-allowed">
                                        </div>
                                    </div>

                                    <!-- Header Component -->
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <h4 class="font-medium text-sm text-[hsl(var(--muted-foreground))] uppercase tracking-wide">Header (Optional)</h4>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" x-model="editForm.hasHeader" class="rounded border-[hsl(var(--border))]">
                                                <span class="text-sm">Enable</span>
                                            </label>
                                        </div>
                                        
                                        <div x-show="editForm.hasHeader" x-collapse class="space-y-3">
                                            <div>
                                                <label class="text-sm font-medium mb-1.5 block">Header Type</label>
                                                <select x-model="editForm.header.type" class="input w-full">
                                                    <option value="TEXT">Text</option>
                                                    <option value="IMAGE">Image</option>
                                                    <option value="VIDEO">Video</option>
                                                    <option value="DOCUMENT">Document</option>
                                                </select>
                                            </div>
                                            
                                            <div x-show="editForm.header.type === 'TEXT'">
                                                <label class="text-sm font-medium mb-1.5 block">Header Text</label>
                                                <input type="text" x-model="editForm.header.text" placeholder="Enter header text..." class="input w-full" maxlength="60">
                                                <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1"><span x-text="(editForm.header.text || '').length"></span>/60 characters</p>
                                            </div>
                                            
                                            <div x-show="['IMAGE', 'VIDEO', 'DOCUMENT'].includes(editForm.header.type)">
                                                <label class="text-sm font-medium mb-1.5 block">Example Media URL</label>
                                                <input type="url" x-model="editForm.header.example" placeholder="https://example.com/media.jpg" class="input w-full">
                                                <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">Provide an example URL for template approval</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Body Component -->
                                    <div class="space-y-4">
                                        <h4 class="font-medium text-sm text-[hsl(var(--muted-foreground))] uppercase tracking-wide">Body <span class="text-red-500">*</span></h4>
                                        
                                        <div>
                                            <label class="text-sm font-medium mb-1.5 block">Body Text</label>
                                            <textarea x-model="editForm.body.text" @input="validateEditBody" placeholder="Enter your message body. Use {{1}}, {{2}}, etc. for variables..." class="input w-full min-h-[120px] resize-y" :class="{'border-red-500': editErrors.body}" maxlength="1024"></textarea>
                                            <div class="flex justify-between mt-1">
                                                <p x-show="editErrors.body" x-text="editErrors.body" class="text-xs text-red-500"></p>
                                                <p x-show="editWarnings.body" x-text="editWarnings.body" class="text-xs text-amber-500"></p>
                                                <p class="text-xs text-[hsl(var(--muted-foreground))]"><span x-text="(editForm.body.text || '').length"></span>/1024 characters</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Footer Component -->
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <h4 class="font-medium text-sm text-[hsl(var(--muted-foreground))] uppercase tracking-wide">Footer (Optional)</h4>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" x-model="editForm.hasFooter" class="rounded border-[hsl(var(--border))]">
                                                <span class="text-sm">Enable</span>
                                            </label>
                                        </div>
                                        
                                        <div x-show="editForm.hasFooter" x-collapse>
                                            <label class="text-sm font-medium mb-1.5 block">Footer Text</label>
                                            <input type="text" x-model="editForm.footer.text" @input="validateEditFooter" placeholder="Enter footer text..." class="input w-full" :class="{'border-red-500': editErrors.footer}" maxlength="60">
                                            <div class="flex justify-between mt-1">
                                                <p x-show="editErrors.footer" x-text="editErrors.footer" class="text-xs text-red-500"></p>
                                                <p class="text-xs text-[hsl(var(--muted-foreground))]"><span x-text="(editForm.footer.text || '').length"></span>/60 characters</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Buttons Component -->
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <h4 class="font-medium text-sm text-[hsl(var(--muted-foreground))] uppercase tracking-wide">Buttons (Optional)</h4>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" x-model="editForm.hasButtons" class="rounded border-[hsl(var(--border))]">
                                                <span class="text-sm">Enable</span>
                                            </label>
                                        </div>
                                        
                                        <div x-show="editForm.hasButtons" x-collapse class="space-y-3">
                                            <div>
                                                <label class="text-sm font-medium mb-1.5 block">Button Type</label>
                                                <select x-model="editForm.buttonType" @change="resetEditButtons" class="input w-full">
                                                    <option value="QUICK_REPLY">Quick Reply (max 10)</option>
                                                    <option value="CTA">Call to Action (max 2)</option>
                                                </select>
                                            </div>
                                            
                                            <!-- Quick Reply Buttons -->
                                            <div x-show="editForm.buttonType === 'QUICK_REPLY'" class="space-y-2">
                                                <template x-for="(btn, index) in editForm.buttons" :key="'edit-qr-'+index">
                                                    <div class="flex gap-2">
                                                        <input type="text" x-model="btn.text" :placeholder="'Button ' + (index + 1) + ' text'" class="input flex-1" maxlength="25">
                                                        <button type="button" @click="removeEditButton(index)" class="btn btn-ghost btn-icon text-red-500" x-show="editForm.buttons.length > 1">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </template>
                                                <button type="button" @click="addEditQuickReplyButton" x-show="editForm.buttons.length < 10" class="btn btn-outline btn-sm w-full">
                                                    <i class="fas fa-plus mr-2"></i>Add Quick Reply Button
                                                </button>
                                            </div>
                                            
                                            <!-- CTA Buttons -->
                                            <div x-show="editForm.buttonType === 'CTA'" class="space-y-3">
                                                <template x-for="(btn, index) in editForm.buttons" :key="'edit-cta-'+index">
                                                    <div class="p-3 bg-[hsl(var(--muted)/0.3)] rounded-lg space-y-2">
                                                        <div class="flex items-center justify-between">
                                                            <span class="text-sm font-medium">Button <span x-text="index + 1"></span></span>
                                                            <button type="button" @click="removeEditButton(index)" class="btn btn-ghost btn-icon btn-sm text-red-500" x-show="editForm.buttons.length > 1">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                        <select x-model="btn.type" class="input w-full">
                                                            <option value="URL">URL</option>
                                                            <option value="PHONE_NUMBER">Phone Number</option>
                                                        </select>
                                                        <input type="text" x-model="btn.text" placeholder="Button text" class="input w-full" maxlength="25">
                                                        <input x-show="btn.type === 'URL'" type="url" x-model="btn.url" placeholder="https://example.com" class="input w-full">
                                                        <input x-show="btn.type === 'PHONE_NUMBER'" type="tel" x-model="btn.phone_number" placeholder="+1234567890" class="input w-full">
                                                    </div>
                                                </template>
                                                <button type="button" @click="addEditCtaButton" x-show="editForm.buttons.length < 2" class="btn btn-outline btn-sm w-full">
                                                    <i class="fas fa-plus mr-2"></i>Add CTA Button
                                                </button>
                                            </div>
                                            
                                            <p x-show="editErrors.buttons" x-text="editErrors.buttons" class="text-xs text-red-500"></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Preview Section -->
                                <div class="lg:sticky lg:top-0">
                                    <h4 class="font-medium text-sm text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-4">Preview</h4>
                                    <div class="bg-[#e5ddd5] rounded-lg p-4 min-h-[300px]">
                                        <!-- WhatsApp-style message bubble -->
                                        <div class="bg-white rounded-lg shadow-sm max-w-[280px] overflow-hidden">
                                            <!-- Header Preview -->
                                            <div x-show="editForm.hasHeader && editForm.header.type">
                                                <!-- Text Header -->
                                                <div x-show="editForm.header.type === 'TEXT' && editForm.header.text" class="px-3 pt-3">
                                                    <p class="font-semibold text-sm" x-text="editForm.header.text"></p>
                                                </div>
                                                <!-- Media Header -->
                                                <div x-show="['IMAGE', 'VIDEO', 'DOCUMENT'].includes(editForm.header.type)" class="bg-[hsl(var(--muted))] h-32 flex items-center justify-center">
                                                    <div class="text-center text-[hsl(var(--muted-foreground))]">
                                                        <i class="text-3xl mb-2" :class="{
                                                            'fas fa-image': editForm.header.type === 'IMAGE',
                                                            'fas fa-video': editForm.header.type === 'VIDEO',
                                                            'fas fa-file-alt': editForm.header.type === 'DOCUMENT'
                                                        }"></i>
                                                        <p class="text-xs" x-text="editForm.header.type"></p>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Body Preview -->
                                            <div class="px-3 py-2">
                                                <p class="text-sm whitespace-pre-wrap" x-html="getEditPreviewBody()"></p>
                                            </div>
                                            
                                            <!-- Footer Preview -->
                                            <div x-show="editForm.hasFooter && editForm.footer.text" class="px-3 pb-2">
                                                <p class="text-xs text-[hsl(var(--muted-foreground))]" x-text="editForm.footer.text"></p>
                                            </div>
                                            
                                            <!-- Buttons Preview -->
                                            <div x-show="editForm.hasButtons && editForm.buttons.length > 0" class="border-t border-[hsl(var(--border))]">
                                                <template x-for="(btn, index) in editForm.buttons" :key="'edit-preview-btn-'+index">
                                                    <div class="border-b border-[hsl(var(--border))] last:border-b-0">
                                                        <button class="w-full py-2 text-sm text-[#00a5f4] font-medium flex items-center justify-center gap-2">
                                                            <i x-show="btn.type === 'URL'" class="fas fa-external-link-alt text-xs"></i>
                                                            <i x-show="btn.type === 'PHONE_NUMBER'" class="fas fa-phone text-xs"></i>
                                                            <i x-show="btn.type === 'QUICK_REPLY'" class="fas fa-reply text-xs"></i>
                                                            <span x-text="btn.text || 'Button ' + (index + 1)"></span>
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                        
                                        <!-- Variable Legend -->
                                        <div x-show="getEditVariableCount() > 0" class="mt-4 p-3 bg-white/80 rounded-lg">
                                            <p class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-2">Variables in this template:</p>
                                            <div class="flex flex-wrap gap-2">
                                                <template x-for="i in getEditVariableCount()" :key="'edit-var-'+i">
                                                    <span class="inline-flex items-center px-2 py-1 bg-[#dcf8c6] text-xs rounded">
                                                        <span x-text="'{{' + i + '}}'"></span>
                                                        <span class="ml-1 text-[hsl(var(--muted-foreground))]">→ [Variable <span x-text="i"></span>]</span>
                                                    </span>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Modal Footer -->
                        <div class="p-6 border-t border-[hsl(var(--border))] flex-shrink-0 bg-[hsl(var(--card))]">
                            <!-- API Error Display -->
                            <div x-show="editApiError" x-transition class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                                <div class="flex items-start gap-2">
                                    <i class="fas fa-exclamation-circle text-red-500 mt-0.5"></i>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-red-800">API Error</p>
                                        <p class="text-sm text-red-600" x-text="editApiError"></p>
                                    </div>
                                    <button @click="editApiError = null" class="text-red-400 hover:text-red-600">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <p x-show="Object.keys(editErrors).length > 0" class="text-sm text-red-500">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    Please fix the errors above
                                </p>
                                <div class="flex gap-3 ml-auto">
                                    <button type="button" @click="closeEditModal" class="btn btn-outline btn-md" :disabled="updating">Cancel</button>
                                    <button type="button" @click="submitEdit" :disabled="updating || !isEditFormValid()" class="btn btn-primary btn-md">
                                        <i class="fas" :class="updating ? 'fa-spinner animate-spin' : 'fa-check'"></i>
                                        <span x-text="updating ? 'Updating...' : 'Update Template'"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delete Confirmation Modal -->
                <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div x-show="showDeleteModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-black/50" @click="!deleting && closeDeleteModal()"></div>
                    <div x-show="showDeleteModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="card relative w-full max-w-md">
                        <!-- Loading Overlay -->
                        <div x-show="deleting" x-transition class="absolute inset-0 bg-white/80 z-10 flex items-center justify-center rounded-lg">
                            <div class="text-center">
                                <i class="fas fa-spinner fa-spin text-3xl text-red-500 mb-3"></i>
                                <p class="text-sm font-medium text-[hsl(var(--muted-foreground))]">Deleting template...</p>
                            </div>
                        </div>
                        
                        <!-- Modal Header -->
                        <div class="p-6 border-b border-[hsl(var(--border))] flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-red-600">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                Delete Template
                            </h3>
                            <button @click="closeDeleteModal" :disabled="deleting" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
                        </div>
                        
                        <!-- Modal Body -->
                        <div class="p-6">
                            <p class="text-[hsl(var(--muted-foreground))] mb-4">
                                Are you sure you want to delete this template? This action cannot be undone.
                            </p>
                            <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4">
                                <p class="text-xs text-[hsl(var(--muted-foreground))] mb-1">Template Name</p>
                                <p class="font-semibold" x-text="templateToDelete?.name"></p>
                                <div class="flex items-center gap-2 mt-2">
                                    <span class="badge badge-secondary text-xs" x-text="templateToDelete?.category"></span>
                                    <span class="badge badge-outline text-xs" x-text="templateToDelete?.language"></span>
                                </div>
                            </div>
                            
                            <!-- API Error Display -->
                            <div x-show="deleteApiError" x-transition class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                                <div class="flex items-start gap-2">
                                    <i class="fas fa-exclamation-circle text-red-500 mt-0.5"></i>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-red-800">Delete Failed</p>
                                        <p class="text-sm text-red-600" x-text="deleteApiError"></p>
                                    </div>
                                    <button @click="deleteApiError = null" class="text-red-400 hover:text-red-600">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Modal Footer -->
                        <div class="p-6 border-t border-[hsl(var(--border))] flex gap-3 justify-end">
                            <button type="button" @click="closeDeleteModal" class="btn btn-outline btn-md" :disabled="deleting">
                                Cancel
                            </button>
                            <button type="button" @click="confirmDelete" :disabled="deleting" class="btn btn-md bg-red-600 hover:bg-red-700 text-white">
                                <i class="fas" :class="deleting ? 'fa-spinner animate-spin' : 'fa-trash'"></i>
                                <span x-text="deleting ? 'Deleting...' : 'Delete Template'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Global toast manager for notifications
function toastManager() {
    return {
        toasts: [],
        
        addToast(message, type = 'info', duration = 5000) {
            const id = Date.now() + Math.random();
            const toast = { id, message, type, visible: true };
            this.toasts.push(toast);
            
            // Auto-remove after duration
            if (duration > 0) {
                setTimeout(() => {
                    this.removeToast(id);
                }, duration);
            }
            
            return id;
        },
        
        removeToast(id) {
            const toast = this.toasts.find(t => t.id === id);
            if (toast) {
                toast.visible = false;
                setTimeout(() => {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }, 300);
            }
        },
        
        success(message, duration = 5000) {
            return this.addToast(message, 'success', duration);
        },
        
        error(message, duration = 7000) {
            return this.addToast(message, 'error', duration);
        },
        
        warning(message, duration = 6000) {
            return this.addToast(message, 'warning', duration);
        },
        
        info(message, duration = 5000) {
            return this.addToast(message, 'info', duration);
        }
    };
}

// Global toast function accessible from other components
window.showToast = function(message, type = 'info', duration = 5000) {
    const container = document.getElementById('toast-container');
    if (container && container.__x) {
        container.__x.$data.addToast(message, type, duration);
    }
};

function templatesApp() {
    return {
        sidebarOpen: true, user: null, notifications: [],
        init() {
            let savedState = localStorage.getItem('sidebarOpen');
            if (savedState !== null) this.sidebarOpen = JSON.parse(savedState);
            this.$watch('sidebarOpen', v => localStorage.setItem('sidebarOpen', JSON.stringify(v)));
            let storedUser = localStorage.getItem('user');
            if (storedUser) { try { this.user = JSON.parse(storedUser); } catch (e) { this.user = { name: 'User', email: 'user@example.com' }; } }
            else { this.user = { name: 'User', email: 'user@example.com' }; }
            let savedNotifs = localStorage.getItem('notifications');
            if (savedNotifs) { try { this.notifications = JSON.parse(savedNotifs); } catch (e) { this.notifications = []; } }
        },
        addNotification(n) { n.id = Date.now() + Math.random(); this.notifications.unshift(n); if (this.notifications.length > 50) this.notifications = this.notifications.slice(0, 50); localStorage.setItem('notifications', JSON.stringify(this.notifications)); },
        clearNotifications() { this.notifications = []; localStorage.removeItem('notifications'); },
        removeNotification(id) { this.notifications = this.notifications.filter(n => n.id !== id); localStorage.setItem('notifications', JSON.stringify(this.notifications)); },
        formatNotificationTime(t) { let d = new Date(t), diff = Math.floor((new Date() - d) / 1000); if (diff < 60) return 'Just now'; if (diff < 3600) return Math.floor(diff / 60) + 'm ago'; if (diff < 86400) return Math.floor(diff / 3600) + 'h ago'; return d.toLocaleDateString(); },
        logout() { let token = localStorage.getItem('token'); if (token) { fetch(`${window.location.origin}/api/logout`, { method: 'POST', headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } }).finally(() => { localStorage.removeItem('token'); localStorage.removeItem('user'); localStorage.removeItem('sidebarOpen'); localStorage.removeItem('notifications'); window.location.href = '/login'; }); } else { localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href = '/login'; } }
    }
}

function templatesManager() {
    return {
        API_BASE_URL: window.location.origin + '/api',
        templates: [], filteredTemplates: [], contacts: [], selectedTemplate: null,
        showSendModal: false, showViewModal: false, showCreateModal: false, showEditModal: false, showDeleteModal: false,
        loading: true, sending: false, creating: false, updating: false, deleting: false, refreshing: false,
        templateToDelete: null,
        filters: { status: '', category: '', search: '' },
        sendForm: { contactId: '' },
        // API error state for displaying errors in modals
        apiError: null,
        editApiError: null,
        deleteApiError: null,
        createForm: {
            name: '',
            category: '',
            language: '',
            hasHeader: false,
            header: { type: 'TEXT', text: '', example: '' },
            body: { text: '' },
            hasFooter: false,
            footer: { text: '' },
            hasButtons: false,
            buttonType: 'QUICK_REPLY',
            buttons: [{ type: 'QUICK_REPLY', text: '' }]
        },
        editForm: {
            id: null,
            name: '',
            category: '',
            language: '',
            hasHeader: false,
            header: { type: 'TEXT', text: '', example: '' },
            body: { text: '' },
            hasFooter: false,
            footer: { text: '' },
            hasButtons: false,
            buttonType: 'QUICK_REPLY',
            buttons: [{ type: 'QUICK_REPLY', text: '' }]
        },
        errors: {},
        warnings: {},
        editErrors: {},
        editWarnings: {},

        async init() { await Promise.all([this.fetchTemplates(), this.fetchContacts()]); },

        async fetchTemplates(refresh = false) {
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const url = refresh 
                    ? `${this.API_BASE_URL}/whatsapp/templates?refresh=true`
                    : `${this.API_BASE_URL}/whatsapp/templates`;
                const res = await fetch(url, { headers: { 'Authorization': `Bearer ${token}` } });
                const data = await res.json();
                this.templates = data.data || [];
                this.filterTemplates();
            } catch (e) { console.error('Error:', e); }
            finally { this.loading = false; }
        },

        async refreshTemplates() {
            this.refreshing = true;
            try {
                await this.fetchTemplates(true);
                this.showToast('Templates synced from Meta successfully!', 'success');
            } catch (e) {
                console.error('Error refreshing templates:', e);
                this.showToast('Failed to sync templates from Meta', 'error');
            } finally {
                this.refreshing = false;
            }
        },

        async fetchContacts() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/contacts`, { headers: { 'Authorization': `Bearer ${token}` } });
                const data = await res.json();
                this.contacts = data.data || [];
            } catch (e) { console.error('Error:', e); }
        },

        filterTemplates() {
            let filtered = this.templates;
            if (this.filters.status) filtered = filtered.filter(t => t.status === this.filters.status);
            if (this.filters.category) filtered = filtered.filter(t => t.category === this.filters.category);
            if (this.filters.search) {
                const q = this.filters.search.toLowerCase();
                filtered = filtered.filter(t => t.name.toLowerCase().includes(q) || (t.body && t.body.toLowerCase().includes(q)));
            }
            this.filteredTemplates = filtered;
        },

        viewTemplate(t) { this.selectedTemplate = t; this.showViewModal = true; },
        closeViewModal() { this.showViewModal = false; setTimeout(() => this.selectedTemplate = null, 200); },
        sendTemplateModal(t) { this.selectedTemplate = t; this.sendForm.contactId = ''; this.showSendModal = true; },
        closeSendModal() { this.showSendModal = false; setTimeout(() => { this.selectedTemplate = null; this.sendForm.contactId = ''; }, 200); },

        async sendTemplate() {
            if (!this.sendForm.contactId || !this.selectedTemplate) return;
            this.sending = true;
            try {
                const token = localStorage.getItem('token');
                const contact = this.contacts.find(c => c.id == this.sendForm.contactId);
                if (!contact) { alert('Contact not found'); return; }
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/send-template`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ to: contact.phone_number, template_name: this.selectedTemplate.name, language_code: this.selectedTemplate.language || 'en' })
                });
                const data = await res.json();
                if (data.success) { alert('Template sent successfully!'); this.closeSendModal(); }
                else { alert(data.message || 'Failed to send template'); }
            } catch (e) { console.error('Error:', e); alert('Failed to send template'); }
            finally { this.sending = false; }
        },

        // Create Template Methods
        openCreateModal() {
            this.resetCreateForm();
            this.showCreateModal = true;
        },

        closeCreateModal() {
            this.showCreateModal = false;
            setTimeout(() => this.resetCreateForm(), 200);
        },

        resetCreateForm() {
            this.createForm = {
                name: '',
                category: '',
                language: '',
                hasHeader: false,
                header: { type: 'TEXT', text: '', example: '' },
                body: { text: '' },
                hasFooter: false,
                footer: { text: '' },
                hasButtons: false,
                buttonType: 'QUICK_REPLY',
                buttons: [{ type: 'QUICK_REPLY', text: '' }]
            };
            this.errors = {};
            this.warnings = {};
            this.apiError = null;
        },

        // Validation Methods
        validateName() {
            const name = this.createForm.name;
            if (!name) {
                this.errors.name = 'Template name is required';
                return false;
            }
            if (!/^[a-z0-9_]+$/.test(name)) {
                this.errors.name = 'Only lowercase letters, numbers, and underscores allowed';
                return false;
            }
            delete this.errors.name;
            return true;
        },

        validateBody() {
            const body = this.createForm.body.text;
            if (!body) {
                this.errors.body = 'Body text is required';
                return false;
            }
            if (body.length > 1024) {
                this.errors.body = 'Body text cannot exceed 1024 characters';
                return false;
            }
            delete this.errors.body;
            
            // Check variable sequence (warning only)
            this.validateVariableSequence();
            return true;
        },

        validateVariableSequence() {
            const body = this.createForm.body.text;
            const matches = body.match(/\{\{(\d+)\}\}/g);
            if (!matches) {
                delete this.warnings.body;
                return;
            }
            
            const numbers = matches.map(m => parseInt(m.replace(/[{}]/g, ''))).sort((a, b) => a - b);
            const unique = [...new Set(numbers)];
            const expected = Array.from({ length: unique.length }, (_, i) => i + 1);
            
            if (JSON.stringify(unique) !== JSON.stringify(expected)) {
                this.warnings.body = 'Variables should be sequential starting from {{1}}';
            } else {
                delete this.warnings.body;
            }
        },

        validateFooter() {
            const footer = this.createForm.footer.text;
            if (footer && footer.length > 60) {
                this.errors.footer = 'Footer cannot exceed 60 characters';
                return false;
            }
            delete this.errors.footer;
            return true;
        },

        isFormValid() {
            // Check required fields
            if (!this.createForm.name || !this.createForm.category || !this.createForm.language || !this.createForm.body.text) {
                return false;
            }
            // Check for errors
            if (Object.keys(this.errors).length > 0) {
                return false;
            }
            return true;
        },

        // Button Management
        resetButtons() {
            if (this.createForm.buttonType === 'QUICK_REPLY') {
                this.createForm.buttons = [{ type: 'QUICK_REPLY', text: '' }];
            } else {
                this.createForm.buttons = [{ type: 'URL', text: '', url: '' }];
            }
        },

        addQuickReplyButton() {
            if (this.createForm.buttons.length < 10) {
                this.createForm.buttons.push({ type: 'QUICK_REPLY', text: '' });
            }
        },

        addCtaButton() {
            if (this.createForm.buttons.length < 2) {
                this.createForm.buttons.push({ type: 'URL', text: '', url: '' });
            }
        },

        removeButton(index) {
            if (this.createForm.buttons.length > 1) {
                this.createForm.buttons.splice(index, 1);
            }
        },

        // Preview Methods
        getPreviewBody() {
            let body = this.createForm.body.text || 'Your message body will appear here...';
            // Replace variables with styled placeholders
            body = body.replace(/\{\{(\d+)\}\}/g, '<span class="inline-block px-1 py-0.5 bg-[#dcf8c6] rounded text-xs font-medium">[Variable $1]</span>');
            return body;
        },

        getVariableCount() {
            const body = this.createForm.body.text || '';
            const matches = body.match(/\{\{(\d+)\}\}/g);
            if (!matches) return 0;
            const numbers = matches.map(m => parseInt(m.replace(/[{}]/g, '')));
            return Math.max(...numbers, 0);
        },

        // API Submission
        async submitCreate() {
            // Clear previous API error
            this.apiError = null;
            
            // Validate all fields
            this.validateName();
            this.validateBody();
            if (this.createForm.hasFooter) this.validateFooter();
            
            if (!this.createForm.category) this.errors.category = 'Category is required';
            else delete this.errors.category;
            
            if (!this.createForm.language) this.errors.language = 'Language is required';
            else delete this.errors.language;

            if (!this.isFormValid()) return;

            this.creating = true;
            try {
                const token = localStorage.getItem('token');
                
                // Build request payload
                const payload = {
                    name: this.createForm.name,
                    category: this.createForm.category,
                    language: this.createForm.language,
                    body: this.createForm.body.text
                };

                // Add header if enabled
                if (this.createForm.hasHeader) {
                    payload.header = {
                        type: this.createForm.header.type,
                        text: this.createForm.header.type === 'TEXT' ? this.createForm.header.text : null,
                        example: ['IMAGE', 'VIDEO', 'DOCUMENT'].includes(this.createForm.header.type) ? this.createForm.header.example : null
                    };
                }

                // Add footer if enabled
                if (this.createForm.hasFooter && this.createForm.footer.text) {
                    payload.footer = this.createForm.footer.text;
                }

                // Add buttons if enabled
                if (this.createForm.hasButtons && this.createForm.buttons.length > 0) {
                    payload.buttons = this.createForm.buttons.filter(btn => btn.text).map(btn => {
                        const button = { type: btn.type, text: btn.text };
                        if (btn.type === 'URL' && btn.url) button.url = btn.url;
                        if (btn.type === 'PHONE_NUMBER' && btn.phone_number) button.phone_number = btn.phone_number;
                        return button;
                    });
                }

                const res = await fetch(`${this.API_BASE_URL}/whatsapp/templates`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();

                if (data.success) {
                    this.showToast('Template created successfully!', 'success');
                    this.closeCreateModal();
                    await this.fetchTemplates();
                } else {
                    // Extract error message from API response
                    const errorMsg = data.message || data.error || 'Failed to create template';
                    this.apiError = errorMsg;
                    this.showToast(errorMsg, 'error');
                    // Form state is preserved - user can fix and retry
                }
            } catch (e) {
                console.error('Error creating template:', e);
                const errorMsg = 'Failed to create template. Please check your connection and try again.';
                this.apiError = errorMsg;
                this.showToast(errorMsg, 'error');
                // Form state is preserved on network errors
            } finally {
                this.creating = false;
            }
        },

        showToast(message, type = 'info') {
            // Use global toast notification system
            if (window.showToast) {
                window.showToast(message, type);
            } else {
                // Fallback to alert if toast system not available
                console.log(`[${type}] ${message}`);
            }
        },

        // Edit Template Methods
        openEditModal(template) {
            this.resetEditForm();
            this.editForm.id = template.id;
            this.editForm.name = template.name;
            this.editForm.category = template.category;
            this.editForm.language = template.language;
            
            // Populate header
            if (template.header || template.header_type) {
                this.editForm.hasHeader = true;
                this.editForm.header.type = template.header_type || 'TEXT';
                this.editForm.header.text = template.header || '';
            }
            
            // Populate body
            this.editForm.body.text = template.body || '';
            
            // Populate footer
            if (template.footer) {
                this.editForm.hasFooter = true;
                this.editForm.footer.text = template.footer;
            }
            
            // Populate buttons
            if (template.buttons) {
                let buttons = template.buttons;
                if (typeof buttons === 'string') {
                    try {
                        buttons = JSON.parse(buttons);
                    } catch (e) {
                        buttons = [];
                    }
                }
                if (Array.isArray(buttons) && buttons.length > 0) {
                    this.editForm.hasButtons = true;
                    // Determine button type
                    const firstBtn = buttons[0];
                    if (firstBtn.type === 'QUICK_REPLY') {
                        this.editForm.buttonType = 'QUICK_REPLY';
                    } else {
                        this.editForm.buttonType = 'CTA';
                    }
                    this.editForm.buttons = buttons.map(btn => ({
                        type: btn.type || 'QUICK_REPLY',
                        text: btn.text || '',
                        url: btn.url || '',
                        phone_number: btn.phone_number || ''
                    }));
                }
            }
            
            this.showEditModal = true;
        },

        closeEditModal() {
            this.showEditModal = false;
            setTimeout(() => this.resetEditForm(), 200);
        },

        resetEditForm() {
            this.editForm = {
                id: null,
                name: '',
                category: '',
                language: '',
                hasHeader: false,
                header: { type: 'TEXT', text: '', example: '' },
                body: { text: '' },
                hasFooter: false,
                footer: { text: '' },
                hasButtons: false,
                buttonType: 'QUICK_REPLY',
                buttons: [{ type: 'QUICK_REPLY', text: '' }]
            };
            this.editErrors = {};
            this.editWarnings = {};
            this.editApiError = null;
        },

        // Edit Validation Methods
        validateEditBody() {
            const body = this.editForm.body.text;
            if (!body) {
                this.editErrors.body = 'Body text is required';
                return false;
            }
            if (body.length > 1024) {
                this.editErrors.body = 'Body text cannot exceed 1024 characters';
                return false;
            }
            delete this.editErrors.body;
            
            // Check variable sequence (warning only)
            this.validateEditVariableSequence();
            return true;
        },

        validateEditVariableSequence() {
            const body = this.editForm.body.text;
            const matches = body.match(/\{\{(\d+)\}\}/g);
            if (!matches) {
                delete this.editWarnings.body;
                return;
            }
            
            const numbers = matches.map(m => parseInt(m.replace(/[{}]/g, ''))).sort((a, b) => a - b);
            const unique = [...new Set(numbers)];
            const expected = Array.from({ length: unique.length }, (_, i) => i + 1);
            
            if (JSON.stringify(unique) !== JSON.stringify(expected)) {
                this.editWarnings.body = 'Variables should be sequential starting from {{1}}';
            } else {
                delete this.editWarnings.body;
            }
        },

        validateEditFooter() {
            const footer = this.editForm.footer.text;
            if (footer && footer.length > 60) {
                this.editErrors.footer = 'Footer cannot exceed 60 characters';
                return false;
            }
            delete this.editErrors.footer;
            return true;
        },

        isEditFormValid() {
            // Check required fields
            if (!this.editForm.body.text) {
                return false;
            }
            // Check for errors
            if (Object.keys(this.editErrors).length > 0) {
                return false;
            }
            return true;
        },

        // Edit Button Management
        resetEditButtons() {
            if (this.editForm.buttonType === 'QUICK_REPLY') {
                this.editForm.buttons = [{ type: 'QUICK_REPLY', text: '' }];
            } else {
                this.editForm.buttons = [{ type: 'URL', text: '', url: '' }];
            }
        },

        addEditQuickReplyButton() {
            if (this.editForm.buttons.length < 10) {
                this.editForm.buttons.push({ type: 'QUICK_REPLY', text: '' });
            }
        },

        addEditCtaButton() {
            if (this.editForm.buttons.length < 2) {
                this.editForm.buttons.push({ type: 'URL', text: '', url: '' });
            }
        },

        removeEditButton(index) {
            if (this.editForm.buttons.length > 1) {
                this.editForm.buttons.splice(index, 1);
            }
        },

        // Edit Preview Methods
        getEditPreviewBody() {
            let body = this.editForm.body.text || 'Your message body will appear here...';
            // Replace variables with styled placeholders
            body = body.replace(/\{\{(\d+)\}\}/g, '<span class="inline-block px-1 py-0.5 bg-[#dcf8c6] rounded text-xs font-medium">[Variable $1]</span>');
            return body;
        },

        getEditVariableCount() {
            const body = this.editForm.body.text || '';
            const matches = body.match(/\{\{(\d+)\}\}/g);
            if (!matches) return 0;
            const numbers = matches.map(m => parseInt(m.replace(/[{}]/g, '')));
            return Math.max(...numbers, 0);
        },

        // Edit API Submission
        async submitEdit() {
            // Clear previous API error
            this.editApiError = null;
            
            // Validate all fields
            this.validateEditBody();
            if (this.editForm.hasFooter) this.validateEditFooter();

            if (!this.isEditFormValid()) return;

            this.updating = true;
            try {
                const token = localStorage.getItem('token');
                
                // Build request payload
                const payload = {
                    body: this.editForm.body.text
                };

                // Add header if enabled
                if (this.editForm.hasHeader) {
                    payload.header = {
                        type: this.editForm.header.type,
                        text: this.editForm.header.type === 'TEXT' ? this.editForm.header.text : null,
                        example: ['IMAGE', 'VIDEO', 'DOCUMENT'].includes(this.editForm.header.type) ? this.editForm.header.example : null
                    };
                }

                // Add footer if enabled
                if (this.editForm.hasFooter && this.editForm.footer.text) {
                    payload.footer = this.editForm.footer.text;
                }

                // Add buttons if enabled
                if (this.editForm.hasButtons && this.editForm.buttons.length > 0) {
                    payload.buttons = this.editForm.buttons.filter(btn => btn.text).map(btn => {
                        const button = { type: btn.type, text: btn.text };
                        if (btn.type === 'URL' && btn.url) button.url = btn.url;
                        if (btn.type === 'PHONE_NUMBER' && btn.phone_number) button.phone_number = btn.phone_number;
                        return button;
                    });
                }

                const res = await fetch(`${this.API_BASE_URL}/whatsapp/templates/${this.editForm.id}`, {
                    method: 'PUT',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();

                if (data.success) {
                    this.showToast('Template updated successfully!', 'success');
                    this.closeEditModal();
                    await this.fetchTemplates();
                } else {
                    // Extract error message from API response
                    const errorMsg = data.message || data.error || 'Failed to update template';
                    this.editApiError = errorMsg;
                    this.showToast(errorMsg, 'error');
                    // Form state is preserved - user can fix and retry
                }
            } catch (e) {
                console.error('Error updating template:', e);
                const errorMsg = 'Failed to update template. Please check your connection and try again.';
                this.editApiError = errorMsg;
                this.showToast(errorMsg, 'error');
                // Form state is preserved on network errors
            } finally {
                this.updating = false;
            }
        },

        // Delete Template Methods
        openDeleteModal(template) {
            this.templateToDelete = template;
            this.showDeleteModal = true;
        },

        closeDeleteModal() {
            this.showDeleteModal = false;
            setTimeout(() => {
                this.templateToDelete = null;
                this.deleteApiError = null;
            }, 200);
        },

        async confirmDelete() {
            if (!this.templateToDelete) return;

            // Clear previous API error
            this.deleteApiError = null;
            
            this.deleting = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/templates/${this.templateToDelete.name}`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });

                const data = await res.json();

                if (data.success) {
                    this.showToast('Template deleted successfully!', 'success');
                    this.closeDeleteModal();
                    await this.fetchTemplates();
                } else {
                    // Extract error message from API response
                    const errorMsg = data.message || data.error || 'Failed to delete template';
                    this.deleteApiError = errorMsg;
                    this.showToast(errorMsg, 'error');
                    // Keep modal open so user can see the error
                }
            } catch (e) {
                console.error('Error deleting template:', e);
                const errorMsg = 'Failed to delete template. Please check your connection and try again.';
                this.deleteApiError = errorMsg;
                this.showToast(errorMsg, 'error');
            } finally {
                this.deleting = false;
            }
        }
    }
}
</script>
@endsection
