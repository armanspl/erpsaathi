import { createRouter, createWebHistory } from 'vue-router';
import { isModuleVisible } from '../store';

const routes = [
    { path: '/', name: 'dashboard', component: () => import('../pages/Dashboard.vue'), meta: { title: 'Dashboard' } },
    { path: '/profile', name: 'profile', component: () => import('../pages/Profile.vue'), meta: { title: 'My Profile', moduleKey: 'profile' } },
    { path: '/attendance', name: 'attendance', component: () => import('../pages/Attendance.vue'), meta: { title: 'Attendance', moduleKey: 'attendance' } },
    { path: '/examination', name: 'examination', component: () => import('../pages/Examination.vue'), meta: { title: 'Examination' } },
    { path: '/fees', name: 'fees', component: () => import('../pages/Fees.vue'), meta: { title: 'Fees' } },
    { path: '/transport', name: 'transport', component: () => import('../pages/Transport.vue'), meta: { title: 'Transport', moduleKey: 'transport' } },
    { path: '/events-calendar', name: 'events-calendar', component: () => import('../pages/EventsCalendar.vue'), meta: { title: 'Events & Calendar' } },
    { path: '/homework', name: 'homework', component: () => import('../pages/Homework.vue'), meta: { title: 'Homework', moduleKey: 'homework' } },
    { path: '/library', name: 'library', component: () => import('../pages/Library.vue'), meta: { title: 'Library', moduleKey: 'library' } },
    { path: '/documents', name: 'documents', component: () => import('../pages/Documents.vue'), meta: { title: 'My Documents', moduleKey: 'documents' } },
];

const router = createRouter({
    history: createWebHistory('/student/dashboard'),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach((to) => {
    const key = to.meta?.moduleKey;
    if (key && !isModuleVisible(key)) {
        return { path: '/' };
    }
    return true;
});

router.afterEach((to) => {
    document.title = to.meta?.title ? `${to.meta.title} — Student Portal` : 'Student Portal';
});

export default router;
