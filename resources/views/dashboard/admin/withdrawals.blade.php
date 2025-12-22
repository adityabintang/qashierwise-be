@extends('layouts.app')

@section('title', 'Withdrawal Management - Admin')

@section('content')
<div x-data="adminWithdrawalsApp()" class="min-h-screen flex bg-[hsl(var(--muted)/0.4)]">
    @include('components.dashboard-sidebar', ['activePage' => 'admin-withdrawals'])

    <div class="flex-1 flex flex-col min-h-screen">
        @include('components.dashboard-header', ['title' => 'Withdrawal Management', 'description' => 'Review and process withdrawal requests'])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Stats Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="card p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center">
                                <i class="fas fa-clock text-amber-600"></i>
                            </div>
                            <div>
                                <p class="text-xs text-[hsl(var(--muted-foreground))]">Pending</p>
                                <p class="text-xl font-bold" x-text="stats.pending?.count || 0"></p>
                                <p class="text-xs text-[hsl(var(--muted-foreground))]" x-text="formatCurrency(stats.pending?.amount || 0)"></p>
                            </div>
                        </div>
                    </div>
                    <div class="card p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-check text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-xs text-[hsl(var(--muted-foreground))]">Approved</p>
                                <p class="text-xl font-bold" x-text="stats.approved?.count || 0"></p>
                                <p class="text-xs text-[hsl(var(--muted-foreground))]" x-text="formatCurrency(stats.approved?.amount || 0)"></p>
                            </div>
                        </div>
                    </div>
                    <div class="card p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center">
                                <i class="fas fa-check-double text-emerald-600"></i>
                            </div>
                            <div>
                                <p class="text-xs text-[hsl(var(--muted-foreground))]">Processed</p>
                                <p class="text-xl font-bold" x-text="stats.processed?.count || 0"></p>
                                <p class="text-xs text-[hsl(var(--muted-foreground))]" x-text="formatCurrency(stats.processed?.amount || 0)"></p>
                            </div>
                        </div>
                    </div>
                    <div class="card p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center">
                                <i class="fas fa-times text-red-600"></i>
                            </div>
                            <div>
                                <p class="text-xs text-[hsl(var(--muted-foreground))]">Rejected</p>
                                <p class="text-xl font-bold" x-text="stats.rejected?.count || 0"></p>
                                <p class="text-xs text-[hsl(var(--muted-foreground))]" x-text="formatCurrency(stats.rejected?.amount || 0)"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card p-3 sm:p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5 block">Status Filter</label>
                            <select x-model="statusFilter" @change="fetchWithdrawals()" class="input w-full min-h-[44px]">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="processed">Processed</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2 flex items-end">
                            <button @click="fetchWithdrawals()" class="btn btn-outline min-h-[44px]">
                                <i class="fas fa-sync-alt mr-2"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mobile Card View -->
                <div class="block lg:hidden space-y-3">
                    <template x-if="loading">
                        <template x-for="i in 4" :key="'skeleton-'+i">
                            <div class="card p-4">
                                <div class="flex justify-between mb-2">
                                    <div class="skeleton h-4 w-24"></div>
                                    <div class="skeleton h-5 w-20"></div>
                                </div>
                                <div class="skeleton h-3 w-32 mb-1"></div>
                                <div class="skeleton h-3 w-24"></div>
                            </div>
                        </template>
                    </template>

                    <template x-if="!loading">
                        <template x-for="withdrawal in withdrawals" :key="withdrawal.id">
                            <div class="card p-4 hover:shadow-md transition-shadow" @click="viewWithdrawal(withdrawal)">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="font-semibold" x-text="'#' + withdrawal.id"></span>
                                    <span class="text-lg font-bold text-[hsl(var(--primary))]" x-text="formatCurrency(withdrawal.amount)"></span>
                                </div>
                                <div class="flex items-center gap-2 text-sm text-[hsl(var(--muted-foreground))]">
                                    <span x-text="withdrawal.sub_merchant?.user?.name || 'Unknown'"></span>
                                    <span>•</span>
                                    <span x-text="formatDate(withdrawal.created_at)"></span>
                                </div>
                                <div class="flex items-center gap-2 mt-2">
                                    <span class="badge text-xs" :class="getStatusClass(withdrawal.status)" x-text="withdrawal.status"></span>
                                    <span class="text-xs text-[hsl(var(--muted-foreground))]" x-text="withdrawal.bank_details?.bank_name"></span>
                                </div>
                                <template x-if="withdrawal.can_be_processed">
                                    <div class="flex gap-2 mt-3">
                                        <button @click.stop="openApproveModal(withdrawal)" class="btn btn-sm bg-emerald-500 hover:bg-emerald-600 text-white flex-1">
                                            <i class="fas fa-check mr-1"></i> Approve
                                        </button>
                                        <button @click.stop="openRejectModal(withdrawal)" class="btn btn-sm bg-red-500 hover:bg-red-600 text-white flex-1">
                                            <i class="fas fa-times mr-1"></i> Reject
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </template>
                </div>

                <!-- Desktop Table View -->
                <div class="hidden lg:block card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-[hsl(var(--muted)/0.5)]">
                                <tr>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">ID</th>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Merchant</th>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Bank Details</th>
                                    <th class="text-right p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Amount</th>
                                    <th class="text-center p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Status</th>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Date</th>
                                    <th class="text-right p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[hsl(var(--border))]">
                                <template x-if="loading">
                                    <template x-for="i in 5" :key="'table-skeleton-'+i">
                                        <tr>
                                            <td class="p-4"><div class="skeleton h-4 w-12"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-24"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-32"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-20 ml-auto"></div></td>
                                            <td class="p-4"><div class="skeleton h-6 w-16 mx-auto rounded-full"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-24"></div></td>
                                            <td class="p-4"><div class="skeleton h-8 w-24 ml-auto"></div></td>
                                        </tr>
                                    </template>
                                </template>
                                <template x-if="!loading">
                                    <template x-for="withdrawal in withdrawals" :key="withdrawal.id">
                                        <tr class="hover:bg-[hsl(var(--muted)/0.3)] transition-colors">
                                            <td class="p-4 font-medium" x-text="'#' + withdrawal.id"></td>
                                            <td class="p-4">
                                                <div class="text-sm font-medium" x-text="withdrawal.sub_merchant?.user?.name || 'Unknown'"></div>
                                                <div class="text-xs text-[hsl(var(--muted-foreground))]" x-text="withdrawal.sub_merchant?.user?.email || ''"></div>
                                            </td>
                                            <td class="p-4">
                                                <div class="text-sm" x-text="withdrawal.bank_details?.bank_name || '-'"></div>
                                                <div class="text-xs text-[hsl(var(--muted-foreground))]" x-text="withdrawal.bank_details?.account_number || ''"></div>
                                                <div class="text-xs text-[hsl(var(--muted-foreground))]" x-text="withdrawal.bank_details?.account_holder_name || ''"></div>
                                            </td>
                                            <td class="p-4 text-right font-medium" x-text="formatCurrency(withdrawal.amount)"></td>
                                            <td class="p-4 text-center">
                                                <span class="badge text-xs" :class="getStatusClass(withdrawal.status)" x-text="withdrawal.status"></span>
                                            </td>
                                            <td class="p-4 text-sm text-[hsl(var(--muted-foreground))]" x-text="formatDate(withdrawal.created_at)"></td>
                                            <td class="p-4 text-right">
                                                <div class="flex items-center justify-end gap-1">
                                                    <button @click="viewWithdrawal(withdrawal)" class="btn btn-ghost btn-sm" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <template x-if="withdrawal.can_be_processed">
                                                        <button @click="openApproveModal(withdrawal)" class="btn btn-sm bg-emerald-500 hover:bg-emerald-600 text-white" title="Approve">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </template>
                                                    <template x-if="withdrawal.can_be_processed">
                                                        <button @click="openRejectModal(withdrawal)" class="btn btn-sm bg-red-500 hover:bg-red-600 text-white" title="Reject">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </template>
                                                    <template x-if="withdrawal.is_approved">
                                                        <button @click="markAsProcessed(withdrawal)" class="btn btn-sm bg-blue-500 hover:bg-blue-600 text-white" title="Mark Processed">
                                                            <i class="fas fa-check-double"></i>
                                                        </button>
                                                    </template>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State -->
                <div x-show="!loading && withdrawals.length === 0" class="card">
                    <div class="empty-state py-16">
                        <div class="empty-state-icon"><i class="fas fa-wallet text-2xl"></i></div>
                        <h3 class="font-semibold mt-4">No withdrawal requests found</h3>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] mt-1">Withdrawal requests will appear here when merchants submit them.</p>
                    </div>
                </div>

                <!-- Pagination -->
                <div x-show="!loading && pagination.lastPage > 1" class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <p class="text-sm text-[hsl(var(--muted-foreground))]">
                        Showing <span x-text="pagination.from"></span> to <span x-text="pagination.to"></span> of <span x-text="pagination.total"></span>
                    </p>
                    <div class="flex items-center gap-2">
                        <button @click="goToPage(pagination.currentPage - 1)" :disabled="pagination.currentPage === 1" class="btn btn-outline btn-sm"><i class="fas fa-chevron-left"></i></button>
                        <template x-for="page in paginationPages" :key="page">
                            <button @click="goToPage(page)" class="btn btn-sm" :class="page === pagination.currentPage ? 'btn-primary' : 'btn-outline'" x-text="page"></button>
                        </template>
                        <button @click="goToPage(pagination.currentPage + 1)" :disabled="pagination.currentPage === pagination.lastPage" class="btn btn-outline btn-sm"><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- View Withdrawal Modal -->
    <div x-show="showViewModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-0 sm:p-4">
        <div x-show="showViewModal" x-transition class="fixed inset-0 bg-black/50" @click="closeViewModal()"></div>
        <div x-show="showViewModal" x-transition class="card relative w-full h-full sm:h-auto sm:max-w-lg sm:max-h-[90vh] overflow-hidden flex flex-col sm:rounded-lg rounded-none">
            <div class="p-4 sm:p-6 border-b border-[hsl(var(--border))] flex items-center justify-between flex-shrink-0">
                <h3 class="text-lg font-semibold">Withdrawal Details</h3>
                <button @click="closeViewModal()" class="btn btn-ghost btn-icon min-h-[44px] min-w-[44px]"><i class="fas fa-times"></i></button>
            </div>
            <div class="flex-1 overflow-y-auto scroll-area p-4 sm:p-6" x-show="selectedWithdrawal">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h4 class="text-xl font-bold" x-text="'#' + selectedWithdrawal?.id"></h4>
                        <p class="text-sm text-[hsl(var(--muted-foreground))]" x-text="formatDate(selectedWithdrawal?.created_at)"></p>
                    </div>
                    <span class="badge" :class="getStatusClass(selectedWithdrawal?.status)" x-text="selectedWithdrawal?.status"></span>
                </div>
                <div class="space-y-4">
                    <!-- Amount -->
                    <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4 text-center">
                        <p class="text-sm text-[hsl(var(--muted-foreground))]">Withdrawal Amount</p>
                        <p class="text-2xl font-bold text-[hsl(var(--primary))]" x-text="formatCurrency(selectedWithdrawal?.amount)"></p>
                    </div>

                    <!-- Merchant Info -->
                    <div class="border-t border-[hsl(var(--border))] pt-4">
                        <h5 class="font-medium mb-3">Merchant Information</h5>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-[hsl(var(--muted-foreground))]">Name:</span>
                                <p class="font-medium" x-text="selectedWithdrawal?.sub_merchant?.user?.name || '-'"></p>
                            </div>
                            <div>
                                <span class="text-[hsl(var(--muted-foreground))]">Email:</span>
                                <p class="font-medium" x-text="selectedWithdrawal?.sub_merchant?.user?.email || '-'"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Bank Details -->
                    <div class="border-t border-[hsl(var(--border))] pt-4">
                        <h5 class="font-medium mb-3">Bank Details</h5>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-[hsl(var(--muted-foreground))]">Bank:</span>
                                <span class="font-medium" x-text="selectedWithdrawal?.bank_details?.bank_name || '-'"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[hsl(var(--muted-foreground))]">Account Number:</span>
                                <span class="font-medium" x-text="selectedWithdrawal?.bank_details?.account_number || '-'"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[hsl(var(--muted-foreground))]">Account Holder:</span>
                                <span class="font-medium" x-text="selectedWithdrawal?.bank_details?.account_holder_name || '-'"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Balance Info -->
                    <template x-if="selectedWithdrawal?.sub_merchant?.balance">
                        <div class="border-t border-[hsl(var(--border))] pt-4">
                            <h5 class="font-medium mb-3">Current Balance</h5>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-[hsl(var(--muted-foreground))]">Available:</span>
                                    <p class="font-medium" x-text="formatCurrency(selectedWithdrawal?.sub_merchant?.balance?.available)"></p>
                                </div>
                                <div>
                                    <span class="text-[hsl(var(--muted-foreground))]">Total Earned:</span>
                                    <p class="font-medium" x-text="formatCurrency(selectedWithdrawal?.sub_merchant?.balance?.total_earned)"></p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Admin Notes -->
                    <template x-if="selectedWithdrawal?.admin_notes">
                        <div class="border-t border-[hsl(var(--border))] pt-4">
                            <h5 class="font-medium mb-3">Admin Notes</h5>
                            <p class="text-sm bg-[hsl(var(--muted)/0.5)] rounded-lg p-3" x-text="selectedWithdrawal?.admin_notes"></p>
                        </div>
                    </template>

                    <!-- Processed Info -->
                    <template x-if="selectedWithdrawal?.processed_at">
                        <div class="border-t border-[hsl(var(--border))] pt-4">
                            <h5 class="font-medium mb-3">Processing Info</h5>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-[hsl(var(--muted-foreground))]">Processed At:</span>
                                    <span class="font-medium" x-text="formatDate(selectedWithdrawal?.processed_at)"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-[hsl(var(--muted-foreground))]">Processed By:</span>
                                    <span class="font-medium" x-text="selectedWithdrawal?.processed_by?.name || '-'"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            <!-- Action Buttons -->
            <template x-if="selectedWithdrawal?.can_be_processed">
                <div class="p-4 border-t border-[hsl(var(--border))] flex gap-2">
                    <button @click="closeViewModal(); openApproveModal(selectedWithdrawal)" class="btn bg-emerald-500 hover:bg-emerald-600 text-white flex-1">
                        <i class="fas fa-check mr-2"></i> Approve
                    </button>
                    <button @click="closeViewModal(); openRejectModal(selectedWithdrawal)" class="btn bg-red-500 hover:bg-red-600 text-white flex-1">
                        <i class="fas fa-times mr-2"></i> Reject
                    </button>
                </div>
            </template>
        </div>
    </div>

    <!-- Approve Modal -->
    <div x-show="showApproveModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="showApproveModal" x-transition class="fixed inset-0 bg-black/50" @click="closeApproveModal()"></div>
        <div x-show="showApproveModal" x-transition class="card relative w-full max-w-md overflow-hidden">
            <div class="p-4 sm:p-6 border-b border-[hsl(var(--border))] flex items-center justify-between">
                <h3 class="text-lg font-semibold">Approve Withdrawal</h3>
                <button @click="closeApproveModal()" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-4 sm:p-6">
                <div class="mb-4 p-4 bg-emerald-50 rounded-lg">
                    <p class="text-sm text-emerald-800">You are about to approve a withdrawal of:</p>
                    <p class="text-xl font-bold text-emerald-700" x-text="formatCurrency(approveWithdrawal?.amount)"></p>
                    <p class="text-sm text-emerald-600" x-text="'To: ' + (approveWithdrawal?.bank_details?.bank_name || '') + ' - ' + (approveWithdrawal?.bank_details?.account_number || '')"></p>
                </div>
                <div class="mb-4">
                    <label class="text-sm font-medium mb-1.5 block">Admin Notes (Optional)</label>
                    <textarea x-model="approveNotes" rows="3" class="input w-full" placeholder="Add any notes about this approval..."></textarea>
                </div>
                <div class="flex gap-2">
                    <button @click="closeApproveModal()" class="btn btn-outline flex-1">Cancel</button>
                    <button @click="confirmApprove()" :disabled="processing" class="btn bg-emerald-500 hover:bg-emerald-600 text-white flex-1">
                        <i class="fas fa-check mr-2" x-show="!processing"></i>
                        <i class="fas fa-spinner fa-spin mr-2" x-show="processing"></i>
                        Approve
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div x-show="showRejectModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="showRejectModal" x-transition class="fixed inset-0 bg-black/50" @click="closeRejectModal()"></div>
        <div x-show="showRejectModal" x-transition class="card relative w-full max-w-md overflow-hidden">
            <div class="p-4 sm:p-6 border-b border-[hsl(var(--border))] flex items-center justify-between">
                <h3 class="text-lg font-semibold">Reject Withdrawal</h3>
                <button @click="closeRejectModal()" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-4 sm:p-6">
                <div class="mb-4 p-4 bg-red-50 rounded-lg">
                    <p class="text-sm text-red-800">You are about to reject a withdrawal of:</p>
                    <p class="text-xl font-bold text-red-700" x-text="formatCurrency(rejectWithdrawal?.amount)"></p>
                    <p class="text-sm text-red-600">The amount will be returned to the merchant's balance.</p>
                </div>
                <div class="mb-4">
                    <label class="text-sm font-medium mb-1.5 block">Rejection Reason <span class="text-red-500">*</span></label>
                    <textarea x-model="rejectReason" rows="3" class="input w-full" placeholder="Please provide a reason for rejection (min 10 characters)..." required></textarea>
                    <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">This reason will be shared with the merchant.</p>
                </div>
                <div class="flex gap-2">
                    <button @click="closeRejectModal()" class="btn btn-outline flex-1">Cancel</button>
                    <button @click="confirmReject()" :disabled="processing || rejectReason.length < 10" class="btn bg-red-500 hover:bg-red-600 text-white flex-1">
                        <i class="fas fa-times mr-2" x-show="!processing"></i>
                        <i class="fas fa-spinner fa-spin mr-2" x-show="processing"></i>
                        Reject
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div x-show="toast.show" x-transition class="fixed bottom-4 right-4 z-50">
        <div class="card p-4 shadow-lg" :class="toast.type === 'success' ? 'border-l-4 border-emerald-500' : 'border-l-4 border-red-500'">
            <div class="flex items-center gap-3">
                <i class="fas" :class="toast.type === 'success' ? 'fa-check-circle text-emerald-500' : 'fa-exclamation-circle text-red-500'"></i>
                <p class="text-sm" x-text="toast.message"></p>
                <button @click="toast.show = false" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i></button>
            </div>
        </div>
    </div>
</div>


<script>
function adminWithdrawalsApp() {
    return {
        API_BASE_URL: window.location.origin + '/api/admin',
        loading: true,
        processing: false,
        withdrawals: [],
        stats: {},
        statusFilter: '',
        pagination: { currentPage: 1, lastPage: 1, from: 0, to: 0, total: 0 },
        
        // Modals
        showViewModal: false,
        showApproveModal: false,
        showRejectModal: false,
        selectedWithdrawal: null,
        approveWithdrawal: null,
        rejectWithdrawal: null,
        approveNotes: '',
        rejectReason: '',
        
        // Toast
        toast: { show: false, message: '', type: 'success' },
        
        // Sidebar
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        async init() {
            this.initSidebar();
            await Promise.all([this.fetchWithdrawals(), this.fetchStats()]);
        },

        initSidebar() {
            this.isMobile = window.innerWidth < 768;
            if (this.isMobile) { this.sidebarOpen = false; }
            else { let s = localStorage.getItem('sidebarOpen'); if (s !== null) this.sidebarOpen = JSON.parse(s); }
            this.$watch('sidebarOpen', v => { if (!this.isMobile) localStorage.setItem('sidebarOpen', JSON.stringify(v)); });
            window.addEventListener('resize', () => {
                const was = this.isMobile; this.isMobile = window.innerWidth < 768;
                if (was && !this.isMobile) { let s = localStorage.getItem('sidebarOpen'); this.sidebarOpen = s !== null ? JSON.parse(s) : true; }
                else if (!was && this.isMobile) { this.sidebarOpen = false; }
            });
            let u = localStorage.getItem('user'); if (u) { try { this.user = JSON.parse(u); } catch (e) { this.user = { name: 'User' }; } } else { this.user = { name: 'User' }; }
        },

        async fetchWithdrawals() {
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const params = new URLSearchParams({ page: this.pagination.currentPage });
                if (this.statusFilter) params.append('status', this.statusFilter);
                
                const res = await fetch(`${this.API_BASE_URL}/withdrawals?${params}`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                
                if (data.success) {
                    this.withdrawals = data.data.withdrawals || [];
                    if (data.data.pagination) {
                        this.pagination = {
                            currentPage: data.data.pagination.current_page,
                            lastPage: data.data.pagination.last_page,
                            from: data.data.pagination.from || 0,
                            to: data.data.pagination.to || 0,
                            total: data.data.pagination.total
                        };
                    }
                }
            } catch (e) {
                console.error('Error fetching withdrawals:', e);
                this.showToast('Failed to load withdrawals', 'error');
            } finally {
                this.loading = false;
            }
        },

        async fetchStats() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/withdrawals/stats`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.stats = data.data.stats || {};
                }
            } catch (e) {
                console.error('Error fetching stats:', e);
            }
        },

        get paginationPages() {
            const p = [], c = this.pagination.currentPage, l = this.pagination.lastPage;
            for (let i = Math.max(1, c - 2); i <= Math.min(l, c + 2); i++) p.push(i);
            return p;
        },

        goToPage(page) {
            if (page >= 1 && page <= this.pagination.lastPage) {
                this.pagination.currentPage = page;
                this.fetchWithdrawals();
            }
        },

        viewWithdrawal(withdrawal) {
            this.selectedWithdrawal = withdrawal;
            this.showViewModal = true;
        },

        closeViewModal() {
            this.showViewModal = false;
            this.selectedWithdrawal = null;
        },

        openApproveModal(withdrawal) {
            this.approveWithdrawal = withdrawal;
            this.approveNotes = '';
            this.showApproveModal = true;
        },

        closeApproveModal() {
            this.showApproveModal = false;
            this.approveWithdrawal = null;
            this.approveNotes = '';
        },

        openRejectModal(withdrawal) {
            this.rejectWithdrawal = withdrawal;
            this.rejectReason = '';
            this.showRejectModal = true;
        },

        closeRejectModal() {
            this.showRejectModal = false;
            this.rejectWithdrawal = null;
            this.rejectReason = '';
        },

        async confirmApprove() {
            if (!this.approveWithdrawal) return;
            this.processing = true;
            
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/withdrawals/${this.approveWithdrawal.id}/approve`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ notes: this.approveNotes || null })
                });
                const data = await res.json();
                
                if (data.success) {
                    this.showToast('Withdrawal approved successfully', 'success');
                    this.closeApproveModal();
                    await Promise.all([this.fetchWithdrawals(), this.fetchStats()]);
                } else {
                    this.showToast(data.error?.message || 'Failed to approve withdrawal', 'error');
                }
            } catch (e) {
                console.error('Error approving withdrawal:', e);
                this.showToast('Failed to approve withdrawal', 'error');
            } finally {
                this.processing = false;
            }
        },

        async confirmReject() {
            if (!this.rejectWithdrawal || this.rejectReason.length < 10) return;
            this.processing = true;
            
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/withdrawals/${this.rejectWithdrawal.id}/reject`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ reason: this.rejectReason })
                });
                const data = await res.json();
                
                if (data.success) {
                    this.showToast('Withdrawal rejected successfully', 'success');
                    this.closeRejectModal();
                    await Promise.all([this.fetchWithdrawals(), this.fetchStats()]);
                } else {
                    this.showToast(data.error?.message || 'Failed to reject withdrawal', 'error');
                }
            } catch (e) {
                console.error('Error rejecting withdrawal:', e);
                this.showToast('Failed to reject withdrawal', 'error');
            } finally {
                this.processing = false;
            }
        },

        async markAsProcessed(withdrawal) {
            if (!confirm('Mark this withdrawal as processed (bank transfer completed)?')) return;
            
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/withdrawals/${withdrawal.id}/mark-processed`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                
                if (data.success) {
                    this.showToast('Withdrawal marked as processed', 'success');
                    await Promise.all([this.fetchWithdrawals(), this.fetchStats()]);
                } else {
                    this.showToast(data.error?.message || 'Failed to mark as processed', 'error');
                }
            } catch (e) {
                console.error('Error marking as processed:', e);
                this.showToast('Failed to mark as processed', 'error');
            }
        },

        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => { this.toast.show = false; }, 5000);
        },

        formatCurrency(amount) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount || 0);
        },

        formatDate(date) {
            return date ? new Date(date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
        },

        getStatusClass(status) {
            return {
                'pending': 'bg-amber-100 text-amber-700',
                'approved': 'bg-blue-100 text-blue-700',
                'processed': 'bg-emerald-100 text-emerald-700',
                'rejected': 'bg-red-100 text-red-700'
            }[status] || 'bg-gray-100 text-gray-700';
        },

        logout() {
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            window.location.href = '/login';
        },

        addNotification() {},
        clearNotifications() {},
        removeNotification() {},
        formatNotificationTime() { return ''; }
    }
}
</script>
@endsection
