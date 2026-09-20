import { createApp } from 'vue';
import StudentApp from './student/StudentApp.vue';
import router from './student/router';

createApp(StudentApp).use(router).mount('#student-app');
