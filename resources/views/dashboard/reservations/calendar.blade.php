@extends('layouts.app')

@section('title', 'Kalender Reservasi')

@section('content')
<div x-data="calendarApp()" class="h-screen flex bg-[hsl(var(--background))] overflow-hidden">
    <!-- Sidebar -->
    @include('components.dashboard-sidebar', ['activePage' => 'reservations-calendar'])

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden" :class="{ 'lg:ml-0': true }">
        <!-- Calendar Toolbar -->
        <div class="flex-shrink-0 flex items-center justify-between px-4 py-2 border-b border-[hsl(var(--border))] bg-[hsl(var(--background))]">
            <div class="flex items-center gap-3">
                <button @click="goToday()" class="px-4 py-1.5 text-sm font-medium border border-[hsl(var(--border))] rounded-md hover:bg-[hsl(var(--muted)/0.5)] transition-colors">
                    Hari Ini
                </button>
                <button @click="goPrev()" class="p-1.5 rounded-full hover:bg-[hsl(var(--muted)/0.5)] transition-colors">
                    <svg class="w-5 h-5 text-[hsl(var(--foreground)/0.7)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button @click="goNext()" class="p-1.5 rounded-full hover:bg-[hsl(var(--muted)/0.5)] transition-colors">
                    <svg class="w-5 h-5 text-[hsl(var(--foreground)/0.7)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
                <h1 class="text-xl font-normal text-[hsl(var(--foreground))] ml-2" x-text="headerTitle"></h1>
            </div>
            <div class="flex items-center gap-2">
                <!-- Loading indicator -->
                <div x-show="loading" class="mr-2">
                    <i class="fas fa-circle-notch fa-spin text-[hsl(var(--primary))]"></i>
                </div>
                <!-- View label -->
                <span class="px-3 py-1.5 text-sm font-medium bg-[hsl(var(--primary))] text-white rounded-md">
                    Minggu
                </span>
            </div>
        </div>

        <!-- ==================== WEEK VIEW ==================== -->
        <template x-if="viewMode === 'week'">
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Day headers (syncs horizontal scroll with grid) -->
                <div class="flex-shrink-0 border-b border-[hsl(var(--border))] overflow-x-hidden" x-ref="weekHeader">
                    <div class="min-w-[700px]">
                        <div class="grid" :style="'grid-template-columns: 60px repeat(7, 1fr)'">
                            <!-- Timezone label -->
                            <div class="text-[10px] text-[hsl(var(--muted-foreground))] text-right pr-2 pt-2 border-r border-[hsl(var(--border))]" x-text="timezoneLabel"></div>
                            <!-- Day headers -->
                            <template x-for="(day, i) in weekDays" :key="i">
                                <div class="text-center py-2 border-r border-[hsl(var(--border))] last:border-r-0"
                                     @click="viewDayDetail(day)">
                                    <div class="text-[11px] font-medium tracking-wide uppercase"
                                         :class="day.isToday ? 'text-[hsl(var(--primary))]' : 'text-[hsl(var(--muted-foreground))]'"
                                         x-text="day.dayLabel"></div>
                                    <div class="mt-0.5 w-10 h-10 mx-auto flex items-center justify-center rounded-full text-2xl font-normal cursor-pointer transition-colors"
                                         :class="day.isToday ? 'bg-[hsl(var(--primary))] text-white' : 'text-[hsl(var(--foreground))] hover:bg-[hsl(var(--muted)/0.5)]'"
                                         x-text="day.dateNum"></div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Time grid (scrollable both directions) -->
                <div class="flex-1 overflow-auto" x-ref="weekGrid"
                     @scroll="$refs.weekHeader && ($refs.weekHeader.scrollLeft = $el.scrollLeft)">
                    <div class="min-w-[700px]">
                        <div class="grid relative" :style="'grid-template-columns: 60px repeat(7, 1fr); grid-template-rows: repeat(24, minmax(48px, 1fr));'">
                            <!-- Time labels column -->
                            <template x-for="hour in hours" :key="'label-'+hour">
                                <div class="relative border-r border-[hsl(var(--border))] border-b border-b-[hsl(var(--border)/0.5)] flex items-start justify-end pr-2 pt-0" :style="'grid-column: 1; grid-row: ' + (hour + 1)">
                                    <span class="text-[10px] text-[hsl(var(--muted-foreground))] -mt-1.5 leading-none" x-text="formatHourLabel(hour)"></span>
                                </div>
                            </template>

                            <!-- Day columns -->
                            <template x-for="(day, di) in weekDays" :key="'col-'+di">
                                <template x-for="hour in hours" :key="'cell-'+di+'-'+hour">
                                    <div class="border-r border-[hsl(var(--border))] border-b border-b-[hsl(var(--border)/0.5)]"
                                         :class="day.isToday ? 'bg-[hsl(var(--primary)/0.02)]' : ''"
                                         :style="'grid-column: ' + (di + 2) + '; grid-row: ' + (hour + 1)"></div>
                                </template>
                            </template>

                            <!-- Events overlay (positioned over grid) -->
                            <template x-for="(day, di) in weekDays" :key="'ev-'+di">
                                <template x-for="ev in getEventsForDay(day.dateStr)" :key="ev.id">
                                    <div class="rounded px-1.5 py-0.5 text-xs overflow-hidden cursor-pointer border-l-[3px] transition-opacity hover:opacity-90 z-10"
                                         :style="getEventGridStyle(ev, di)"
                                         @click.stop="viewEventDetail(ev)">
                                        <div class="font-medium truncate text-white text-[11px] leading-tight" x-text="ev.title"></div>
                                    </div>
                                </template>
                            </template>

                            <!-- Current time red line -->
                            <template x-for="(day, di) in weekDays" :key="'now-'+di">
                                <template x-if="day.isToday">
                                    <div class="z-20 pointer-events-none flex items-center"
                                         :style="getCurrentTimeGridStyle(di)">
                                        <div class="w-2.5 h-2.5 rounded-full bg-red-500 -ml-1 flex-shrink-0"></div>
                                        <div class="flex-1 h-[2px] bg-red-500"></div>
                                    </div>
                                </template>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- ==================== MONTH VIEW ==================== -->
        <template x-if="viewMode === 'month'">
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Day-of-week headers -->
                <div class="flex-shrink-0 grid grid-cols-7 border-b border-[hsl(var(--border))]">
                    <template x-for="d in ['MIN','SEN','SEL','RAB','KAM','JUM','SAB']" :key="d">
                        <div class="text-center py-2 text-[11px] font-medium tracking-wide text-[hsl(var(--muted-foreground))] border-r border-[hsl(var(--border))] last:border-r-0" x-text="d"></div>
                    </template>
                </div>

                <!-- Month grid -->
                <div class="flex-1 grid grid-cols-7 auto-rows-fr overflow-y-auto">
                    <template x-for="(day, index) in monthDays" :key="index">
                        <div class="min-h-[90px] border-r border-b border-[hsl(var(--border))] p-1.5 cursor-pointer transition-colors"
                             :class="{
                                 'bg-[hsl(var(--primary)/0.03)]': day.isToday,
                                 'opacity-40': !day.inMonth
                             }"
                             @click="viewDayDetail(day)">
                            <div class="flex justify-center mb-1">
                                <span class="w-7 h-7 flex items-center justify-center rounded-full text-sm"
                                      :class="day.isToday ? 'bg-[hsl(var(--primary))] text-white font-medium' : 'text-[hsl(var(--foreground))]'"
                                      x-text="day.dateNum"></span>
                            </div>
                            <div class="space-y-0.5">
                                <template x-for="ev in getEventsForDay(day.dateStr).slice(0, 3)" :key="ev.id">
                                    <div class="text-[10px] px-1.5 py-0.5 rounded truncate text-white font-medium"
                                         :style="'background-color:' + (ev.backgroundColor || '#6366f1')"
                                         x-text="ev.title"></div>
                                </template>
                                <template x-if="getEventsForDay(day.dateStr).length > 3">
                                    <div class="text-[10px] text-[hsl(var(--muted-foreground))] font-medium px-1" x-text="'+' + (getEventsForDay(day.dateStr).length - 3) + ' lagi'"></div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
</div>

<!-- Event Detail Modal -->
<div x-show="eventModal.show" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     @click.self="eventModal.show = false">
    <div class="bg-[hsl(var(--background))] rounded-xl shadow-2xl max-w-lg w-full overflow-hidden border border-[hsl(var(--border))]"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         @click.stop>
        <div class="p-5">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-[hsl(var(--foreground))]" x-text="eventModal.data?.title || 'Reservasi'"></h3>
                    <p class="text-sm text-[hsl(var(--muted-foreground))] mt-0.5" x-text="eventModal.dateDisplay"></p>
                </div>
                <button @click="eventModal.show = false" class="p-1 rounded-md hover:bg-[hsl(var(--muted)/0.5)] transition-colors">
                    <svg class="w-5 h-5 text-[hsl(var(--muted-foreground))]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <template x-if="eventModal.data">
                <div class="space-y-3">
                    <div class="flex items-center gap-2.5 text-sm">
                        <span class="px-2.5 py-1 rounded-full text-xs font-medium text-white"
                              :style="'background-color:' + (eventModal.data.backgroundColor || '#6366f1')"
                              x-text="eventModal.data.extendedProps?.status === 'confirmed' ? 'Confirmed' : (eventModal.data.extendedProps?.status === 'completed' ? 'Completed' : eventModal.data.extendedProps?.status)"></span>
                    </div>
                    <div class="grid gap-2 text-sm text-[hsl(var(--foreground)/0.8)]">
                        <div class="flex items-center gap-2" x-show="eventModal.data.extendedProps?.customer_name">
                            <i class="fas fa-user w-4 text-center text-[hsl(var(--muted-foreground))]"></i>
                            <span x-text="eventModal.data.extendedProps?.customer_name"></span>
                        </div>
                        <div class="flex items-center gap-2" x-show="eventModal.data.extendedProps?.customer_phone">
                            <i class="fas fa-phone w-4 text-center text-[hsl(var(--muted-foreground))]"></i>
                            <span x-text="eventModal.data.extendedProps?.customer_phone"></span>
                        </div>
                        <div class="flex items-center gap-2" x-show="eventModal.data.extendedProps?.guest_count">
                            <i class="fas fa-users w-4 text-center text-[hsl(var(--muted-foreground))]"></i>
                            <span x-text="(eventModal.data.extendedProps?.guest_count || 0) + ' tamu'"></span>
                        </div>
                        <div class="flex items-center gap-2" x-show="eventModal.data.extendedProps?.table_name">
                            <i class="fas fa-chair w-4 text-center text-[hsl(var(--muted-foreground))]"></i>
                            <span x-text="eventModal.data.extendedProps?.table_name"></span>
                        </div>
                        <div class="flex items-center gap-2" x-show="eventModal.data.extendedProps?.order_id">
                            <i class="fas fa-receipt w-4 text-center text-[hsl(var(--muted-foreground))]"></i>
                            <span class="font-mono text-xs" x-text="eventModal.data.extendedProps?.order_id"></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<!-- Day Detail Modal -->
<div x-show="dayDetailModal.show" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     @click.self="dayDetailModal.show = false">
    <div class="bg-[hsl(var(--background))] rounded-xl shadow-2xl max-w-2xl w-full max-h-[85vh] overflow-y-auto border border-[hsl(var(--border))]"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         @click.stop>
        <div class="p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-[hsl(var(--foreground))]">
                    Reservasi — <span x-text="dayDetailModal.dateDisplay"></span>
                </h3>
                <button @click="dayDetailModal.show = false" class="p-1 rounded-md hover:bg-[hsl(var(--muted)/0.5)] transition-colors">
                    <svg class="w-5 h-5 text-[hsl(var(--muted-foreground))]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <template x-if="dayDetailModal.events.length === 0">
                <div class="text-center py-10 text-[hsl(var(--muted-foreground))]">
                    <i class="fas fa-calendar-times text-3xl mb-2 opacity-40"></i>
                    <p class="text-sm">Tidak ada reservasi pada tanggal ini</p>
                </div>
            </template>

            <div class="space-y-2">
                <template x-for="ev in dayDetailModal.events" :key="ev.id">
                    <div class="flex items-center gap-3 p-3 rounded-lg border border-[hsl(var(--border))] hover:bg-[hsl(var(--muted)/0.3)] transition-colors cursor-pointer"
                         @click="viewEventDetail(ev); dayDetailModal.show = false">
                        <div class="w-1 self-stretch rounded-full" :style="'background-color:' + (ev.backgroundColor || '#6366f1')"></div>
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-sm text-[hsl(var(--foreground))] truncate" x-text="ev.title"></div>
                            <div class="text-xs text-[hsl(var(--muted-foreground))] mt-0.5" x-text="ev.extendedProps?.time_display || ''"></div>
                        </div>
                        <span class="flex-shrink-0 px-2 py-0.5 rounded-full text-[10px] font-medium text-white"
                              :style="'background-color:' + (ev.backgroundColor || '#6366f1')"
                              x-text="ev.extendedProps?.status === 'confirmed' ? 'Confirmed' : 'Completed'"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
function calendarApp() {
    return {
        // Dashboard base properties
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        // Calendar state
        loading: false,
        events: [],
        viewMode: 'week', // 'week' or 'month'
        currentDate: new Date(),
        headerTitle: '',
        timezoneLabel: '',

        // Week view
        weekDays: [],
        hours: [],

        // Month view
        monthDays: [],

        // Modals
        eventModal: { show: false, data: null, dateDisplay: '' },
        dayDetailModal: { show: false, events: [], dateDisplay: '' },

        async init() {
            // Dashboard base init
            this.isMobile = window.innerWidth < 768;
            if (this.isMobile) {
                this.sidebarOpen = false;
            } else {
                let savedState = localStorage.getItem('sidebarOpen');
                if (savedState !== null) this.sidebarOpen = JSON.parse(savedState);
            }

            this.$watch('sidebarOpen', v => {
                if (!this.isMobile) localStorage.setItem('sidebarOpen', JSON.stringify(v));
            });

            let resizeTimeout;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    const wasMobile = this.isMobile;
                    this.isMobile = window.innerWidth < 768;
                    if (wasMobile && !this.isMobile) {
                        let savedState = localStorage.getItem('sidebarOpen');
                        this.sidebarOpen = savedState !== null ? JSON.parse(savedState) : true;
                    } else if (!wasMobile && this.isMobile) {
                        this.sidebarOpen = false;
                    }
                }, 150);
            });

            let storedUser = localStorage.getItem('user');
            if (storedUser) {
                try { this.user = JSON.parse(storedUser); }
                catch (e) { this.user = { name: 'User', email: 'user@example.com' }; }
            } else {
                this.user = { name: 'User', email: 'user@example.com' };
            }

            let savedNotifs = localStorage.getItem('notifications');
            if (savedNotifs) {
                try { this.notifications = JSON.parse(savedNotifs); } catch (e) { this.notifications = []; }
            }

            // Calendar init
            this.timezoneLabel = 'GMT+07 (WIB)';
            this.hours = Array.from({ length: 24 }, (_, i) => i);
            this.buildView();
            await this.loadEvents();

            // Scroll to current hour on load
            this.$nextTick(() => this.scrollToCurrentTime());

            // Current time line updates every minute (forces re-render)
            setInterval(() => { this.currentDate = new Date(this.currentDate); }, 60000);
        },

        getTimezoneOffset() {
            const offset = -(new Date().getTimezoneOffset());
            const sign = offset >= 0 ? '+' : '-';
            const h = String(Math.floor(Math.abs(offset) / 60)).padStart(2, '0');
            return sign + h;
        },

        buildView() {
            if (this.viewMode === 'week') {
                this.buildWeekView();
            } else {
                this.buildMonthView();
            }
            this.updateHeaderTitle();
        },

        buildWeekView() {
            const dayNames = ['MIN', 'SEN', 'SEL', 'RAB', 'KAM', 'JUM', 'SAB'];
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            // Get Sunday of current week
            const start = new Date(this.currentDate);
            start.setDate(start.getDate() - start.getDay());
            start.setHours(0, 0, 0, 0);

            this.weekDays = [];
            for (let i = 0; i < 7; i++) {
                const d = new Date(start);
                d.setDate(d.getDate() + i);
                const dateStr = this.toDateStr(d);
                this.weekDays.push({
                    dateObj: d,
                    dateStr: dateStr,
                    dateNum: d.getDate(),
                    dayLabel: dayNames[d.getDay()],
                    isToday: d.toDateString() === today.toDateString()
                });
            }
        },

        buildMonthView() {
            const year = this.currentDate.getFullYear();
            const month = this.currentDate.getMonth();
            const firstDay = new Date(year, month, 1);
            const startDate = new Date(firstDay);
            startDate.setDate(startDate.getDate() - firstDay.getDay());

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            this.monthDays = [];
            for (let i = 0; i < 42; i++) {
                const d = new Date(startDate);
                d.setDate(d.getDate() + i);
                this.monthDays.push({
                    dateObj: d,
                    dateStr: this.toDateStr(d),
                    dateNum: d.getDate(),
                    inMonth: d.getMonth() === month,
                    isToday: d.toDateString() === today.toDateString()
                });
            }
        },

        updateHeaderTitle() {
            const months = ['January', 'February', 'March', 'April', 'May', 'June',
                            'July', 'August', 'September', 'October', 'November', 'December'];
            if (this.viewMode === 'week' && this.weekDays.length) {
                const first = this.weekDays[0].dateObj;
                const last = this.weekDays[6].dateObj;
                if (first.getMonth() === last.getMonth()) {
                    this.headerTitle = `${months[first.getMonth()]} ${first.getFullYear()}`;
                } else if (first.getFullYear() === last.getFullYear()) {
                    this.headerTitle = `${months[first.getMonth()]} – ${months[last.getMonth()]} ${first.getFullYear()}`;
                } else {
                    this.headerTitle = `${months[first.getMonth()]} ${first.getFullYear()} – ${months[last.getMonth()]} ${last.getFullYear()}`;
                }
            } else {
                this.headerTitle = `${months[this.currentDate.getMonth()]} ${this.currentDate.getFullYear()}`;
            }
        },

        switchView(mode) {
            this.viewMode = mode;
            this.buildView();
            if (mode === 'week') {
                this.$nextTick(() => this.scrollToCurrentTime());
            }
        },

        goToday() {
            this.currentDate = new Date();
            this.buildView();
            this.loadEvents();
            if (this.viewMode === 'week') {
                this.$nextTick(() => this.scrollToCurrentTime());
            }
        },

        goPrev() {
            if (this.viewMode === 'week') {
                this.currentDate.setDate(this.currentDate.getDate() - 7);
            } else {
                this.currentDate.setMonth(this.currentDate.getMonth() - 1);
            }
            this.currentDate = new Date(this.currentDate);
            this.buildView();
            this.loadEvents();
        },

        goNext() {
            if (this.viewMode === 'week') {
                this.currentDate.setDate(this.currentDate.getDate() + 7);
            } else {
                this.currentDate.setMonth(this.currentDate.getMonth() + 1);
            }
            this.currentDate = new Date(this.currentDate);
            this.buildView();
            this.loadEvents();
        },

        async loadEvents() {
            this.loading = true;
            try {
                let startDate, endDate;
                if (this.viewMode === 'week' && this.weekDays.length) {
                    startDate = this.weekDays[0].dateStr;
                    endDate = this.weekDays[6].dateStr;
                } else if (this.monthDays.length) {
                    startDate = this.monthDays[0].dateStr;
                    endDate = this.monthDays[this.monthDays.length - 1].dateStr;
                } else {
                    startDate = this.toDateStr(new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), 1));
                    endDate = this.toDateStr(new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 0));
                }

                const token = localStorage.getItem('token');
                const params = new URLSearchParams({ start: startDate, end: endDate });

                const response = await fetch(`/api/reservations/calendar?${params}`, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Failed to load');

                const data = await response.json();
                if (data.success) {
                    this.events = data.data || [];
                }
            } catch (error) {
                console.error('Error loading calendar events:', error);
            } finally {
                this.loading = false;
            }
        },

        getEventsForDay(dateStr) {
            return this.events.filter(e => e.start === dateStr || (e.start && e.start.startsWith(dateStr)));
        },

        getEventGridStyle(ev, dayIndex) {
            const bgColor = ev.backgroundColor || '#6366f1';
            let startRow = 1;
            let spanRows = 1;

            if (ev.extendedProps?.reservation_time) {
                const parts = ev.extendedProps.reservation_time.split(':');
                const hour = parseInt(parts[0]) || 0;
                startRow = hour + 1; // grid rows are 1-based
                spanRows = 1;
            } else if (ev.start && ev.start.includes('T')) {
                const dt = new Date(ev.start);
                startRow = dt.getHours() + 1;
                spanRows = 1;
            }

            const col = dayIndex + 2;
            return `grid-column: ${col}; grid-row: ${startRow} / span ${spanRows}; background-color: ${bgColor}; border-left-color: ${bgColor};`;
        },

        getCurrentTimeGridStyle(dayIndex) {
            const now = new Date();
            const hour = now.getHours();
            const minuteFraction = now.getMinutes() / 60;
            // Place in the correct hour row, offset by minute fraction
            const col = dayIndex + 2;
            const row = hour + 1;
            return `grid-column: ${col}; grid-row: ${row}; align-self: start; margin-top: ${minuteFraction * 100}%;`;
        },

        formatHourLabel(hour) {
            if (hour === 0) return '';
            return String(hour).padStart(2, '0') + ':00 WIB';
        },

        updateCurrentTimeLine() {
            // No-op: current time line is CSS grid positioned
        },

        scrollToCurrentTime() {
            const grid = this.$refs.weekGrid;
            if (grid) {
                const now = new Date();
                // Each row is minmax(48px, 1fr), so use 48 as baseline
                const targetTop = Math.max(0, (now.getHours() - 1) * 48);
                grid.scrollTop = targetTop;
            }
        },

        viewDayDetail(day) {
            const evts = this.getEventsForDay(day.dateStr);
            this.dayDetailModal.events = evts;
            this.dayDetailModal.dateDisplay = day.dateObj.toLocaleDateString('id-ID', {
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
            });
            this.dayDetailModal.show = true;
        },

        viewEventDetail(ev) {
            this.eventModal.data = ev;
            const dateStr = ev.start?.split('T')[0] || ev.start;
            this.eventModal.dateDisplay = dateStr
                ? new Date(dateStr + 'T00:00:00').toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
                : '';
            this.eventModal.show = true;
        },

        toDateStr(d) {
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        },

        // Dashboard base methods
        addNotification(notif) {
            notif.id = Date.now() + Math.random();
            this.notifications.unshift(notif);
            if (this.notifications.length > 50) this.notifications = this.notifications.slice(0, 50);
            localStorage.setItem('notifications', JSON.stringify(this.notifications));
        },

        clearNotifications() {
            this.notifications = [];
            localStorage.removeItem('notifications');
        },

        removeNotification(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
            localStorage.setItem('notifications', JSON.stringify(this.notifications));
        },

        formatNotificationTime(timestamp) {
            let date = new Date(timestamp);
            let diff = Math.floor((new Date() - date) / 1000);
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            return date.toLocaleDateString();
        },

        logout() {
            let token = localStorage.getItem('token');
            if (token) {
                fetch(`${window.location.origin}/api/logout`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                }).finally(() => {
                    localStorage.removeItem('token');
                    localStorage.removeItem('user');
                    localStorage.removeItem('sidebarOpen');
                    localStorage.removeItem('notifications');
                    window.location.href = '/login';
                });
            } else {
                window.location.href = '/login';
            }
        }
    };
}
</script>

<style>
[x-cloak] { display: none !important; }
</style>
@endsection
