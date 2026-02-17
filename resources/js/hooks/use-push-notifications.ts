import { useState, useEffect } from 'react';

/**
 * Hook para gestionar suscripciones a Push Notifications (PWA-007).
 * Usa la Web Push API del navegador con el Service Worker registrado por vite-plugin-pwa.
 */
export function usePushNotifications() {
    const [permission, setPermission] = useState<NotificationPermission>(
        typeof Notification !== 'undefined' ? Notification.permission : 'default',
    );
    const [subscribed, setSubscribed] = useState(false);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        checkSubscription();
    }, []);

    async function checkSubscription() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        setSubscribed(!!sub);
    }

    async function requestPermission(): Promise<boolean> {
        if (!('Notification' in window)) return false;

        const result = await Notification.requestPermission();
        setPermission(result);
        return result === 'granted';
    }

    async function subscribe() {
        setLoading(true);
        try {
            const granted = await requestPermission();
            if (!granted) return;

            if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

            const reg = await navigator.serviceWorker.ready;

            // Subscribe — VAPID public key should be set in .env (VAPID_PUBLIC_KEY)
            // For now we use a placeholder; real key is configured server-side
            const sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                // This key must come from the server in a real implementation
                // applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
            });

            // Send subscription to server
            const csrf =
                (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
                    ?.content ?? '';

            await fetch('/api/collector/push/subscribe', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(sub.toJSON()),
            });

            setSubscribed(true);
        } catch (err) {
            console.error('Error al suscribirse a notificaciones:', err);
        } finally {
            setLoading(false);
        }
    }

    async function unsubscribe() {
        setLoading(true);
        try {
            if (!('serviceWorker' in navigator)) return;

            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.getSubscription();

            if (sub) {
                await sub.unsubscribe();
                setSubscribed(false);
            }
        } finally {
            setLoading(false);
        }
    }

    /** Muestra una notificación local (sin servidor) — útil para confirmaciones offline */
    async function showLocalNotification(title: string, body: string) {
        if (permission !== 'granted') return;

        if ('serviceWorker' in navigator) {
            const reg = await navigator.serviceWorker.ready;
            await reg.showNotification(title, {
                body,
                icon: '/icon-192.png',
                badge: '/icon-192.png',
            });
        } else if ('Notification' in window) {
            new Notification(title, { body });
        }
    }

    const isSupported =
        typeof window !== 'undefined' && 'Notification' in window && 'serviceWorker' in navigator;

    return {
        permission,
        subscribed,
        loading,
        isSupported,
        subscribe,
        unsubscribe,
        showLocalNotification,
    };
}
