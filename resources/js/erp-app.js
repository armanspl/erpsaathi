import { createApp } from 'vue';
import ErpApp from './erp/ErpApp.vue';
import { router } from './erp/router';

createApp(ErpApp).use(router).mount('#erp-app');
