import Pusher from 'pusher-js';
import Echo from 'laravel-echo';

window.echoReady = false;

const connection = () => window.Echo?.connector?.pusher?.connection;
window.isEchoReady = () => window.echoReady && connection()?.state === 'connected';
window.getPusherState = () => connection()?.state ?? 'not_initialized';
window.reconnectPusher = () => {
    const client = window.Echo?.connector?.pusher;
    if (!client) return false;
    client.connect();
    return true;
};

async function getDashboardUser(token) {
    try {
        const cachedUser = JSON.parse(localStorage.getItem('user'));
        if (cachedUser?.id) return cachedUser;
    } catch {
        // Fetch the authenticated user if the cached entry is invalid.
    }

    const response = await fetch('/api/user', {
        headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    });
    if (!response.ok) throw new Error(`User lookup returned HTTP ${response.status}`);

    const data = await response.json();
    const user = data.data?.user ?? data.user ?? data;
    if (!user?.id) throw new Error('User lookup response is missing the user ID');

    return user;
}

async function connectWhatsAppChannel() {
    console.info('[WhatsApp realtime] Initializing Echo');

    const { key, cluster } = window.qashierBroadcastConfig ?? {};
    if (!key || !cluster) {
        throw new Error('Pusher configuration is missing PUSHER_APP_KEY or PUSHER_APP_CLUSTER');
    }

    const token = localStorage.getItem('token');
    if (!token) {
        console.warn('[WhatsApp realtime] No dashboard authentication token');
        return;
    }
    const user = await getDashboardUser(token);

    const client = new Pusher(key, {
        cluster,
        forceTLS: true,
        channelAuthorization: {
            endpoint: '/api/broadcasting/auth',
            headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
        },
    });
    window.Echo = new Echo({ broadcaster: 'pusher', client });

    console.info('[WhatsApp realtime] Pusher connection:', client.connection.state);
    client.connection.bind('state_change', ({ current }) => {
        if (current !== 'connected') window.echoReady = false;
        console.info('[WhatsApp realtime] Pusher connection:', current);
    });
    client.connection.bind('error', error => {
        console.error('[WhatsApp realtime] Pusher connection failed:', error);
    });

    const channelName = `whatsapp.${user.id}`;
    console.info('[WhatsApp realtime] Subscribing:', channelName);
    const channel = window.Echo.private(channelName);
    channel.subscribed(() => {
        window.echoReady = true;
        console.info('[WhatsApp realtime] Subscribed:', channelName);
        window.dispatchEvent(new Event('echo-ready'));
    });
    channel.error(error => {
        window.echoReady = false;
        console.error('[WhatsApp realtime] Channel subscription failed:', error);
    });

    for (const [serverEvent, browserEvent] of [
        ['message.new', 'whatsapp-message-received'],
        ['message.status', 'whatsapp-status-updated'],
        ['profile.updated', 'whatsapp-profile-updated'],
    ]) {
        channel.listen(`.${serverEvent}`, detail => {
            console.info('[WhatsApp realtime] Received:', serverEvent);
            window.dispatchEvent(new CustomEvent(browserEvent, { detail }));
        });
    }
}

connectWhatsAppChannel().catch(error => {
    console.error('[WhatsApp realtime] Setup failed:', error);
});
