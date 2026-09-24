import { createApp } from 'vue';
import { createPinia } from 'pinia';
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate';
import router from './router';
import './style.css';
import App from './App.vue';
import { registerSW } from 'virtual:pwa-register';

const app = createApp(App);

const pinia = createPinia();
pinia.use(piniaPluginPersistedstate);

app.use(pinia);
app.use(router);

app.mount('#app');

if (import.meta.env.PROD) {
  registerSW({
    onNeedRefresh() {
      if (confirm('Ada update baru. Muat ulang?')) {
        window.location.reload();
      }
    },
    onOfflineReady() {
      console.log('Aplikasi siap digunakan offline');
    },
  });
}