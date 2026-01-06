<script lang="ts" setup>
import axios from 'axios';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { logoUrl } from '@/Utils/logo';

type SharedProps = {
    auth: {
        user: {
            name: string;
            email?: string | null;
        } | null;
    };
};

type Stats = {
    total_users: number;
    new_users: number;
    active_users: number;
    total_episodes: number;
};

type TrendItem = {
    label: string;
    count: number;
};

type AdminUser = {
    id: number;
    name: string;
    email: string;
    role: string;
    created_at: string | null;
    episodes_count: number;
};

const props = defineProps<{
    stats: Stats;
    user_trend: TrendItem[];
    users: AdminUser[];
    profile: {
        name: string;
        email: string;
    };
}>();

const page = usePage<SharedProps>();
const sidebarOpen = ref(false);
const activeSection = ref<'dashboard' | 'users' | 'profile' | 'security'>('dashboard');

const userName = computed(() => page.props.auth.user?.name ?? 'Super Admin');
const userEmail = computed(() => page.props.auth.user?.email ?? 'admin@example.com');
const userInitial = computed(() => userName.value.trim().charAt(0).toUpperCase() || 'S');

const navigationLinks = computed(() => [
    { label: 'Overview', icon: 'home', section: 'dashboard' },
    { label: 'Users', icon: 'users', section: 'users' },
    { label: 'Profile', icon: 'profile', section: 'profile' },
    { label: 'Security', icon: 'shield', section: 'security' },
] as const);

const statsCards = computed(() => [
    {
        label: 'Total Users',
        value: props.stats.total_users.toLocaleString(),
        helper: 'All accounts',
    },
    {
        label: 'New This Week',
        value: props.stats.new_users.toLocaleString(),
        helper: 'Last 7 days',
    },
    {
        label: 'Active (30d)',
        value: props.stats.active_users.toLocaleString(),
        helper: 'User episodes recorded',
    },
    {
        label: 'Total Episodes',
        value: props.stats.total_episodes.toLocaleString(),
        helper: 'Lifetime logs',
    },
]);

const editUserState = reactive({
    open: false,
    loading: false,
    error: '',
    id: null as number | null,
    name: '',
    email: '',
    role: 'user',
});

const deleteUserState = reactive({
    open: false,
    loading: false,
    error: '',
    id: null as number | null,
    name: '',
});

const profileForm = reactive({
    name: props.profile.name,
    email: props.profile.email,
    loading: false,
    success: '',
    error: '',
});

const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
    loading: false,
    error: '',
    success: '',
});

function logout() {
    router.visit('/logout', {
        method: 'post',
        preserveState: false,
        onSuccess: () => {
            window.location.href = '/login';
        },
        onError: () => {
            window.location.href = '/login';
        },
    });
}

function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value;
}

function scrollToSection(section: 'dashboard' | 'users' | 'profile' | 'security') {
    activeSection.value = section;
    const el = document.getElementById(`admin-${section}`);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function openEditUser(user: AdminUser) {
    editUserState.open = true;
    editUserState.id = user.id;
    editUserState.name = user.name;
    editUserState.email = user.email;
    editUserState.role = user.role;
    editUserState.error = '';
}

function closeEditUser() {
    if (editUserState.loading) {
        return;
    }
    editUserState.open = false;
    editUserState.id = null;
}

async function saveUserEdits() {
    if (!editUserState.id) {
        return;
    }
    editUserState.loading = true;
    editUserState.error = '';
    try {
        await axios.put(`/super-admin/users/${editUserState.id}`, {
            name: editUserState.name,
            email: editUserState.email,
            role: editUserState.role,
        });
        editUserState.loading = false;
        editUserState.open = false;
        router.reload({ only: ['stats', 'users'] });
    } catch (error: any) {
        editUserState.loading = false;
        editUserState.error =
            error?.response?.data?.message ??
            error?.response?.data?.errors?.email?.[0] ??
            'Unable to update this user.';
    }
}

function confirmDeleteUser(user: AdminUser) {
    deleteUserState.open = true;
    deleteUserState.id = user.id;
    deleteUserState.name = user.name;
    deleteUserState.error = '';
}

function closeDeleteUser() {
    if (deleteUserState.loading) {
        return;
    }
    deleteUserState.open = false;
    deleteUserState.id = null;
}

async function deleteUser() {
    if (!deleteUserState.id) {
        return;
    }
    deleteUserState.loading = true;
    deleteUserState.error = '';
    try {
        await axios.delete(`/super-admin/users/${deleteUserState.id}`);
        deleteUserState.loading = false;
        deleteUserState.open = false;
        router.reload({ only: ['stats', 'users'] });
    } catch (error: any) {
        deleteUserState.loading = false;
        deleteUserState.error =
            error?.response?.data?.message ??
            error?.response?.data?.errors?.delete?.[0] ??
            'Unable to delete this user.';
    }
}

function impersonateUser(id: number) {
    router.post(`/super-admin/users/${id}/impersonate`);
}

async function saveProfile() {
    profileForm.loading = true;
    profileForm.error = '';
    profileForm.success = '';

    try {
        await axios.put('/super-admin/profile', {
            name: profileForm.name,
            email: profileForm.email,
        });
        profileForm.success = 'Profile updated.';
    } catch (error: any) {
        profileForm.error =
            error?.response?.data?.message ??
            error?.response?.data?.errors?.email?.[0] ??
            'Unable to update profile.';
    } finally {
        profileForm.loading = false;
    }
}

async function changePassword() {
    passwordForm.loading = true;
    passwordForm.error = '';
    passwordForm.success = '';

    try {
        await axios.put('/super-admin/password', {
            current_password: passwordForm.current_password,
            password: passwordForm.password,
            password_confirmation: passwordForm.password_confirmation,
        });
        passwordForm.current_password = '';
        passwordForm.password = '';
        passwordForm.password_confirmation = '';
        passwordForm.success = 'Password updated.';
    } catch (error: any) {
        passwordForm.error =
            error?.response?.data?.message ??
            error?.response?.data?.errors?.current_password?.[0] ??
            'Unable to update password.';
    } finally {
        passwordForm.loading = false;
    }
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }
    return date.toLocaleDateString();
}
</script>

<template>
    <Head title="Super Admin" />

    <div class="dashboard-layout">
        <aside :class="['dashboard-sidebar', sidebarOpen ? 'dashboard-sidebar--open' : 'dashboard-sidebar--collapsed']">
            <div class="sidebar-brand">
                <button class="sidebar-logo" type="button">
                    <img :src="logoUrl" alt="MigraineAI logo" class="sidebar-logo-image" />
                </button>
                <div v-if="sidebarOpen" class="sidebar-brand-text">
                    <p class="sidebar-brand-name">Super<span>Admin</span></p>
                    <p class="sidebar-brand-sub">Control Center</p>
                </div>
            </div>

            <nav class="sidebar-nav">
                <button
                    v-for="item in navigationLinks"
                    :key="item.label"
                    type="button"
                    class="sidebar-link"
                    :class="activeSection === item.section ? 'sidebar-link--active' : ''"
                    @click="scrollToSection(item.section)"
                >
                    <span class="sidebar-link-icon">
                        <svg
                            v-if="item.icon === 'home'"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            class="size-5"
                            fill="none"
                        >
                            <path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-5h-4v5H5a1 1 0 0 1-1-1v-9.5Z" fill="currentColor" />
                        </svg>
                        <svg
                            v-else-if="item.icon === 'users'"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            class="size-5"
                            fill="none"
                        >
                            <path
                                d="M7 14a3 3 0 1 0-3-3 3 3 0 0 0 3 3Zm10 0a3 3 0 1 0-3-3 3 3 0 0 0 3 3Zm-10 2c-2.667 0-5 1.333-5 3v1h8v-1c0-1.667-2.333-3-5-3Zm10 0c-1.183 0-2.247.235-3.088.637a4.07 4.07 0 0 1 1.088 2.363v1h8v-1c0-1.667-2.333-3-6-3Z"
                                fill="currentColor"
                            />
                        </svg>
                        <svg
                            v-else-if="item.icon === 'profile'"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            class="size-5"
                            fill="none"
                        >
                            <path
                                d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-3.33 0-6 2-6 4.5a.5.5 0 0 0 .5.5h11a.5.5 0 0 0 .5-.5C18 16 15.33 14 12 14Z"
                                fill="currentColor"
                            />
                        </svg>
                        <svg
                            v-else
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            class="size-5"
                            fill="none"
                        >
                            <path
                                d="M10.33 3.75a2 2 0 0 1 3.34 0l.35.58a2 2 0 0 0 1.54.94l.66.06a2 2 0 0 1 1.76 1.76l.06.66a2 2 0 0 0 .94 1.54l.59.35a2 2 0 0 1 0 3.34l-.59.35a2 2 0 0 0-.94 1.54l-.06.66a2 2 0 0 1-1.76 1.76l-.66.06a2 2 0 0 0-1.54.94l-.35.59a2 2 0 0 1-3.34 0l-.35-.59a2 2 0 0 0-1.54-.94l-.66-.06a2 2 0 0 1-1.76-1.76l-.06-.66a2 2 0 0 0-.94-1.54l-.59-.35a2 2 0 0 1 0-3.34l.59-.35a2 2 0 0 0 .94-1.54l.06-.66a2 2 0 0 1 1.76-1.76l.66-.06a2 2 0 0 0 1.54-.94l.35-.58Z"
                                stroke="currentColor"
                                stroke-width="1.5"
                            />
                            <circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.5" />
                        </svg>
                    </span>
                    <span v-if="sidebarOpen" class="sidebar-link-label">{{ item.label }}</span>
                </button>
            </nav>

            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <span class="sidebar-user-avatar">
                        <span>
                            {{ userInitial }}
                        </span>
                    </span>
                    <div v-if="sidebarOpen" class="sidebar-user-meta">
                        <p class="sidebar-user-name">{{ userName }}</p>
                        <p class="sidebar-user-email">{{ userEmail }}</p>
                    </div>
                </div>

                <button type="button" class="sidebar-logout" @click="logout">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                        <path d="M15 12H4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        <path d="m8 8-4 4 4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M15 5h2a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3h-2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                    <span v-if="sidebarOpen">Logout</span>
                </button>
            </div>
        </aside>

        <main class="dashboard-main">
            <header class="dashboard-toolbar">
                <button class="toolbar-toggle" type="button" @click="toggleSidebar">
                    <svg v-if="sidebarOpen" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                        <path d="M8 6h8M8 12h8M8 18h8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                        <path d="M5 12h14M12 5l7 7-7 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
                <img :src="logoUrl" alt="MigraineAI logo" class="toolbar-logo" />

                <div class="toolbar-breadcrumb">
                    <span class="toolbar-crumb">Super Admin</span>
                    <span class="toolbar-separator">/</span>
                    <span class="toolbar-crumb-active">Dashboard</span>
                </div>
            </header>

            <div class="pointer-events-none absolute inset-0 -z-10">
                <div class="absolute left-1/3 top-12 h-80 w-80 rounded-full bg-[--color-accent]/15 blur-[130px]"></div>
                <div class="absolute bottom-10 right-0 h-72 w-72 rounded-full bg-[--color-accent]/12 blur-[120px]"></div>
            </div>

            <div class="mx-auto flex w-full max-w-6xl flex-col gap-10">
                <section id="admin-dashboard" class="glass-panel p-6">
                    <header class="super-admin-header">
                        <div>
                            <p class="super-admin-kicker">Platform Metrics</p>
                            <h1>Operational Overview</h1>
                        </div>
                        <span class="super-admin-tag">Real-time data</span>
                    </header>
                    <div class="super-admin-stats">
                        <article v-for="card in statsCards" :key="card.label" class="super-admin-stat">
                            <p class="super-admin-stat-label">{{ card.label }}</p>
                            <p class="super-admin-stat-value">{{ card.value }}</p>
                            <p class="super-admin-stat-helper">{{ card.helper }}</p>
                        </article>
                    </div>

                    <div class="super-admin-trend">
                        <header>
                            <p>User Growth (last 6 months)</p>
                            <span>{{ props.stats.total_users.toLocaleString() }} total users</span>
                        </header>
                        <div class="super-admin-trend-bars">
                            <div v-for="item in props.user_trend" :key="item.label" class="super-admin-trend-item">
                                <span>{{ item.label }}</span>
                                <div class="super-admin-trend-bar">
                                    <span :style="{ width: `${Math.min(item.count, 100)}%` }"></span>
                                </div>
                                <small>{{ item.count }}</small>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="admin-users" class="glass-panel p-6">
                    <header class="super-admin-section-header">
                        <div>
                            <p class="super-admin-kicker">User Directory</p>
                            <h2>Manage Accounts</h2>
                        </div>
                        <span class="super-admin-tag">Total: {{ props.users.length }}</span>
                    </header>

                    <div class="super-admin-table-wrapper">
                        <table class="super-admin-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Episodes</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="user in props.users" :key="user.id">
                                    <td>{{ user.name }}</td>
                                    <td>{{ user.email }}</td>
                                    <td>
                                        <span class="super-admin-role" :class="user.role === 'super_admin' ? 'super-admin-role--accent' : ''">
                                            {{ user.role === 'super_admin' ? 'Super Admin' : 'User' }}
                                        </span>
                                    </td>
                                    <td>{{ user.episodes_count }}</td>
                                    <td>{{ formatDate(user.created_at) }}</td>
                                    <td class="super-admin-table-actions">
                                        <button type="button" class="analysis-table-button" @click="openEditUser(user)">Edit</button>
                                        <button
                                            type="button"
                                            class="analysis-table-button analysis-table-button--danger"
                                            @click="confirmDeleteUser(user)"
                                        >
                                            Delete
                                        </button>
                                        <button
                                            type="button"
                                            class="analysis-table-button analysis-table-button--primary"
                                            @click="impersonateUser(user.id)"
                                        >
                                            Login as
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="admin-profile" class="glass-panel p-6">
                    <header class="super-admin-section-header">
                        <div>
                            <p class="super-admin-kicker">Your Profile</p>
                            <h2>Account Information</h2>
                        </div>
                    </header>

                    <form class="super-admin-form-grid" @submit.prevent="saveProfile">
                        <label class="analysis-field">
                            <span>Name</span>
                            <input type="text" v-model="profileForm.name" />
                        </label>
                        <label class="analysis-field">
                            <span>Email</span>
                            <input type="email" v-model="profileForm.email" />
                        </label>
                        <div class="super-admin-form-actions">
                            <p v-if="profileForm.error" class="analysis-modal-error">{{ profileForm.error }}</p>
                            <p v-else-if="profileForm.success" class="analysis-modal-success">{{ profileForm.success }}</p>
                            <button type="submit" class="button-primary" :disabled="profileForm.loading">
                                {{ profileForm.loading ? 'Saving…' : 'Save Profile' }}
                            </button>
                        </div>
                    </form>
                </section>

                <section id="admin-security" class="glass-panel p-6">
                    <header class="super-admin-section-header">
                        <div>
                            <p class="super-admin-kicker">Security</p>
                            <h2>Change Password</h2>
                        </div>
                    </header>

                    <form class="super-admin-form-grid" @submit.prevent="changePassword">
                        <label class="analysis-field">
                            <span>Current Password</span>
                            <input type="password" v-model="passwordForm.current_password" />
                        </label>
                        <label class="analysis-field">
                            <span>New Password</span>
                            <input type="password" v-model="passwordForm.password" />
                        </label>
                        <label class="analysis-field">
                            <span>Confirm Password</span>
                            <input type="password" v-model="passwordForm.password_confirmation" />
                        </label>
                        <div class="super-admin-form-actions">
                            <p v-if="passwordForm.error" class="analysis-modal-error">{{ passwordForm.error }}</p>
                            <p v-else-if="passwordForm.success" class="analysis-modal-success">{{ passwordForm.success }}</p>
                            <button type="submit" class="analysis-table-button analysis-table-button--primary" :disabled="passwordForm.loading">
                                {{ passwordForm.loading ? 'Updating…' : 'Update Password' }}
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </main>
    </div>

    <div v-if="editUserState.open" class="analysis-modal-overlay">
        <div class="analysis-modal">
            <div class="analysis-modal-header">
                <div>
                    <p class="analysis-modal-kicker">Manage User</p>
                    <h4>Edit account</h4>
                </div>
                <button type="button" class="analysis-modal-close" @click="closeEditUser">&times;</button>
            </div>
            <form class="analysis-modal-body" @submit.prevent="saveUserEdits">
                <label class="analysis-field">
                    <span>Name</span>
                    <input type="text" v-model="editUserState.name" required />
                </label>
                <label class="analysis-field">
                    <span>Email</span>
                    <input type="email" v-model="editUserState.email" required />
                </label>
                <label class="analysis-field">
                    <span>Role</span>
                    <select v-model="editUserState.role">
                        <option value="user">User</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </label>
                <p v-if="editUserState.error" class="analysis-modal-error">{{ editUserState.error }}</p>
                <div class="analysis-modal-actions">
                    <button type="button" class="analysis-table-button" @click="closeEditUser">Cancel</button>
                    <button type="submit" class="analysis-table-button analysis-table-button--primary" :disabled="editUserState.loading">
                        {{ editUserState.loading ? 'Saving…' : 'Save User' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div v-if="deleteUserState.open" class="analysis-modal-overlay">
        <div class="analysis-modal analysis-modal--confirm">
            <div class="analysis-modal-header">
                <div>
                    <p class="analysis-modal-kicker">Delete User</p>
                    <h4>Remove {{ deleteUserState.name }}?</h4>
                </div>
                <button type="button" class="analysis-modal-close" @click="closeDeleteUser">&times;</button>
            </div>
            <div class="analysis-modal-body">
                <p class="analysis-modal-message">
                    Deleting this user will remove their access immediately. This action cannot be undone.
                </p>
                <p v-if="deleteUserState.error" class="analysis-modal-error">{{ deleteUserState.error }}</p>
                <div class="analysis-modal-actions">
                    <button type="button" class="analysis-table-button" @click="closeDeleteUser">Cancel</button>
                    <button
                        type="button"
                        class="analysis-table-button analysis-table-button--danger"
                        :disabled="deleteUserState.loading"
                        @click="deleteUser"
                    >
                        {{ deleteUserState.loading ? 'Deleting…' : 'Delete User' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
