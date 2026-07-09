<script setup>
import { ref, computed, watch, onMounted, useTemplateRef } from 'vue';
import {
    Header, Heading, Subheading,
    PublishContainer, Card, Button, Badge, Icon,
    Input, Select, Pagination, Separator,
    Table, TableColumns, TableColumn, TableRows, TableRow, TableCell,
    Modal,
} from '@statamic/cms/ui';
import { Pipeline, BeforeSaveHooks, Request, AfterSaveHooks } from '@statamic/cms/save-pipeline';
import { router } from '@statamic/cms/inertia';

// Expose Statamic's global cp_url helper to the template scope
const cp_url = window.cp_url;

const props = defineProps({
    title: String,
    action: String,
    blueprint: Object,
    initialMeta: Object,
    initialValues: Object,
});

const values = ref({ ...props.initialValues });
const meta = ref({ ...props.initialMeta });
const container = useTemplateRef('container');
const errors = ref({});
const saving = ref(false);

const itemsSliced = ref([]);
const perPage = ref(10);
const currentPage = ref(1);
const totalItems = ref(0);
const selectedFile = ref(null);
const search = ref('');
const fileName = ref('Choose a file...');
const paginationData = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    from: 0,
    to: 0,
});
const loading = ref(false);
const fileInputKey = ref(0);
const deleteModalOpen = ref(false);
const deleteTargetId = ref(null);
const importModalOpen = ref(false);

const perPageOptions = [
    { label: '10', value: 10 },
    { label: '25', value: 25 },
    { label: '50', value: 50 },
    { label: '100', value: 100 },
];

let searchTimeout = null;

watch(search, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPage.value = 1;
        fetchPaginatedData();
    }, 300);
});

watch(perPage, () => {
    currentPage.value = 1;
    fetchPaginatedData();
});

onMounted(() => {
    fetchPaginatedData();
});

async function fetchPaginatedData() {
    loading.value = true;
    try {
        const params = new URLSearchParams({
            page: currentPage.value,
            per_page: perPage.value,
            search: search.value,
        });
        const response = await fetch(`${cp_url('alt-design/alt-redirect/paginated')}?${params}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        const data = await response.json();

        itemsSliced.value = data.data;
        paginationData.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            per_page: data.per_page,
            total: data.total,
            from: data.from,
            to: data.to,
        };
        totalItems.value = data.total;
    } catch (error) {
        console.error('Error fetching paginated data:', error);
        Statamic.$toast.error('Error loading redirects');
    } finally {
        loading.value = false;
    }
}

function save() {
    new Pipeline()
        .provide({ container, errors, saving })
        .through([
            new BeforeSaveHooks(),
            new Request(props.action, 'POST'),
            new AfterSaveHooks(),
        ])
        .then(() => {
            values.value = { ...props.initialValues };
            Statamic.$toast.success('Redirect saved successfully');
            fetchPaginatedData();
        })
        .catch(() => {});
}

function onPageSelected(page) {
    currentPage.value = page;
    fetchPaginatedData();
}

function onPerPageChanged(value) {
    perPage.value = value;
}

function confirmDelete(id) {
    deleteTargetId.value = id;
    deleteModalOpen.value = true;
}

function executeDelete() {
    const id = deleteTargetId.value;
    deleteModalOpen.value = false;
    deleteTargetId.value = null;

    router.post(cp_url('alt-design/alt-redirect/delete'), { id }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            Statamic.$toast.success('Redirect deleted successfully');
            fetchPaginatedData();
        },
        onError: () => {
            Statamic.$toast.error('Error deleting redirect');
        },
    });
}

function confirmImport() {
    if (!selectedFile.value) {
        Statamic.$toast.error("You haven't attached a CSV file!");
        return;
    }
    importModalOpen.value = true;
}

function executeImport() {
    importModalOpen.value = false;

    const formData = new FormData();
    formData.append('file', selectedFile.value);

    router.post(cp_url('alt-design/alt-redirect/import'), formData, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            Statamic.$toast.success('Redirects imported successfully');
            fetchPaginatedData();
            clearFile();
        },
        onError: () => {
            Statamic.$toast.error('Invalid CSV file format. Check console for details.');
        },
    });
}

function handleFileUpload(event) {
    selectedFile.value = event.target.files[0];
    fileName.value = selectedFile.value ? selectedFile.value.name : 'Choose a file...';
}

function handleDrop(event) {
    const file = event.dataTransfer.files[0];
    if (file && file.name.endsWith('.csv')) {
        selectedFile.value = file;
        fileName.value = file.name;
    }
}

function clearFile() {
    selectedFile.value = null;
    fileName.value = 'Choose a file...';
    fileInputKey.value++;
}
</script>

<template>
    <div id="alt-redirect">
        <Header>
            <template #title>{{ title }}</template>
            <template #actions>
                <Button variant="primary" @click="save" :loading="saving">Save</Button>
            </template>
        </Header>

        <PublishContainer
            ref="container"
            v-model="values"
            :blueprint="blueprint"
            :meta="meta"
            :errors="errors"
        />

        <Card class="mt-4 overflow-hidden">
            <div class="p-3">
                <Input
                    v-model="search"
                    placeholder="Search redirects..."
                    icon="search-magnifying-glass"
                    :clearable="true"
                    :loading="loading"
                    :disabled="loading"
                    size="base"
                />
            </div>

            <Separator />

            <Table>
                <TableColumns>
                    <TableColumn>From</TableColumn>
                    <TableColumn>To</TableColumn>
                    <TableColumn>Match Type</TableColumn>
                    <TableColumn>Type</TableColumn>
                    <TableColumn>Sites</TableColumn>
                    <TableColumn></TableColumn>
                </TableColumns>
                <TableRows>
                    <TableRow v-if="loading">
                        <TableCell colspan="6" class="text-center py-8">
                            <div class="loading inline-block"></div>
                            Loading redirects...
                        </TableCell>
                    </TableRow>
                    <TableRow v-else-if="itemsSliced.length === 0">
                        <TableCell colspan="6" class="text-center py-8 text-gray-500">
                            {{ search ? 'No redirects match your search.' : 'No redirects found.' }}
                        </TableCell>
                    </TableRow>
                    <TableRow v-else v-for="item in itemsSliced" :key="item.id">
                        <TableCell>{{ item.from }}</TableCell>
                        <TableCell>{{ item.to }}</TableCell>
                        <TableCell>
                            <Badge :color="item.is_regex ? 'orange' : 'green'" :text="item.is_regex ? 'Regex' : 'Exact'" size="sm" />
                        </TableCell>
                        <TableCell>
                            <Badge :text="item.redirect_type" color="blue" size="sm" />
                        </TableCell>
                        <TableCell>
                            <div class="flex flex-wrap gap-1">
                                <Badge v-for="(site, i) in (item.sites || [])" :key="i" :text="site" size="sm" />
                                <Badge v-if="!item.sites || !item.sites.length" text="Unknown" color="default" size="sm" />
                            </div>
                        </TableCell>
                        <TableCell class="text-right">
                            <Button variant="danger" size="sm" icon="trash" icon-only @click="confirmDelete(item.id)" />
                        </TableCell>
                    </TableRow>
                </TableRows>
            </Table>

            <Pagination
                v-if="!loading && totalItems > 0"
                :resource-meta="paginationData"
                :per-page="perPage"
                :show-totals="true"
                :show-page-links="true"
                :show-per-page-selector="true"
                :scroll-to-top="false"
                @page-selected="onPageSelected"
                @per-page-changed="onPerPageChanged"
            />
        </Card>

        <div class="flex gap-4 mt-4">
            <Card class="w-full xl:w-1/2 p-4">
                <Heading class="mb-1">CSV Export</Heading>
                <Subheading class="mb-4">Exports CSV of all redirects, use this format on import.</Subheading>
                <a :href="cp_url('/alt-design/alt-redirect/export')" download data-inertia="false">
                    <Button variant="primary" icon="download" as="span">Export CSV</Button>
                </a>
            </Card>
            <Card class="w-full xl:w-1/2 p-4">
                <Heading class="mb-1">CSV Import</Heading>
                <Subheading class="mb-4">Import CSV for redirects, use the export format on import.</Subheading>
                <div class="flex items-center gap-3">
                    <label
                        class="btn cursor-pointer inline-flex items-center gap-2"
                        :class="selectedFile ? 'btn-filled' : ''"
                        @dragover.prevent
                        @drop.prevent="handleDrop"
                    >
                        <Icon name="upload" class="w-4 h-4" />
                        <span>{{ selectedFile ? fileName : 'Choose CSV file...' }}</span>
                        <input :key="fileInputKey" type="file" accept=".csv" @change="handleFileUpload" class="sr-only">
                    </label>
                    <Button v-if="selectedFile" @click="clearFile" icon="trash" variant="danger" size="sm" icon-only />
                    <Button @click="confirmImport()" :disabled="!selectedFile" icon="upload">Import</Button>
                </div>
            </Card>
        </div>

        <!-- Delete confirmation modal -->
        <Modal v-model:open="deleteModalOpen" title="Delete Redirect" dismissible>
            <div class="p-4">
                <p>Are you sure you want to delete this redirect? This action cannot be undone.</p>
                <div class="flex justify-end gap-2 mt-4">
                    <Button @click="deleteModalOpen = false">Cancel</Button>
                    <Button variant="danger" @click="executeDelete">Delete</Button>
                </div>
            </div>
        </Modal>

        <!-- Import confirmation modal -->
        <Modal v-model:open="importModalOpen" title="Import CSV" dismissible>
            <div class="p-4">
                <p>Are you sure you want to import <strong>{{ fileName }}</strong>? We recommend making a backup of the existing redirects first.</p>
                <div class="flex justify-end gap-2 mt-4">
                    <Button @click="importModalOpen = false">Cancel</Button>
                    <Button variant="primary" @click="executeImport">Import</Button>
                </div>
            </div>
        </Modal>
    </div>
</template>

<style scoped></style>
