<dialog x-ref="broadcastDialog" @close="closeBroadcast()" @cancel.prevent="dismissBroadcast()"
    aria-labelledby="broadcast-title" class="dashboard-ui dashboard-ui-dialog">
    <header class="flex items-center justify-between gap-3 border-b border-(--dashboard-divider) px-5 py-4">
        <h2 id="broadcast-title" x-ref="broadcastHeading" tabindex="-1" autofocus class="dashboard-ui-title text-base font-semibold"
            x-text="broadcastCopy(broadcastView === 'compose' ? 'title' : broadcastView)"></h2>
        <button type="button" class="btn btn-ghost dashboard-ui-icon"
            aria-label="{{ __('whatsapp_broadcast.close') }}" :disabled="broadcastSubmitting" @click="dismissBroadcast()">
            <span aria-hidden="true">×</span>
        </button>
    </header>

    <div class="space-y-5 p-5">
        <div x-show="broadcastError" class="dashboard-ui-alert text-sm" data-tone="error">
            <p x-text="broadcastError" role="alert"></p>
            <button type="button" class="btn btn-secondary mt-3" x-show="broadcastLoadFailed" @click="retryBroadcast()">
                {{ __('whatsapp_broadcast.retry') }}
            </button>
        </div>
        <p x-show="broadcastLoading" role="status" class="dashboard-ui-muted flex items-center justify-center gap-2 py-9 text-sm">
            <span class="dashboard-ui-spinner text-(--dashboard-primary)" aria-hidden="true"></span>
            {{ __('whatsapp_broadcast.loading') }}
        </p>

        <template x-if="broadcastView === 'compose' && !broadcastLoading && broadcastOptions">
            <div>
                <template x-if="!broadcastOptions.templates.length">
                    <div class="dashboard-ui-empty">
                        <i class="fas fa-file-lines dashboard-ui-muted mb-3" aria-hidden="true"></i>
                        <h3 class="text-base font-semibold">{{ __('whatsapp_broadcast.empty_templates') }}</h3>
                        <p class="dashboard-ui-muted mt-1 text-sm">{{ __('whatsapp_broadcast.empty_templates_hint') }}</p>
                    </div>
                </template>
                <template x-if="broadcastOptions.templates.length">
                    <form class="space-y-6" @submit.prevent="submitBroadcast()">
                        <div class="dashboard-ui-panel">
                            <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                <div>
                                    <dt class="dashboard-ui-muted text-xs">{{ __('whatsapp_broadcast.eligible') }}</dt>
                                    <dd class="dashboard-ui-title text-2xl font-bold tabular-nums" x-text="broadcastCounts.eligible"></dd>
                                </div>
                                <div>
                                    <dt class="dashboard-ui-muted text-xs">{{ __('whatsapp_broadcast.skipped') }}</dt>
                                    <dd class="dashboard-ui-title text-2xl font-bold tabular-nums" x-text="broadcastCounts.skipped"></dd>
                                </div>
                                <div class="col-span-2 sm:col-span-1">
                                    <dt class="dashboard-ui-muted text-xs">{{ __('whatsapp_broadcast.quota') }}</dt>
                                    <dd class="dashboard-ui-title text-2xl font-bold tabular-nums" x-text="broadcastOptions.quota.remaining ?? broadcastCopy('unlimited')"></dd>
                                </div>
                            </dl>
                            <p class="dashboard-ui-muted mt-3 text-xs">{{ __('whatsapp_broadcast.quota_help') }}</p>
                        </div>
                        <p x-show="broadcastOverLimit && !broadcastError" x-text="broadcastLimitMessage" role="alert"
                            class="dashboard-ui-alert text-sm" data-tone="error"></p>

                        <fieldset class="min-w-0 space-y-4" :disabled="broadcastSubmitting">
                            <label class="block text-sm font-medium">
                                <span class="mb-1.5 block">{{ __('whatsapp_broadcast.template') }} <span class="dashboard-ui-required" aria-hidden="true">*</span></span>
                                <select x-model="broadcastTemplateId" @change="broadcastVariables = {}" class="input w-full" required>
                                    <option value="">{{ __('whatsapp_broadcast.choose_template') }}</option>
                                    <template x-for="item in broadcastOptions.templates" :key="item.id">
                                        <option :value="item.id" x-text="`${item.name} · ${item.language} · ${item.category}`"></option>
                                    </template>
                                </select>
                            </label>

                            <template x-if="broadcastTemplate">
                                <div class="space-y-4">
                                    <template x-for="section in broadcastTemplate.sections.filter(item => item.variables.length)" :key="section.type">
                                        <fieldset class="min-w-0">
                                            <legend class="mb-2 text-sm font-semibold" x-text="broadcastCopy(`${section.type}_variables`)"></legend>
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <template x-for="key in section.variables" :key="key">
                                                    <label class="block min-w-0 text-sm font-medium">
                                                        <span class="mb-1.5 block"><span x-text="key"></span> <span class="dashboard-ui-required" aria-hidden="true">*</span></span>
                                                        <input class="input w-full" type="text" required
                                                            :maxlength="section.type === 'header' ? 60 : 1024"
                                                            :value="broadcastVariable(section.type, key)"
                                                            @input="setBroadcastVariable(section.type, key, $event.target.value)">
                                                    </label>
                                                </template>
                                            </div>
                                        </fieldset>
                                    </template>
                                    <section class="dashboard-ui-panel space-y-2">
                                        <h3 class="text-sm font-semibold">{{ __('whatsapp_broadcast.preview') }}</h3>
                                        <p x-show="broadcastPreview.header" x-text="broadcastPreview.header" class="whitespace-pre-wrap break-words text-sm font-semibold"></p>
                                        <p x-text="broadcastPreview.body" class="whitespace-pre-wrap break-words text-sm"></p>
                                        <p x-show="broadcastPreview.footer" x-text="broadcastPreview.footer" class="dashboard-ui-muted whitespace-pre-wrap break-words text-xs"></p>
                                    </section>
                                </div>
                            </template>

                            <label class="flex items-start gap-2 text-sm">
                                <input type="checkbox" class="dashboard-ui-check mt-0.5" x-model="broadcastConsent" required>
                                <span>{{ __('whatsapp_broadcast.consent') }} <span class="dashboard-ui-required" aria-hidden="true">*</span></span>
                            </label>
                            <p class="dashboard-ui-muted text-xs">
                                {{ __('whatsapp_broadcast.risk') }}
                                <a class="dashboard-ui-link" href="https://business.whatsapp.com/policy" target="_blank" rel="noopener noreferrer">{{ __('whatsapp_broadcast.policy') }}</a>
                            </p>
                        </fieldset>

                        <footer class="flex flex-wrap justify-end gap-2 border-t border-(--dashboard-divider) pt-4">
                            <button type="button" class="btn btn-secondary" :disabled="broadcastSubmitting" @click="dismissBroadcast()">{{ __('whatsapp_broadcast.cancel') }}</button>
                            <button type="submit" class="btn btn-primary"
                                :disabled="broadcastSubmitting || !broadcastTemplate || !broadcastCounts.eligible || broadcastOverLimit || !broadcastConsent">
                                <span x-show="broadcastSubmitting" class="dashboard-ui-spinner" aria-hidden="true"></span>
                                <span x-text="broadcastCopy(broadcastSubmitting ? 'submitting' : 'submit')"></span>
                            </button>
                        </footer>
                    </form>
                </template>
            </div>
        </template>

        <template x-if="broadcastView === 'history' && !broadcastLoading">
            <div>
                <div x-show="!broadcastHistory.length && !broadcastError" class="dashboard-ui-empty">
                    <i class="fas fa-inbox dashboard-ui-muted mb-3" aria-hidden="true"></i>
                    <h3 class="text-base font-semibold">{{ __('whatsapp_broadcast.empty_history') }}</h3>
                    <p class="dashboard-ui-muted mt-1 text-sm">{{ __('whatsapp_broadcast.empty_history_hint') }}</p>
                </div>
                <div x-show="broadcastHistory.length" class="dashboard-ui-card">
                    <div role="region" aria-label="{{ __('whatsapp_broadcast.history') }}" tabindex="0" class="overflow-x-auto rounded-[inherit]">
                        <table class="dashboard-ui-table min-w-[32rem]">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('whatsapp_broadcast.table_template') }}</th>
                                    <th scope="col">{{ __('whatsapp_broadcast.table_date') }}</th>
                                    <th scope="col">{{ __('whatsapp_broadcast.table_progress') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in broadcastHistory" :key="item.id">
                                    <tr>
                                        <th scope="row" class="font-semibold">
                                            <button type="button" class="dashboard-ui-link break-all text-left" @click="viewBroadcast(item.id)" x-text="item.template_name"></button>
                                        </th>
                                        <td><time class="dashboard-ui-muted text-xs" :datetime="item.created_at" x-text="broadcastDate(item.created_at)"></time></td>
                                        <td><p class="dashboard-ui-muted text-xs" x-text="broadcastProgress(item)"></p></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    @include('dashboard.partials.whatsapp-broadcast-pagination')
                </div>
            </div>
        </template>

        <template x-if="broadcastView === 'detail'">
            <div class="space-y-4">
                <button type="button" class="btn btn-ghost" @click="loadBroadcastHistory()">{{ __('whatsapp_broadcast.back') }}</button>
                <template x-if="broadcastDetail">
                    <div class="space-y-4">
                        <div>
                            <h3 class="dashboard-ui-title text-base font-semibold" x-text="broadcastDetail.template_name"></h3>
                            <p class="dashboard-ui-muted mt-1 text-xs" role="status" x-text="broadcastProgress(broadcastDetail)"></p>
                        </div>
                        <p x-show="broadcastDetail.stop_reason" class="dashboard-ui-alert text-sm" data-tone="warning" x-text="broadcastReason(broadcastDetail.stop_reason)"></p>
                        <div class="dashboard-ui-card">
                            <div role="region" aria-label="{{ __('whatsapp_broadcast.table_recipient') }}" tabindex="0" class="overflow-x-auto rounded-[inherit]">
                                <table class="dashboard-ui-table min-w-[22rem]">
                                    <thead>
                                        <tr>
                                            <th scope="col">{{ __('whatsapp_broadcast.table_recipient') }}</th>
                                            <th scope="col">{{ __('whatsapp_broadcast.table_status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr x-show="!broadcastRecipients.length">
                                            <td colspan="2" class="dashboard-ui-muted">{{ __('whatsapp_broadcast.no_recipients') }}</td>
                                        </tr>
                                        <template x-for="recipient in broadcastRecipients" :key="recipient.id">
                                            <tr>
                                                <td class="whitespace-nowrap tabular-nums" x-text="'+' + recipient.wa_id"></td>
                                                <td>
                                                    <span class="dashboard-ui-badge" :data-tone="broadcastStatusTone(recipient.status)" x-text="broadcastStatus(recipient.status)"></span>
                                                    <p x-show="recipient.reason" class="dashboard-ui-muted mt-1 text-xs" x-text="broadcastReason(recipient.reason)"></p>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            @include('dashboard.partials.whatsapp-broadcast-pagination')
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>
</dialog>


<script>
function contactsBroadcast() {
    const copy = @js(__('whatsapp_broadcast'));
    const locale = @js(app()->getLocale());

    return {
        broadcastSelection: {},
        broadcastOptions: null,
        broadcastTemplateId: '',
        broadcastVariables: {},
        broadcastConsent: false,
        broadcastView: 'compose',
        broadcastError: '',
        broadcastLoading: false,
        broadcastLoadFailed: false,
        broadcastSubmitting: false,
        broadcastAttempts: new Map(),
        broadcastHistory: [],
        broadcastDetail: null,
        broadcastDetailId: null,
        broadcastRecipients: [],
        broadcastPage: 1,
        broadcastLastPage: 1,
        broadcastTimer: null,
        broadcastRequest: 0,

        broadcastCopy(key, values = {}) {
            return copy[key].replace(/:(\w+)/g, (placeholder, name) => values[name] ?? placeholder);
        },

        broadcastDate(date) {
            return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(date));
        },

        get broadcastPageSelected() {
            return this.contacts.length > 0 && this.contacts.every(contact => contact.id in this.broadcastSelection);
        },

        get broadcastCounts() {
            const numbers = new Map();
            for (const contact of Object.values(this.broadcastSelection)) {
                numbers.set(contact.waId, Boolean(numbers.get(contact.waId) || contact.optedOut));
            }
            const skipped = [...numbers.values()].filter(Boolean).length;
            return { total: numbers.size, eligible: numbers.size - skipped, skipped };
        },

        get broadcastTemplate() {
            return this.broadcastOptions?.templates.find(item => item.id === this.broadcastTemplateId);
        },

        get broadcastPreview() {
            return Object.fromEntries((this.broadcastTemplate?.sections ?? []).map(section => [
                section.type,
                section.text.replace(/\{\{\s*([a-z][a-z0-9_]*|[1-9][0-9]*)\s*\}\}/g,
                    (placeholder, key) => this.broadcastVariable(section.type, key) || placeholder),
            ]));
        },

        get broadcastOverLimit() {
            const remaining = this.broadcastOptions?.quota.remaining;
            return remaining != null && this.broadcastCounts.eligible > remaining;
        },

        get broadcastLimitMessage() {
            return this.broadcastCopy('over_limit', {
                count: this.broadcastCounts.eligible,
                remaining: this.broadcastOptions?.quota.remaining,
            });
        },

        selectBroadcastContact(contact, selected) {
            if (selected) {
                this.broadcastSelection[contact.id] = {
                    waId: contact.wa_id.replace(/^\+/, ''),
                    optedOut: contact.broadcast_opted_out,
                };
            } else {
                delete this.broadcastSelection[contact.id];
            }
        },

        selectBroadcastPage(selected) {
            this.contacts.forEach(contact => this.selectBroadcastContact(contact, selected));
        },

        setBroadcastVariable(type, key, value) {
            this.broadcastVariables[type] = { ...this.broadcastVariables[type], [key]: value };
        },

        broadcastVariable(type, key) {
            const values = this.broadcastVariables[type] ?? {};
            return Object.hasOwn(values, key) ? values[key] : '';
        },

        async broadcastApi(path = '', options = {}) {
            const response = await fetch(`${this.API_BASE_URL}/whatsapp/broadcasts${path}`, {
                ...options,
                headers: {
                    Authorization: `Bearer ${localStorage.getItem('token')}`,
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
            }).catch(() => { throw new Error(copy.error); });
            const body = await response.json().catch(() => null);
            if (!body) throw new Error(copy.error);
            if (!response.ok) {
                const message = body.error_code === 'BROADCAST_LIMIT_EXCEEDED'
                    ? this.broadcastCopy('over_limit', { count: body.recipient_count, remaining: body.remaining_quota })
                    : copy.errors[body.error_code];
                const validation = Object.values(body.errors ?? {}).flat()[0];
                const fallback = locale === 'id' ? validation || body.message : null;
                throw Object.assign(new Error(message || fallback || copy.error), { body });
            }
            return body;
        },

        async openBroadcast(view) {
            this.broadcastView = view;
            this.$refs.broadcastDialog.showModal();
            if (view === 'history') {
                await this.loadBroadcastHistory();
                return;
            }

            this.broadcastOptions = null;
            this.broadcastTemplateId = '';
            this.broadcastVariables = {};
            this.broadcastConsent = false;
            await this.loadBroadcastOptions();
        },

        loadBroadcastOptions() {
            return this.loadBroadcastData('/options', (result) => { this.broadcastOptions = result.data; });
        },

        retryBroadcast() {
            return this.broadcastView === 'compose' ? this.loadBroadcastOptions() : this.showBroadcastPage(this.broadcastPage);
        },

        showBroadcastPage(page) {
            return this.broadcastView === 'history' ? this.loadBroadcastHistory(page) : this.viewBroadcast(this.broadcastDetailId, page);
        },

        dismissBroadcast() {
            if (!this.broadcastSubmitting) this.$refs.broadcastDialog.close();
        },

        closeBroadcast() {
            clearTimeout(this.broadcastTimer);
            this.broadcastRequest++;
        },

        async loadBroadcastData(path, apply, silent = false) {
            clearTimeout(this.broadcastTimer);
            const request = ++this.broadcastRequest;
            if (!silent) this.broadcastLoading = true;
            this.broadcastError = '';
            this.broadcastLoadFailed = false;
            try {
                const result = await this.broadcastApi(path);
                if (request === this.broadcastRequest) apply(result);
            } catch (error) {
                if (request === this.broadcastRequest) {
                    this.broadcastError = error.message;
                    this.broadcastLoadFailed = true;
                }
            } finally {
                if (request === this.broadcastRequest) this.broadcastLoading = false;
            }
            return request === this.broadcastRequest && this.$refs.broadcastDialog.open;
        },

        async loadBroadcastHistory(page = 1) {
            this.broadcastView = 'history';
            this.broadcastPage = page;
            this.broadcastHistory = [];
            this.$nextTick(() => this.$refs.broadcastHeading?.focus());
            await this.loadBroadcastData(`?page=${page}`, (result) => {
                this.broadcastHistory = result.data;
                this.broadcastPage = result.current_page;
                this.broadcastLastPage = result.last_page;
            });
        },

        async viewBroadcast(id, page = 1, silent = false) {
            this.broadcastView = 'detail';
            this.broadcastDetailId = id;
            this.broadcastPage = page;
            if (!silent) {
                this.broadcastDetail = null;
                this.$nextTick(() => this.$refs.broadcastHeading?.focus());
            }
            const active = await this.loadBroadcastData(`/${id}?page=${page}`, (result) => {
                this.broadcastDetail = result.data;
                this.broadcastRecipients = result.recipients.data;
                this.broadcastPage = result.recipients.current_page;
                this.broadcastLastPage = result.recipients.last_page;
            }, silent);
            if (active) this.broadcastTimer = setTimeout(() => this.viewBroadcast(id, page, true), 5000);
        },

        async submitBroadcast() {
            if (this.broadcastSubmitting || !this.broadcastTemplate || !this.broadcastConsent || this.broadcastOverLimit || !this.broadcastCounts.eligible) return;
            this.broadcastSubmitting = true;
            this.broadcastError = '';
            this.broadcastLoadFailed = false;
            try {
                const variables = {};
                for (const section of this.broadcastTemplate.sections) {
                    if (section.variables.length) {
                        variables[section.type] = Object.fromEntries(section.variables.map(key => [
                            key, this.broadcastVariable(section.type, key).trim(),
                        ]));
                    }
                }
                const draft = {
                    contact_ids: Object.keys(this.broadcastSelection).map(Number).sort((a, b) => a - b),
                    template_id: this.broadcastTemplateId,
                    variables,
                    consent_confirmed: true,
                };
                const fingerprint = JSON.stringify([this.broadcastOptions.account_id, draft]);
                if (!this.broadcastAttempts.has(fingerprint)) this.broadcastAttempts.set(fingerprint, crypto.randomUUID());

                const result = await this.broadcastApi('', {
                    method: 'POST',
                    body: JSON.stringify({ ...draft, idempotency_key: this.broadcastAttempts.get(fingerprint) }),
                });
                this.broadcastAttempts.delete(fingerprint);
                this.broadcastSelection = {};
                await this.viewBroadcast(result.data.id);
            } catch (error) {
                if (error.body?.error_code === 'BROADCAST_LIMIT_EXCEEDED') {
                    this.broadcastOptions.quota.remaining = error.body.remaining_quota;
                }
                this.broadcastError = error.message;
            } finally {
                this.broadcastSubmitting = false;
            }
        },

        broadcastProgress(item) {
            return ['queued', 'accepted', 'delivered', 'failed', 'skipped', 'review']
                .map(status => `${item[`${status}_count`]} ${this.broadcastStatus(status)}`)
                .join(' · ');
        },

        broadcastStatus(status) {
            return copy.statuses[status] || status;
        },

        broadcastStatusTone(status) {
            return ({ sending: 'info', accepted: 'info', delivered: 'success', read: 'success', review: 'warning', failed: 'danger' })[status];
        },

        broadcastReason(reason) {
            return copy.reasons[reason] || reason;
        },
    };
}
</script>
