@php $locale = app()->getLocale(); @endphp

<div x-data="{ open: false }" style="position:relative;display:flex;align-items:center;">
    <button
        @click="open = !open"
        @click.outside="open = false"
        aria-label="Switch language"
        style="display:flex;align-items:center;gap:8px;padding:6px 12px;border-radius:8px;border:2px solid #e5e7eb;background:#fff;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,.1);transition:border-color .2s,box-shadow .2s;"
        onmouseover="this.style.borderColor='#6366f1';this.style.boxShadow='0 4px 6px rgba(0,0,0,.1)';"
        onmouseout="this.style.borderColor='#e5e7eb';this.style.boxShadow='0 1px 3px rgba(0,0,0,.1)';"
    >
        <svg xmlns="http://www.w3.org/2000/svg" style="width:18px;height:18px;color:#6366f1;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/>
            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
        </svg>
        <span style="font-size:14px;font-weight:500;color:#374151;">{{ $locale === 'id' ? 'ID' : 'EN' }}</span>
        <svg xmlns="http://www.w3.org/2000/svg" style="width:12px;height:12px;color:#6b7280;transition:transform .2s;" :style="open ? 'transform:rotate(180deg)' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 12 15 18 9"/>
        </svg>
    </button>

    <div
        x-show="open"
        x-cloak
        style="position:absolute;right:0;top:calc(100% + 8px);width:210px;background:#fff;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,.15);border:1px solid #e5e7eb;overflow:hidden;z-index:9999;"
    >
        <a
            href="#"
            onclick="switchLanguage(event, 'en')"
            style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;text-decoration:none;transition:background .15s;{{ $locale === 'en' ? 'background:rgba(99,102,241,.06);border-left:3px solid #6366f1;' : '' }}"
        >
            <div style="display:flex;align-items:center;gap:12px;">
                <span style="font-size:22px;line-height:1;">🇬🇧</span>
                <span style="font-size:14px;font-weight:500;color:{{ $locale === 'en' ? '#6366f1' : '#374151' }};">English</span>
            </div>
            @if($locale === 'en')
                <svg xmlns="http://www.w3.org/2000/svg" style="width:16px;height:16px;color:#6366f1;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            @endif
        </a>
        <a
            href="#"
            onclick="switchLanguage(event, 'id')"
            style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;text-decoration:none;transition:background .15s;{{ $locale === 'id' ? 'background:rgba(99,102,241,.06);border-left:3px solid #6366f1;' : '' }}"
        >
            <div style="display:flex;align-items:center;gap:12px;">
                <span style="font-size:22px;line-height:1;">🇮🇩</span>
                <span style="font-size:14px;font-weight:500;color:{{ $locale === 'id' ? '#6366f1' : '#374151' }};">Bahasa Indonesia</span>
            </div>
            @if($locale === 'id')
                <svg xmlns="http://www.w3.org/2000/svg" style="width:16px;height:16px;color:#6366f1;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            @endif
        </a>
    </div>
</div>

<script>
function switchLanguage(event, locale) {
    event.preventDefault();
    window.location.href = window.location.origin + '/language/' + locale;
}
</script>
