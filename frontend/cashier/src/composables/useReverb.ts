import { ref, onUnmounted } from 'vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useTablesStore } from '@/stores/tables';
import { useShiftStore } from '@/stores/shift';

(window as any).Pusher = Pusher;

let echoInstance: Echo<any> | null = null;
let isInitialized = false;

export function useReverb() {
  const authStore = useAuthStore();
  const tablesStore = useTablesStore();
  const shiftStore = useShiftStore();
  
  const connected = ref(false);
  const connectionError = ref<string | null>(null);

  function initialize(): void {
    const reverbEnabled = import.meta.env['VITE_REVERB_ENABLED'] === 'true';
    if (!reverbEnabled) {
      connectionError.value = null;
      connected.value = false;
      return;
    }

    if (isInitialized || !authStore.token || !authStore.outletId) return;
    
    const reverbHost = import.meta.env.VITE_REVERB_HOST || 'localhost';
    const reverbPort = parseInt(import.meta.env.VITE_REVERB_PORT || '8080', 10);
    const reverbScheme = import.meta.env.VITE_REVERB_SCHEME || 'http';
    const reverbKey = import.meta.env.VITE_REVERB_KEY || 'pos-key';

    echoInstance = new Echo<any>({
      broadcaster: 'reverb',
      key: reverbKey,
      wsHost: reverbHost,
      wsPort: reverbPort,
      wssPort: reverbPort,
      forceTLS: reverbScheme === 'https',
      enabledTransports: ['ws', 'wss'],
      authEndpoint: '/api/broadcasting/auth',
      auth: {
        headers: {
          Authorization: `Bearer ${authStore.token}`,
        },
      },
    });

    setupListeners();
    
    echoInstance.connector.pusher.connection.bind('connected', () => {
      connected.value = true;
      connectionError.value = null;
      console.log('[Reverb] Connected');
      fetchPendingBatches();
    });

    echoInstance.connector.pusher.connection.bind('disconnected', () => {
      connected.value = false;
      console.log('[Reverb] Disconnected');
    });

    echoInstance.connector.pusher.connection.bind('error', (err: any) => {
      connectionError.value = err.message || 'Connection error';
      console.error('[Reverb] Error:', err);
    });

    isInitialized = true;
  }

  function setupListeners(): void {
    if (!echoInstance) return;

    const outletId = authStore.outletId;
    if (!outletId) return;

    const channel = echoInstance.private(`outlet.${outletId}.cashier`);

    channel.listen('.OrderBatchSubmitted', (data: any) => {
      console.log('[Reverb] OrderBatchSubmitted:', data);
      tablesStore.updateTableStatus(data.tableId || '', 'occupied');
      shiftStore.fetchActiveShift(outletId);
    });

    channel.listen('.OrderBatchStatusChanged', (data: any) => {
      console.log('[Reverb] OrderBatchStatusChanged:', data);
    });
  }

  async function fetchPendingBatches(): Promise<void> {
    try {
      const response = await axios.get(
        `/api/order-batches?outlet_id=${authStore.outletId}&status=pending_confirmation`
      );
      // Update tables based on pending batches
      for (const batch of response.data.data) {
        if (batch.tableId) {
          tablesStore.updateTableStatus(batch.tableId, 'occupied');
        }
      }
    } catch (error) {
      console.error('[Reverb] Failed to fetch pending batches:', error);
    }
  }

  function disconnect(): void {
    if (echoInstance) {
      echoInstance.disconnect();
      echoInstance = null;
      isInitialized = false;
      connected.value = false;
    }
  }

  function reconnect(): void {
    disconnect();
    initialize();
  }

  onUnmounted(() => {
    disconnect();
  });

  return {
    connected,
    connectionError,
    initialize,
    disconnect,
    reconnect,
  };
}
