<nav x-show="broadcastLastPage > 1" aria-label="{{ __('whatsapp_broadcast.table_progress') }}"
    class="flex flex-wrap items-center justify-between gap-3 border-t border-(--dashboard-divider) p-4">
    <p class="dashboard-ui-muted text-xs" x-text="broadcastCopy('page', { page: broadcastPage, total: broadcastLastPage })"></p>
    <div class="flex flex-wrap gap-2">
        <button class="btn btn-secondary" type="button" :disabled="broadcastLoading || broadcastPage <= 1" @click="showBroadcastPage(broadcastPage - 1)">{{ __('common.previous') }}</button>
        <button class="btn btn-secondary" type="button" :disabled="broadcastLoading || broadcastPage >= broadcastLastPage" @click="showBroadcastPage(broadcastPage + 1)">{{ __('common.next') }}</button>
    </div>
</nav>
