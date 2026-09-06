import { createRouter, createWebHistory } from 'vue-router';
import { menu } from '../data/menu';
import Dashboard from '../pages/Dashboard.vue';
import Students from '../pages/people/Students.vue';
import ImportExport from '../pages/ImportExport.vue';
import ChooseTemplate from '../pages/account/ChooseTemplate.vue';
import Profile from '../pages/account/Profile.vue';
import ChangePassword from '../pages/account/ChangePassword.vue';
import LoginSessions from '../pages/account/LoginSessions.vue';
import SupportTickets from '../pages/account/SupportTickets.vue';
import Notifications from '../pages/account/Notifications.vue';
// ApiTokens.vue is intentionally not routed — see the note in data/menu.js.
import SchoolSettings from '../pages/settings/SchoolSettings.vue';
import AcademicSessions from '../pages/settings/AcademicSessions.vue';
import Roles from '../pages/settings/Roles.vue';
import DatabaseBackup from '../pages/settings/DatabaseBackup.vue';
import Branches from '../pages/academics/Branches.vue';
import ClassesSections from '../pages/academics/ClassesSections.vue';
import Subjects from '../pages/academics/Subjects.vue';
import Users from '../pages/people/Users.vue';
import Parents from '../pages/people/Parents.vue';
import Teachers from '../pages/people/Teachers.vue';
import Staff from '../pages/people/Staff.vue';
import Drivers from '../pages/people/Drivers.vue';
import VisitorRecords from '../pages/people/VisitorRecords.vue';
import UdisePlus from '../pages/people/UdisePlus.vue';
import EmployeeMasterImport from '../pages/people/EmployeeMasterImport.vue';
import Admissions from '../pages/admissions/Admissions.vue';
import Registration from '../pages/admissions/Registration.vue';
import AdmissionSettings from '../pages/admissions/AdmissionSettings.vue';
import FollowUp from '../pages/admissions/FollowUp.vue';
import FeeHeads from '../pages/fee-management/FeeHeads.vue';
import FeeStructure from '../pages/fee-management/FeeStructure.vue';
import FeeDiscounts from '../pages/fee-management/FeeDiscounts.vue';
import FineRules from '../pages/fee-management/FineRules.vue';
import PayFee from '../pages/fee-management/PayFee.vue';
import FeeReceipts from '../pages/fee-management/FeeReceipts.vue';
import FeeDue from '../pages/fee-management/FeeDue.vue';
import FeeHistory from '../pages/fee-management/FeeHistory.vue';
import FeeDueReceipt from '../pages/fee-management/FeeDueReceipt.vue';
import DepositReceipt from '../pages/fee-management/DepositReceipt.vue';
import FeeCollectionReport from '../pages/fee-management/FeeCollectionReport.vue';
import FeeSettings from '../pages/fee-management/FeeSettings.vue';
import TallyAccounting from '../pages/fee-management/TallyAccounting.vue';
import AttendanceMarking from '../pages/attendance/AttendanceMarking.vue';
import StudentAttendance from '../pages/attendance/StudentAttendance.vue';
import DriverAttendance from '../pages/attendance/DriverAttendance.vue';
import StaffAttendance from '../pages/attendance/StaffAttendance.vue';
import LeaveManagement from '../pages/attendance/LeaveManagement.vue';
import LeaveRequests from '../pages/attendance/LeaveRequests.vue';
import JoiningAfterLeave from '../pages/attendance/JoiningAfterLeave.vue';
import Holidays from '../pages/attendance/Holidays.vue';
import WorkingDays from '../pages/attendance/WorkingDays.vue';
import AttendanceReports from '../pages/attendance/AttendanceReports.vue';
import ExamTypes from '../pages/exams/ExamTypes.vue';
import Exams from '../pages/exams/Exams.vue';
import ExamSchedule from '../pages/exams/ExamSchedule.vue';
import GradeSystem from '../pages/exams/GradeSystem.vue';
import MarksEntry from '../pages/exams/MarksEntry.vue';
import SeatPlanning from '../pages/exams/SeatPlanning.vue';
import QuestionBank from '../pages/exams/QuestionBank.vue';
import AdmitCards from '../pages/exams/AdmitCards.vue';
import ExamResults from '../pages/exams/ExamResults.vue';
import Terms from '../pages/exams/Terms.vue';
import AnnualReportCard from '../pages/exams/AnnualReportCard.vue';
import ExamReports from '../pages/exams/ExamReports.vue';
import ExpenseCategories from '../pages/finance-payroll/ExpenseCategories.vue';
import Expenses from '../pages/finance-payroll/Expenses.vue';
import Income from '../pages/finance-payroll/Income.vue';
import BankAccounts from '../pages/finance-payroll/BankAccounts.vue';
import BankTransactions from '../pages/finance-payroll/BankTransactions.vue';
import SalaryGenerate from '../pages/finance-payroll/SalaryGenerate.vue';
import SalarySlips from '../pages/finance-payroll/SalarySlips.vue';
import SalaryMonthlySheet from '../pages/finance-payroll/SalaryMonthlySheet.vue';
import SalaryReports from '../pages/finance-payroll/SalaryReports.vue';
import CashBook from '../pages/finance-payroll/CashBook.vue';
import BookStore from '../pages/finance-payroll/BookStore.vue';
import BookExpenses from '../pages/finance-payroll/BookExpenses.vue';
import TransportRoutes from '../pages/transport/Routes.vue';
import RouteStops from '../pages/transport/RouteStops.vue';
import Vehicles from '../pages/transport/Vehicles.vue';
import VehicleMaintenance from '../pages/transport/VehicleMaintenance.vue';
import FuelLogs from '../pages/transport/FuelLogs.vue';
import VehicleDocuments from '../pages/transport/VehicleDocuments.vue';
import StudentTransport from '../pages/transport/StudentTransport.vue';
import TransportReports from '../pages/transport/TransportReports.vue';
import LibraryCategories from '../pages/library/Categories.vue';
import Authors from '../pages/library/Authors.vue';
import Publishers from '../pages/library/Publishers.vue';
import Books from '../pages/library/Books.vue';
import LibraryMembers from '../pages/library/LibraryMembers.vue';
import BookIssue from '../pages/library/BookIssue.vue';
import BookReturn from '../pages/library/BookReturn.vue';
import FineCollection from '../pages/library/FineCollection.vue';
import LibraryReports from '../pages/library/LibraryReports.vue';
import InventoryProducts from '../pages/inventory/Products.vue';
import InventoryPurchase from '../pages/inventory/Purchase.vue';
import InventoryStock from '../pages/inventory/Stock.vue';
import InventorySupplier from '../pages/inventory/Supplier.vue';
import LowStockAlerts from '../pages/inventory/LowStockAlerts.vue';
import InventoryReports from '../pages/inventory/InventoryReports.vue';
import HostelRooms from '../pages/hostel/Rooms.vue';
import HostelBeds from '../pages/hostel/Beds.vue';
import HostelStudents from '../pages/hostel/HostelStudents.vue';
import HostelVisitors from '../pages/hostel/HostelVisitors.vue';
import HostelFee from '../pages/hostel/HostelFee.vue';
import HostelReports from '../pages/hostel/HostelReports.vue';
import Certificates from '../pages/documents/Certificates.vue';
import IdCards from '../pages/documents/IdCards.vue';
import LibraryCards from '../pages/documents/LibraryCards.vue';
import TransportCards from '../pages/documents/TransportCards.vue';
import TemplateBuilder from '../pages/documents/TemplateBuilder.vue';
import TemplateEditor from '../pages/documents/TemplateEditor.vue';
import RegisterReports from '../pages/reports/RegisterReports.vue';
import UdiseReport from '../pages/reports/UdiseReport.vue';
import StudentReports from '../pages/reports/StudentReports.vue';
import AdmissionReports from '../pages/reports/AdmissionReports.vue';
import FeeReports from '../pages/reports/FeeReports.vue';
import FinanceReports from '../pages/reports/FinanceReports.vue';
import GenericModule from '../pages/GenericModule.vue';
import Homework from '../pages/academics/Homework.vue';
import HelpAssistant from '../pages/help/HelpAssistant.vue';

// Flagship, fully custom pages get an explicit route; every other sidebar leaf
// falls back to GenericModule, which renders the shared "Common Module Layout".
const FLAGSHIP_ROUTES = {
    '/': Dashboard,
    '/help': HelpAssistant,
    '/people/students': Students,
    '/import-export': ImportExport,
    '/account/choose-template': ChooseTemplate,
    '/account/theme-creator': ChooseTemplate,
    '/account/profile': Profile,
    '/account/change-password': ChangePassword,
    '/account/login-sessions': LoginSessions,
    '/account/support-tickets': SupportTickets,
    '/account/notifications': Notifications,
    '/settings/school-settings': SchoolSettings,
    '/settings/academic-sessions': AcademicSessions,
    '/academics/academic-sessions': AcademicSessions,
    '/settings/roles-and-permissions': Roles,
    '/settings/database-backup': DatabaseBackup,
    '/academics/branches': Branches,
    '/academics/classes-and-sections': ClassesSections,
    '/academics/subjects': Subjects,
    '/academics/homework': Homework,
    '/people/users': Users,
    '/people/parents': Parents,
    '/people/teachers': Teachers,
    '/people/staff': Staff,
    '/people/drivers': Drivers,
    '/people/visitor-records': VisitorRecords,
    '/people/udiseplus': UdisePlus,
    '/people/employee-master-import': EmployeeMasterImport,
    '/admissions/enquiry': Admissions,
    '/admissions/registration': Registration,
    '/admissions/admission': Registration,
    '/admissions/admission-settings': AdmissionSettings,
    '/admissions/follow-up': FollowUp,
    '/fee-management/fee-heads': FeeHeads,
    '/fee-management/fee-structure': FeeStructure,
    '/fee-management/fee-discounts': FeeDiscounts,
    '/fee-management/fine-rules': FineRules,
    '/fee-management/pay-fee': PayFee,
    '/fee-management/fee-receipt': FeeReceipts,
    '/fee-management/fee-refund': FeeReceipts,
    '/fee-management/fee-due': FeeDue,
    '/fee-management/fee-history': FeeHistory,
    '/fee-management/fee-due/receipt': { component: FeeDueReceipt, meta: { printPage: true } },
    '/fee-management/fee-receipt/print': { component: DepositReceipt, meta: { printPage: true } },
    '/fee-management/fee-settings': FeeSettings,
    '/fee-management/tally-accounting': TallyAccounting,
    '/fee-management/fee-collection-report': FeeCollectionReport,
    '/fee-management/daily-collection': FeeCollectionReport,
    '/fee-management/online-payments': FeeCollectionReport,
    '/attendance/student-attendance': StudentAttendance,
    '/attendance/teacher-attendance': AttendanceMarking,
    '/attendance/staff-attendance': StaffAttendance,
    '/attendance/driver-attendance': DriverAttendance,
    '/attendance/leave-management': LeaveManagement,
    '/attendance/leave-approval': LeaveRequests,
    '/attendance/joining-after-leave': JoiningAfterLeave,
    '/attendance/holidays': Holidays,
    '/attendance/working-days': WorkingDays,
    '/attendance/attendance-reports': AttendanceReports,
    '/exam-management/exams': Exams,
    '/exam-management/terms': Terms,
    '/exam-management/exam-types': ExamTypes,
    '/exam-management/exam-schedule': ExamSchedule,
    '/exam-management/grade-system': GradeSystem,
    '/exam-management/marks-management': MarksEntry,
    '/exam-management/seat-planning': SeatPlanning,
    '/exam-management/question-bank': QuestionBank,
    '/exam-management/admit-cards': AdmitCards,
    '/exam-management/result-processing': ExamResults,
    '/exam-management/rank-generation': ExamResults,
    '/exam-management/exam-results': ExamResults,
    '/exam-management/report-cards': ExamResults,
    '/exam-management/annual-report-card': AnnualReportCard,
    '/exam-management/exam-reports': ExamReports,
    '/finance-and-payroll/expense-categories': ExpenseCategories,
    '/finance-and-payroll/office-expenses': Expenses,
    '/finance-and-payroll/income': Income,
    '/finance-and-payroll/bank-accounts': BankAccounts,
    '/finance-and-payroll/bank-transactions': BankTransactions,
    '/finance-and-payroll/salary-generate': SalaryGenerate,
    '/finance-and-payroll/salary-slips': SalarySlips,
    '/finance-and-payroll/salary-sheet': SalaryMonthlySheet,
    '/finance-and-payroll/salary-reports': SalaryReports,
    '/finance-and-payroll/cash-book': CashBook,
    '/finance-and-payroll/book-store': BookStore,
    '/finance-and-payroll/book-expenses': BookExpenses,
    '/transport-management/routes': TransportRoutes,
    '/transport-management/driver-assignment': TransportRoutes,
    '/transport-management/route-stops': RouteStops,
    '/transport-management/route-fare': RouteStops,
    '/transport-management/vehicles': Vehicles,
    '/transport-management/vehicle-maintenance': VehicleMaintenance,
    '/transport-management/fuel-logs': FuelLogs,
    '/transport-management/vehicle-documents': VehicleDocuments,
    '/transport-management/student-transport': StudentTransport,
    '/transport-management/transport-reports': TransportReports,
    '/library/categories': LibraryCategories,
    '/library/authors': Authors,
    '/library/publishers': Publishers,
    '/library/books': Books,
    '/library/library-members': LibraryMembers,
    '/library/book-issue': BookIssue,
    '/library/book-return': BookReturn,
    '/library/fine-collection': FineCollection,
    '/library/library-reports': LibraryReports,
    '/inventory/products': InventoryProducts,
    '/inventory/purchase': InventoryPurchase,
    '/inventory/stock': InventoryStock,
    '/inventory/supplier': InventorySupplier,
    '/inventory/low-stock-alerts': LowStockAlerts,
    '/inventory/inventory-reports': InventoryReports,
    '/hostel/rooms': HostelRooms,
    '/hostel/beds': HostelBeds,
    '/hostel/hostel-students': HostelStudents,
    '/hostel/hostel-visitors': HostelVisitors,
    '/hostel/hostel-fee': HostelFee,
    '/hostel/hostel-reports': HostelReports,
    '/documents/id-cards': IdCards,
    '/documents/certificates': Certificates,
    '/documents/bonafide': Certificates,
    '/documents/transfer-certificate': Certificates,
    '/documents/character-certificate': Certificates,
    '/documents/migration-certificate': Certificates,
    '/documents/library-cards': LibraryCards,
    '/documents/transport-cards': TransportCards,
    '/documents/template-builder': TemplateBuilder,
    '/reports/class-wise': RegisterReports,
    '/reports/area-wise': RegisterReports,
    '/reports/father-wise': RegisterReports,
    '/reports/vehicle-wise': RegisterReports,
    '/reports/udise': UdiseReport,
    '/reports/student-reports': StudentReports,
    '/reports/admission-reports': AdmissionReports,
    '/reports/fee-reports': FeeReports,
    '/reports/finance-reports': FinanceReports,
    '/reports/attendance-reports': AttendanceReports,
    '/reports/salary-reports': SalaryReports,
    '/reports/transport-reports': TransportReports,
    '/reports/examination-reports': ExamReports,
    '/reports/library-reports': LibraryReports,
};

const seenPaths = new Set(Object.keys(FLAGSHIP_ROUTES));
const routes = Object.entries(FLAGSHIP_ROUTES).map(([path, def]) => {
    if (def && typeof def === 'object' && def.component) {
        return { path, component: def.component, meta: def.meta || {} };
    }
    return { path, component: def };
});

for (const group of menu) {
    for (const child of group.children) {
        if (!child.path || seenPaths.has(child.path)) continue;
        seenPaths.add(child.path);
        routes.push({
            path: child.path,
            component: GenericModule,
            meta: { label: child.label, group: child.group },
        });
    }
}

routes.push({ path: '/documents/template-builder/:id', component: TemplateEditor });
routes.push({ path: '/:pathMatch(.*)*', redirect: '/' });

export const router = createRouter({
    history: createWebHistory('/erp/dashboard'),
    routes,
    scrollBehavior() {
        return { top: 0 };
    },
});

router.beforeEach(async (to) => {
    const { erpStore, loadPermissions } = await import('../store');
    const { usePermissions } = await import('../composables/usePermissions');
    const { findMenuItemByPath } = await import('../data/menu');

    if (!erpStore.permissionsLoaded) {
        await loadPermissions();
    }

    const { can } = usePermissions();
    if (erpStore.user?.role === 'admin' || (erpStore.permissions || []).includes('*')) {
        return true;
    }

    if (to.path === '/' || to.path === '') {
        return can('dashboard.dashboard.view') ? true : { path: '/account/profile' };
    }

    const hit = findMenuItemByPath(to.path);
    if (!hit?.group || !hit?.child) {
        return true;
    }
    if (hit.child.action === 'logout') return true;

    let key = `${hit.group.key}.${hit.child.key}.view`;
    if (hit.group.key === 'account' && hit.child.key === 'change-password') {
        key = 'account.change-password.edit';
    }
    if (hit.group.key === 'import-and-export' && to.query?.type) {
        key = `import-and-export.${to.query.type}.view`;
    }

    if (can(key)) return true;
    return { path: '/' };
});
