<template>
    <ModuleTabsHub
        title="Academics"
        subtitle="Branches, sessions, classes, subjects, timetable, homework and calendar — all in one place."
        module-key="academics"
        :tabs="TABS"
    />
</template>

<script setup>
import ModuleTabsHub from '../../components/common/ModuleTabsHub.vue';

/** Academics Management — one sidebar item, the Academics pages as tabs (see ModuleTabsHub). */
const TABS = [
    { id: 'general', label: 'General Setup', icon: 'building', items: [
        { key: 'branches', label: 'Branches', path: '/academics/branches' },
        { key: 'academic-sessions', label: 'Academic Sessions', path: '/academics/academic-sessions' },
    ] },
    { id: 'classes', label: 'Classes', icon: 'layers', items: [
        { key: 'classes-and-sections', label: 'Classes & Sections', path: '/academics/classes-and-sections' },
    ] },
    { id: 'subjects', label: 'Subjects', icon: 'book', items: [
        { key: 'subjects', label: 'Subjects', path: '/academics/subjects' },
        { key: 'subject-enrollment', label: 'Subject Enrollment', path: '/academics/subject-enrollment' },
    ] },
    { id: 'timetable', label: 'Timetable', icon: 'clock', items: [
        { key: 'class-routine', label: 'Class Routine', path: '/academics/class-routine' },
    ] },
    { id: 'homework', label: 'Homework', icon: 'pencil', items: [
        { key: 'homework', label: 'Homework', path: '/academics/homework' },
    ] },
    { id: 'calendar', label: 'Calendar', icon: 'calendar', items: [
        { key: 'academic-calendar', label: 'Academic Calendar', path: '/academics/academic-calendar' },
    ] },
];
</script>
