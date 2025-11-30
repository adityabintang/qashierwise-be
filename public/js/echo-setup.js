/**
 * Laravel Echo Setup for WhatsApp Dashboard
 * This file initializes Pusher and Laravel Echo for real-time messaging
 */

(function() {
    'use strict';

    console.log('🚀 Echo Setup Script Loading...');

    // Wait for DOM and all scripts to be ready
    window.addEventListener('DOMContentLoaded', function() {
        console.log('📡 Initializing Laravel Echo with Pusher...');

        // Check if Pusher is loaded
        if (typeof Pusher === 'undefined') {
            console.error('❌ Pusher library not loaded!');
            return;
        }

        // Check if Echo is loaded
        if (typeof Echo === 'undefined') {
            console.error('❌ Laravel Echo library not loaded!');
            return;
        }

        // Enable Pusher logging for debugging
        Pusher.logToConsole = true;

        // Get API base URL - use API subdomain
        const API_BASE_URL = 'https://api.qashierwise.com/api';

        // Get authentication token
        const token = localStorage.getItem('token');
        if (!token) {
            console.warn('⚠️ No authentication token found');
            return;
        }

        // Get user info
        let user = null;
        try {
            const storedUser = localStorage.getItem('user');
            if (storedUser) {
                user = JSON.parse(storedUser);
                console.log('👤 User loaded:', user);
            }
        } catch (e) {
            console.error('❌ Failed to parse user data:', e);
        }

        // Fetch user if not available
        if (!user || !user.id) {
            console.log('🔍 Fetching user info from API...');
            fetch(`${API_BASE_URL}/user`, {
                headers: { 'Authorization': `Bearer ${token}` }
            })
            .then(res => res.json())
            .then(data => {
                user = data.data || data.user || data;
                localStorage.setItem('user', JSON.stringify(user));
                console.log('✅ User info fetched:', user);

                if (user.id) {
                    initializeEcho(user.id, API_BASE_URL, token);
                }
            })
            .catch(e => {
                console.error('❌ Failed to fetch user info:', e);
            });
        } else if (user.id) {
            console.log('✅ User ID found:', user.id);
            initializeEcho(user.id, API_BASE_URL, token);
        }
    });

    /**
     * Initialize Laravel Echo with Pusher
     */
    function initializeEcho(userId, apiBaseUrl, token) {
        console.log('🎯 Initializing Echo for user:', userId);

        try {
            // Initialize Pusher first with basic config
            window.pusherInstance = new Pusher('5e011e24b7fe71be40c7', {
                cluster: 'ap1',
                forceTLS: true,
                authEndpoint: `${apiBaseUrl}/broadcasting/auth`,
                auth: {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                }
            });

            console.log('✅ Pusher instance created');

            // Create Echo instance with Pusher
            window.Echo = new Echo({
                broadcaster: 'pusher',
                key: '5e011e24b7fe71be40c7',
                cluster: 'ap1',
                forceTLS: true,
                authEndpoint: `${apiBaseUrl}/broadcasting/auth`,
                auth: {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                }
            });

            console.log('✅ Echo instance created successfully');

            // Bind to Pusher connection events
            window.Echo.connector.pusher.connection.bind('state_change', function(states) {
                console.log('📡 Pusher connection state:', states.previous, '->', states.current);
            });

            window.Echo.connector.pusher.connection.bind('connected', function() {
                console.log('✅✅ Pusher CONNECTED successfully!');
            });

            window.Echo.connector.pusher.connection.bind('disconnected', function() {
                console.warn('⚠️ Pusher disconnected');
            });

            window.Echo.connector.pusher.connection.bind('error', function(err) {
                console.error('❌ Pusher connection error:', err);
            });

            // Subscribe to private WhatsApp channel
            console.log('📢 Subscribing to channel: whatsapp.' + userId);

            const channel = window.Echo.private(`whatsapp.${userId}`);

            // Handle subscription success
            channel.subscribed(() => {
                console.log('✅✅✅ Successfully subscribed to whatsapp.' + userId + ' channel!');
            });

            // Handle subscription error
            channel.error((error) => {
                console.error('❌ Channel subscription error:', error);
            });

            // Listen for new message events
            channel.listen('.message.new', (data) => {
                console.log('🔔🔔 NEW MESSAGE EVENT RECEIVED!', data);

                // Dispatch custom event for other parts of the app
                window.dispatchEvent(new CustomEvent('whatsapp-message-received', {
                    detail: data
                }));
            });

            // Listen for message status updates
            channel.listen('.message.status', (data) => {
                console.log('📊 MESSAGE STATUS UPDATE RECEIVED!', data);

                // Dispatch custom event for status updates
                window.dispatchEvent(new CustomEvent('whatsapp-status-updated', {
                    detail: data
                }));
            });

            // Listen for profile updates
            channel.listen('.profile.updated', (data) => {
                console.log('👤 PROFILE UPDATE RECEIVED!', data);

                // Dispatch custom event for profile updates
                window.dispatchEvent(new CustomEvent('whatsapp-profile-updated', {
                    detail: data
                }));
            });

            // Mark Echo as ready
            window.echoReady = true;
            window.dispatchEvent(new Event('echo-ready'));

            console.log('✅ Echo setup complete! Ready to receive messages.');

        } catch (error) {
            console.error('❌ Failed to initialize Echo:', error);
        }
    }

    // Expose helper function to check if Echo is ready
    window.isEchoReady = function() {
        return window.echoReady === true &&
               window.Echo &&
               typeof window.Echo.private === 'function';
    };

})();
