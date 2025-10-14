<script>
export default ({
  props: {
    title: String,
    instructions: String,
    action: String,
    blueprint: Array,
    meta: Array,
    redirectTo: String,
    values: Array,
    data: Array,
    items: Array,
  },
  computed: {
    lastPage() {
      return this.paginationData.last_page || 1;
    },
  },
  data() {
    return {
      itemsSliced: [],
      perPage: 10,
      currentPage: 1,
      totalItems: 0,
      selectedFile: null,
      search: '',
      fileName: 'Choose a file...',
      selectedPage: '',
      paginationData: {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
        from: 0,
        to: 0,
      },
      searchTimeout: null,
      loading: false,
      showForm: true,
    };
  },
  watch: {
    search: {
      handler() {
        // Debounce search to avoid too many requests
        if (this.searchTimeout) {
          clearTimeout(this.searchTimeout);
        }
        this.searchTimeout = setTimeout(() => {
          this.currentPage = 1;
          this.fetchPaginatedData();
        }, 300);
      },
    },
  },
  mounted() {
    this.fetchPaginatedData();
  },
  methods: {
    async fetchPaginatedData() {
      this.loading = true;
      try {
        const response = await Statamic.$axios.get(cp_url('alt-design/alt-redirect/paginated'), {
          params: {
            page: this.currentPage,
            per_page: this.perPage,
            search: this.search,
          },
        });

        this.itemsSliced = response.data.data;
        this.paginationData = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          per_page: response.data.per_page,
          total: response.data.total,
          from: response.data.from,
          to: response.data.to,
        };
        this.totalItems = response.data.total;
      } catch (error) {
        console.error('Error fetching paginated data:', error);
        this.$toast.error('Error loading redirects');
      } finally {
        this.loading = false;
      }
    },
    updateItems(res) {
      // Hack to reset form
      this.values = res.data.values;
      this.showForm = false;

      this.$nextTick(() => {
        this.showForm = true;

        this.$nextTick(() => {
          this.$forceUpdate();
          this.fetchPaginatedData();
          this.$toast.success('Form reset');
        });
      });
    },
    setPage(page) {
      if (page >= 1 && page <= this.lastPage) {
        this.currentPage = page;
        this.fetchPaginatedData();
      }
    },
    deleteRedirect(id) {
      if (confirm('Are you sure you want to delete this redirect?')) {
        Statamic.$axios.post(cp_url('alt-design/alt-redirect/delete'), {
          id: id,
        }).then(res => {
          this.$toast.success('Redirect deleted successfully');
          this.fetchPaginatedData();
        }).catch(err => {
          console.error(err);
          this.$toast.error('Error deleting redirect');
        });
      }
    },
    importFromCSV() {
      if (!this.selectedFile) {
        alert('You haven\'t attached a CSV file!');
        return;
      }

      var formData = new FormData();
      formData.append('file', this.selectedFile);
      Statamic.$axios.post(cp_url('alt-design/alt-redirect/import'), formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      }).then(res => {
        this.$toast.success('Redirects imported successfully');
        this.fetchPaginatedData();
        this.selectedFile = null;
        this.fileName = 'Choose a file...';
      }).catch(err => {
        console.error(err);
        this.$toast.error('Invalid CSV file format. Check console for details.');
      });
    },
    handleFileUpload(event) {
      this.selectedFile = event.target.files[0];
      this.fileName = this.selectedFile ? this.selectedFile.name : 'Choose a file...';
    },
    dropdownPageChange() {
      this.setPage(parseInt(this.selectedPage));
    },
  },
});
</script>

<template>
  <div id="alt-redirect">

    <h1 class="flex-1">{{ title }}</h1>
    <h2 class="flex-1">{{ instructions }}</h2>

    <publish-form v-if="showForm" :title="''" :action="action" :blueprint="blueprint" :meta="meta" :values="values"
                  @saved="updateItems($event)" />

    <div class="card overflow-hidden p-0">
      <div class="mt-4 pb-2 px-4">
        <input type="text" class="input-text" v-model="search" placeholder="Search redirects..." :disabled="loading">
      </div>
      <div class="px-2">
        <table data-size="sm" tabindex="0" class="data-table" style="table-layout: fixed">
          <thead>
          <tr>
            <th class="group from-column sortable-column" style="width:33%">
              <span>From</span>
            </th>
            <th class="group from-column sortable-column pr-8 w-24" style="width:33%">
              <span>To</span>
            </th>
            <th class="group to-column pr-8 w-8" style="width:8%">
              <span>Match Type</span>
            </th>
            <th class="group to-column pr-8" style="width:8%">
              <span>Type</span>
            </th>
            <th class="group to-column pr-8" style="width:15%">
              <span>Sites</span>
            </th>
            <th class="actions-column" style="width:13.4%"></th>
          </tr>
          </thead>
          <tbody>
          <tr v-if="loading">
            <td colspan="6" class="text-center py-8">
              <div class="loading inline-block"></div>
              Loading redirects...
            </td>
          </tr>
          <tr v-else-if="itemsSliced.length === 0">
            <td colspan="6" class="text-center py-8 text-gray-600">
              {{ search ? 'No redirects match your search.' : 'No redirects found.' }}
            </td>
          </tr>
          <tr v-else v-for="item in itemsSliced" :key="item.id" style="width : 100%; overflow: clip">
            <td>
              {{ item.from }}
            </td>
            <td>
              {{ item.to }}
            </td>
            <td>
              {{ item.is_regex ? 'Regex' : 'Exact' }}
            </td>
            <td>
              {{ item.redirect_type }}
            </td>
            <td>
              {{ (item.sites && item.sites.length) ? item.sites.join(', ') : 'Unknown' }}
            </td>
            <td>
              <button @click="deleteRedirect(item.id)" class="btn-danger">Remove</button>
            </td>
          </tr>
          </tbody>
        </table>
      </div>
      <div v-if="!loading && totalItems > 0" class="pagination text-sm py-4 px-4 flex items-center justify-between">
        <div class="w-1/3 flex items-center">
          Page <span class="font-semibold mx-1" v-html="paginationData.current_page"></span> of <span class="mx-1"
                                                                                                      v-html="paginationData.last_page"></span>
        </div>
        <div class="w-1/3 flex items-center justify-center">
                    <span v-if="currentPage > 1" style="height: 15px; margin: 0 15px; width: 12px;" class="cursor-pointer"
                          @click="setPage(currentPage - 1 > 0 ? currentPage - 1 : 1)">
                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="205"
                             height="205" viewBox="0 0 205 205"><defs><clipPath id="clip-LEFT"><rect width="205"
                                                                                                     height="205"/></clipPath></defs><g
                            id="LEFT" clip-path="url(#clip-LEFT)"><rect width="205" height="205" fill-opacity="0"/><path
                            stroke="#2e9fff" fill="#2e9fff" id="Icon_awesome-arrow-left"
                            data-name="Icon awesome-arrow-left"
                            d="M114.961,184.524l-9.91,9.91a10.669,10.669,0,0,1-15.132,0L3.143,107.7a10.669,10.669,0,0,1,0-15.132L89.919,5.794a10.669,10.669,0,0,1,15.132,0l9.91,9.91a10.725,10.725,0,0,1-.179,15.311L60.994,82.259H189.283A10.687,10.687,0,0,1,200,92.972v14.284a10.687,10.687,0,0,1-10.713,10.713H60.994l53.789,51.244A10.648,10.648,0,0,1,114.961,184.524Z"
                            transform="translate(2.004 2.353)"/></g></svg>
                    </span>
          <!-- First Page -->
          <span v-if="currentPage > 1" class="cursor-pointer py-1 mx-1"
                @click="setPage(1)">1</span>
          <span v-if="currentPage == 1" class="cursor-pointer py-1 mx-1 font-semibold text-blue"
                @click="setPage(1)">1</span>

          <!-- Ellipsis for Previous Pages -->
          <span v-if="currentPage > 3">...</span>

          <!-- Previous Page -->
          <span v-if="currentPage > 2" class="cursor-pointer py-1 mx-1"
                @click="setPage(currentPage - 1)">{{ currentPage - 1 }}</span>

          <!-- Current Page (not shown if it's the first or last page) -->
          <span v-if="currentPage !== 1 && currentPage !== lastPage"
                class="cursor-pointer py-1 mx-1 font-semibold text-blue">{{ currentPage }}</span>

          <!-- Next Page -->
          <span v-if="currentPage < lastPage - 1" class="cursor-pointer py-1 mx-1"
                @click="setPage(currentPage + 1)">{{ currentPage + 1 }}</span>

          <!-- Ellipsis for Next Pages -->
          <span v-if="currentPage < lastPage - 2">...</span>

          <!-- Last Page -->
          <span v-if="currentPage < lastPage" class="cursor-pointer py-1 mx-1"
                @click="setPage(lastPage)">{{ lastPage }}</span>
          <span v-if="currentPage == lastPage && lastPage != 1"
                class="cursor-pointer py-1 mx-1 font-semibold text-blue"
                @click="setPage(lastPage)">{{ lastPage }}</span>
          <span v-if="currentPage < lastPage" style="height: 15px; margin: 0 15px; width: 12px;" class="cursor-pointer"
                @click="setPage(currentPage + 1 < lastPage ? currentPage + 1 : lastPage)">
                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="205"
                             height="205" viewBox="0 0 205 205"><defs><clipPath id="clip-RIGHT"><rect width="205"
                                                                                                      height="205"/></clipPath></defs><g
                            id="RIGHT" clip-path="url(#clip-RIGHT)"><rect width="205" height="205" fill-opacity="0"/><path
                            stroke="#2e9fff" fill="#2e9fff" id="Icon_awesome-arrow-left"
                            data-name="Icon awesome-arrow-left"
                            d="M85.032,184.524l9.91,9.91a10.669,10.669,0,0,0,15.132,0L196.85,107.7a10.669,10.669,0,0,0,0-15.132L110.073,5.794a10.669,10.669,0,0,0-15.132,0l-9.91,9.91a10.725,10.725,0,0,0,.179,15.311L139,82.259H10.71A10.687,10.687,0,0,0,0,92.972v14.284A10.687,10.687,0,0,0,10.71,117.969H139L85.21,169.214A10.648,10.648,0,0,0,85.032,184.524Z"
                            transform="translate(2.004 2.353)"/></g></svg>
                    </span>
        </div>
        <div class="w-1/3 flex justify-end">
          <select v-model="selectedPage" @change="dropdownPageChange" class="w-1/2 text-sm text-black">
            <option value="" disabled>Select Page</option>
            <option v-for="n in lastPage" :key="n" :value="n">{{ n }}</option>
          </select>
        </div>
      </div>
    </div>
    <div class="flex justify-between">
      <div class="w-full xl:w-1/2 card overflow-hidden p-0 mb-4 mt-4 mr-4 px-4 py-4">
        <span class="font-semibold mb-2">CSV Export</span><br>
        <p class="text-sm mb-4">Exports CSV of all redirects, use this format on import.</p>

        <a class="btn-primary" :href="cp_url('/alt-design/alt-redirect/export')" download>Export CSV</a>
      </div>

      <div class="w-full xl:w-1/2 card overflow-hidden p-0 mb-4 mt-4 ml-4 px-4 py-4">
        <span class="font-semibold mb-2">CSV Import</span><br>
        <p class="text-sm mb-4">Import CSV for redirects, use the export format on import.</p>

        <div class="flex justify-between items-center">
          <div>
            <input type="file" id='file-upload' @change="handleFileUpload" class="hidden">
            <label for="file-upload" class="btn-primary">Upload File</label>
            <span class="file-upload-cover px-4">{{ fileName }}</span>
          </div>
          <button class="btn-primary" @click="importFromCSV()">Import</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped></style>
